<?php

namespace Tests\Feature;

use App\Models\PanelNormalization;
use App\Models\PanelNormalizationAlias;
use App\Services\Panel\PanelCanonicalizer;
use App\Services\Panel\PanelRuleRepository;
use Database\Seeders\PanelNormalizationSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PanelNormalizationControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PanelNormalizationSeeder::class);
    }

    public function test_rule_page_and_seeded_rules_are_available(): void
    {
        $this->get('/client/panel-chuan-hoa')->assertOk()->assertSee('Danh mục PANEL chuẩn hóa');
        $this->getJson('/api/panel-chuan-hoa?keyword=L%2FB')
            ->assertOk()
            ->assertJsonFragment(['standard_name' => 'LEFT BACK'])
            ->assertJsonFragment(['alias' => 'L/B']);
    }

    public function test_create_update_and_delete_clear_rule_cache(): void
    {
        $suffix = strtoupper(substr(md5((string) microtime(true)), 0, 8));
        Cache::put(PanelRuleRepository::CACHE_KEY, ['stale' => true], 60);
        $response = $this->postJson('/api/panel-chuan-hoa', [
            'standard_name' => 'TEST ' . $suffix,
            'status' => 'AUTO',
            'aliases' => ['TEST-' . $suffix, 'T/' . $suffix],
            'sort_order' => 9999,
            'is_active' => true,
        ])->assertCreated();
        $id = $response->json('data.id');
        $this->assertFalse(Cache::has(PanelRuleRepository::CACHE_KEY));

        Cache::put(PanelRuleRepository::CACHE_KEY, ['stale' => true], 60);
        $this->putJson('/api/panel-chuan-hoa/' . $id, [
            'standard_name' => 'TEST ' . $suffix,
            'status' => 'PENDING',
            'aliases' => ['TEST-' . $suffix],
            'is_active' => false,
        ])->assertOk()->assertJsonPath('data.status', 'PENDING');
        $this->assertFalse(Cache::has(PanelRuleRepository::CACHE_KEY));

        Cache::put(PanelRuleRepository::CACHE_KEY, ['stale' => true], 60);
        $this->deleteJson('/api/panel-chuan-hoa/' . $id)->assertOk();
        $this->assertFalse(Cache::has(PanelRuleRepository::CACHE_KEY));
        $this->assertDatabaseMissing('panel_normalizations', ['id' => $id], 'internal');
    }

    public function test_duplicate_alias_rejects_update_without_losing_existing_aliases(): void
    {
        $suffix = strtoupper(substr(md5((string) microtime(true)), 0, 8));
        $canonicalizer = app(PanelCanonicalizer::class);
        $owner = PanelNormalization::query()->create([
            'standard_name' => 'OWNER ' . $suffix,
            'normalized_standard_name' => 'OWNER ' . $suffix,
            'status' => 'AUTO', 'is_active' => true, 'sort_order' => 9000,
        ]);
        $owner->aliases()->create(['alias' => 'OWNER-' . $suffix, 'normalized_alias' => 'OWNER-' . $suffix]);
        $target = PanelNormalization::query()->create([
            'standard_name' => 'TARGET ' . $suffix,
            'normalized_standard_name' => 'TARGET ' . $suffix,
            'status' => 'AUTO', 'is_active' => true, 'sort_order' => 9001,
        ]);
        $target->aliases()->create(['alias' => 'OLD-' . $suffix, 'normalized_alias' => 'OLD-' . $suffix]);

        try {
            $this->putJson('/api/panel-chuan-hoa/' . $target->id, [
                'standard_name' => $target->standard_name,
                'status' => 'AUTO',
                'aliases' => ['NEW-' . $suffix, 'OWNER-' . $suffix],
            ])->assertStatus(422)->assertJsonValidationErrors('aliases');

            $this->assertDatabaseHas('panel_normalization_aliases', [
                'panel_normalization_id' => $target->id,
                'normalized_alias' => $canonicalizer->canonical('OLD-' . $suffix),
            ], 'internal');
            $this->assertDatabaseMissing('panel_normalization_aliases', [
                'panel_normalization_id' => $target->id,
                'normalized_alias' => $canonicalizer->canonical('NEW-' . $suffix),
            ], 'internal');
        } finally {
            $owner->delete();
            $target->delete();
        }
    }

    public function test_database_unique_index_rejects_duplicate_normalized_alias(): void
    {
        $suffix = strtoupper(substr(md5((string) microtime(true)), 0, 8));
        $first = PanelNormalization::query()->create([
            'standard_name' => 'UNIQUE A ' . $suffix, 'normalized_standard_name' => 'UNIQUE A ' . $suffix,
            'status' => 'AUTO', 'is_active' => true, 'sort_order' => 9100,
        ]);
        $second = PanelNormalization::query()->create([
            'standard_name' => 'UNIQUE B ' . $suffix, 'normalized_standard_name' => 'UNIQUE B ' . $suffix,
            'status' => 'AUTO', 'is_active' => true, 'sort_order' => 9101,
        ]);
        $first->aliases()->create(['alias' => 'DUP-' . $suffix, 'normalized_alias' => 'DUP-' . $suffix]);

        try {
            $thrown = false;
            try {
                $second->aliases()->create(['alias' => 'dup-' . $suffix, 'normalized_alias' => 'DUP-' . $suffix]);
            } catch (QueryException $exception) {
                $thrown = true;
            }
            $this->assertTrue($thrown, 'Unique index must reject a duplicate normalized alias.');
        } finally {
            $first->delete();
            $second->delete();
        }
    }

    public function test_seeder_can_run_repeatedly_without_duplicates(): void
    {
        $rulesBefore = PanelNormalization::query()->count();
        $aliasesBefore = PanelNormalizationAlias::query()->count();
        $this->seed(PanelNormalizationSeeder::class);
        $this->seed(PanelNormalizationSeeder::class);
        $this->assertSame($rulesBefore, PanelNormalization::query()->count());
        $this->assertSame($aliasesBefore, PanelNormalizationAlias::query()->count());
    }
}
