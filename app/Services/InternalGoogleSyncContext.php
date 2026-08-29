<?php

namespace App\Services;

use Throwable;

class InternalGoogleSyncContext
{
    private array $errors = [];

    private ?int $lastSourceRow = null;

    public function checkpoint(?int $sourceRow): void
    {
        if ($sourceRow !== null) {
            $this->lastSourceRow = max($this->lastSourceRow ?? 0, $sourceRow);
        }
    }

    public function recordRowError(?int $sourceRow, string $sourceKey, $error, array $rawData = []): void
    {
        $this->checkpoint($sourceRow);
        $message = $error instanceof Throwable ? $error->getMessage() : (string) $error;

        $this->errors[] = [
            'source_row' => $sourceRow,
            'source_key' => mb_substr(trim($sourceKey), 0, 255),
            'message' => mb_substr($message, 0, 4000),
            'raw_data' => $rawData ?: null,
        ];
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function failedCount(): int
    {
        return count($this->errors);
    }

    public function lastSourceRow(): ?int
    {
        return $this->lastSourceRow;
    }
}
