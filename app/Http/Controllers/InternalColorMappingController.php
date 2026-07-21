<?php

namespace App\Http\Controllers;

use App\Models\InternalColorMapping;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class InternalColorMappingController extends Controller
{
    public function page()
    {
        return view('client.internal-color-mappings');
    }

    public function index(Request $request)
    {
        $keyword = mb_strtoupper(trim((string) $request->query('keyword', '')));
        $query = InternalColorMapping::query()->where('is_active', true);

        if ($keyword !== '') {
            $like = '%' . addcslashes($keyword, '\\%_') . '%';
            $query->where(function ($inner) use ($like) {
                $inner->whereRaw('UPPER(color_code) LIKE ?', [$like])
                    ->orWhereRaw('UPPER(color_name) LIKE ?', [$like])
                    ->orWhereRaw("UPPER(COALESCE(pantone_code, '')) LIKE ?", [$like]);
            });
        }

        return response()->json([
            'data' => $query->orderBy('color_code')->limit(1000)->get(),
            'summary' => [
                'count' => InternalColorMapping::query()->where('is_active', true)->count(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $request->merge([
            'color_code' => mb_strtoupper(trim((string) $request->input('color_code'))),
            'color_name' => trim((string) $request->input('color_name')),
        ]);

        $id = $request->input('id');
        $data = $request->validate([
            'id' => 'nullable|integer',
            'color_code' => [
                'required',
                'string',
                'max:120',
                Rule::unique('internal.internal_color_mappings', 'color_code')->ignore($id),
            ],
            'color_name' => 'required|string|max:255',
            'hex' => ['required', 'string', 'regex:/^#?[0-9a-fA-F]{6}$/'],
            'pantone_code' => 'nullable|string|max:80',
            'note' => 'nullable|string|max:500',
        ]);

        $payload = [
            'color_code' => mb_strtoupper(trim($data['color_code'])),
            'color_name' => trim($data['color_name']),
            'hex' => '#' . strtolower(ltrim(trim($data['hex']), '#')),
            'pantone_code' => mb_strtoupper(trim((string) ($data['pantone_code'] ?? ''))) ?: null,
            'note' => trim((string) ($data['note'] ?? '')) ?: null,
            'is_active' => true,
        ];

        $row = $id
            ? tap(InternalColorMapping::query()->findOrFail($id))->update($payload)
            : InternalColorMapping::query()->create($payload);

        $this->bumpCacheVersion();

        return response()->json([
            'message' => 'Đã lưu màu nội bộ.',
            'data' => $row->fresh(),
        ]);
    }

    public function destroy(InternalColorMapping $colorMapping)
    {
        $colorMapping->delete();
        $this->bumpCacheVersion();

        return response()->json(['message' => 'Đã xóa màu nội bộ.']);
    }

    private function bumpCacheVersion(): void
    {
        Cache::forever('internal_color_mapping_version', (string) microtime(true));
    }
}
