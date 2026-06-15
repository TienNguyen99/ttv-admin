<?php

namespace App\Services;

use App\Models\InternalAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class InternalAudit
{
    public function record(
        string $action,
        string $entityType,
        ?int $entityId = null,
        ?string $entityCode = null,
        array $payload = [],
        ?Request $request = null
    ): void {
        InternalAuditLog::query()->create([
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'entity_code' => $entityCode,
            'payload' => $payload ?: null,
            'ip_address' => $request ? $request->ip() : null,
            'created_at' => now(),
        ]);
    }

    public function model(string $action, Model $model, array $payload = [], ?Request $request = null): void
    {
        $code = $model->issue_code
            ?? $model->receipt_code
            ?? $model->package_code
            ?? $model->location_code
            ?? null;

        $this->record(
            $action,
            class_basename($model),
            (int) $model->getKey(),
            $code ? (string) $code : null,
            $payload,
            $request
        );
    }
}
