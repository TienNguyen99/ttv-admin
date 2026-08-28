<?php

namespace App\Services;

use App\Models\InternalMaterialIssue;

class InternalIssueIdempotencyService
{
    public function key(array $data): string
    {
        return trim((string) ($data['idempotency_key'] ?? ''));
    }

    public function fingerprint(array $data): string
    {
        unset($data['idempotency_key'], $data['allow_negative']);
        $normalized = $this->sortRecursively($data);

        return hash('sha256', json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function existing(string $key): ?InternalMaterialIssue
    {
        if ($key === '') {
            return null;
        }

        return InternalMaterialIssue::query()
            ->where('idempotency_key', $key)
            ->with('lines')
            ->first();
    }

    public function matches(InternalMaterialIssue $issue, string $fingerprint): bool
    {
        $stored = trim((string) $issue->request_fingerprint);

        return $stored !== '' && hash_equals($stored, $fingerprint);
    }

    private function sortRecursively($value)
    {
        if (!is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $item) {
            $value[$key] = $this->sortRecursively($item);
        }
        if ($this->isAssociative($value)) {
            ksort($value);
        }

        return $value;
    }

    private function isAssociative(array $value): bool
    {
        if ($value === []) {
            return false;
        }

        return array_keys($value) !== range(0, count($value) - 1);
    }
}
