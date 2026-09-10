<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePanelNormalizationRequest;
use App\Http\Requests\UpdatePanelNormalizationRequest;
use App\Models\PanelNormalization;
use App\Models\PanelNormalizationAlias;
use App\Services\Panel\PanelCanonicalizer;
use App\Services\Panel\PanelRuleRepository;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PanelNormalizationController extends Controller
{
    private $canonicalizer;
    private $rules;

    public function __construct(PanelCanonicalizer $canonicalizer, PanelRuleRepository $rules)
    {
        $this->canonicalizer = $canonicalizer;
        $this->rules = $rules;
    }

    public function page()
    {
        return view('Client.panel-normalizations');
    }

    public function index(Request $request)
    {
        $data = $request->validate([
            'keyword' => 'nullable|string|max:255',
            'status' => 'nullable|in:AUTO,PENDING',
            'active' => 'nullable|in:0,1',
            'per_page' => 'nullable|integer|min:10|max:100',
        ]);
        $keyword = trim((string) ($data['keyword'] ?? ''));

        $query = PanelNormalization::query()
            ->with('aliases')
            ->when($keyword !== '', function ($query) use ($keyword) {
                $query->where(function ($query) use ($keyword) {
                    $query->where('standard_name', 'like', '%' . $keyword . '%')
                        ->orWhereHas('aliases', function ($query) use ($keyword) {
                            $query->where('alias', 'like', '%' . $keyword . '%');
                        });
                });
            })
            ->when(!empty($data['status']), function ($query) use ($data) {
                $query->where('status', $data['status']);
            })
            ->when(array_key_exists('active', $data), function ($query) use ($data) {
                $query->where('is_active', (bool) $data['active']);
            })
            ->orderBy('sort_order')
            ->orderBy('standard_name')
            ->orderBy('id');

        $rows = $query->paginate((int) ($data['per_page'] ?? 30));
        $stats = PanelNormalization::query()
            ->selectRaw('COUNT(*) AS total_count')
            ->selectRaw("SUM(CASE WHEN status = 'AUTO' AND is_active = 1 THEN 1 ELSE 0 END) AS automatic_count")
            ->selectRaw("SUM(CASE WHEN status = 'PENDING' THEN 1 ELSE 0 END) AS pending_count")
            ->selectRaw('SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) AS inactive_count')
            ->first();

        return response()->json([
            'data' => $rows->items(),
            'meta' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'total' => $rows->total(),
            ],
            'stats' => [
                'total' => (int) ($stats->total_count ?? 0),
                'automatic' => (int) ($stats->automatic_count ?? 0),
                'pending' => (int) ($stats->pending_count ?? 0),
                'inactive' => (int) ($stats->inactive_count ?? 0),
            ],
        ]);
    }

    public function store(StorePanelNormalizationRequest $request)
    {
        $normalization = $this->persist(null, $request->validated());

        return response()->json([
            'message' => 'Đã thêm quy tắc PANEL.',
            'data' => $normalization,
        ], 201);
    }

    public function update(UpdatePanelNormalizationRequest $request, PanelNormalization $panelNormalization)
    {
        $normalization = $this->persist($panelNormalization, $request->validated());

        return response()->json([
            'message' => 'Đã cập nhật quy tắc PANEL.',
            'data' => $normalization,
        ]);
    }

    public function destroy(PanelNormalization $panelNormalization)
    {
        DB::connection('internal')->transaction(function () use ($panelNormalization) {
            $panelNormalization->delete();
        });
        $this->rules->forget();

        return response()->json(['message' => 'Đã xóa quy tắc PANEL.']);
    }

    private function persist(?PanelNormalization $normalization, array $data): PanelNormalization
    {
        $aliases = $this->normalizedAliases($data['aliases']);
        $standardName = isset($data['standard_name']) ? preg_replace('/\s+/u', ' ', trim($data['standard_name'])) : null;
        $normalizedStandard = $standardName ? $this->canonicalizer->canonical($standardName) : null;
        $this->ensureAvailable($normalization, $normalizedStandard, $aliases);
        $userId = auth()->id();

        try {
            $saved = DB::connection('internal')->transaction(function () use ($normalization, $data, $aliases, $standardName, $normalizedStandard, $userId) {
                if (!$normalization) {
                    $normalization = new PanelNormalization();
                    $normalization->created_by = $userId;
                }

                $normalization->fill([
                    'standard_name' => $standardName,
                    'normalized_standard_name' => $normalizedStandard,
                    'status' => $data['status'],
                    'note' => isset($data['note']) ? trim((string) $data['note']) ?: null : null,
                    'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
                    'sort_order' => (int) ($data['sort_order'] ?? 0),
                    'updated_by' => $userId,
                ])->save();

                $normalization->aliases()->delete();
                foreach ($aliases as $alias) {
                    $normalization->aliases()->create($alias);
                }

                return $normalization->load('aliases');
            });
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() === '23000') {
                throw ValidationException::withMessages([
                    'aliases' => 'Tên chuẩn hoặc cách viết đã thuộc một quy tắc PANEL khác.',
                ]);
            }
            throw $exception;
        }

        $this->rules->forget();

        return $saved;
    }

    private function normalizedAliases(array $aliases): array
    {
        $result = [];
        foreach ($aliases as $alias) {
            $display = preg_replace('/\s+/u', ' ', trim((string) $alias));
            $normalized = $this->canonicalizer->canonical($display);
            if (isset($result[$normalized])) {
                throw ValidationException::withMessages([
                    'aliases' => 'Các cách viết không được trùng nhau sau khi chuẩn hóa.',
                ]);
            }
            $result[$normalized] = ['alias' => $display, 'normalized_alias' => $normalized];
        }

        return array_values($result);
    }

    private function ensureAvailable(?PanelNormalization $normalization, ?string $standardName, array $aliases): void
    {
        if ($standardName !== null) {
            $duplicate = PanelNormalization::query()
                ->where('normalized_standard_name', $standardName)
                ->when($normalization, function ($query) use ($normalization) {
                    $query->where('id', '<>', $normalization->id);
                })
                ->exists();
            if ($duplicate) {
                throw ValidationException::withMessages(['standard_name' => 'Tên PANEL chuẩn đã tồn tại.']);
            }
        }

        $normalizedAliases = array_column($aliases, 'normalized_alias');
        $duplicate = PanelNormalizationAlias::query()
            ->whereIn('normalized_alias', $normalizedAliases)
            ->when($normalization, function ($query) use ($normalization) {
                $query->where('panel_normalization_id', '<>', $normalization->id);
            })
            ->first();
        if ($duplicate) {
            throw ValidationException::withMessages([
                'aliases' => 'Cách viết "' . $duplicate->alias . '" đã thuộc quy tắc PANEL khác.',
            ]);
        }
    }
}
