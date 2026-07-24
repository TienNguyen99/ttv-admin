<?php

namespace App\Services;

use Google\Client;
use Google\Service\Sheets;
use Google\Service\Sheets\BatchUpdateValuesRequest;
use Google\Service\Sheets\ValueRange;
use Illuminate\Support\Str;
use RuntimeException;

class GoogleSheetCatalogWriter
{
    public function isConfigured(): bool
    {
        $path = $this->credentialsPath();
        return (bool) config('services.google_sheets.write_enabled') && $path !== '' && is_file($path);
    }

    public function writeItemCodes(string $spreadsheetId, string $sheetName, array $changes): void
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Chưa cấu hình quyền ghi Google Sheet bằng service account.');
        }
        if (!$changes) {
            return;
        }

        $service = new Sheets($this->client());
        $column = $this->findItemCodeColumn($service, $spreadsheetId, $sheetName);
        $data = array_map(function ($change) use ($sheetName, $column) {
            return new ValueRange([
                'range' => sprintf("'%s'!%s%d", str_replace("'", "''", $sheetName), $column, $change['source_row']),
                'values' => [[$change['new_code']]],
            ]);
        }, $changes);

        $request = new BatchUpdateValuesRequest([
            'valueInputOption' => 'RAW',
            'data' => $data,
        ]);
        $service->spreadsheets_values->batchUpdate($spreadsheetId, $request);
    }

    public function writeRowFields(string $spreadsheetId, string $sheetName, int $sourceRow, array $fields): void
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Chưa cấu hình quyền ghi Google Sheet bằng service account.');
        }
        if ($sourceRow < 2 || !$fields) {
            throw new RuntimeException('Dòng nguồn hoặc dữ liệu cập nhật không hợp lệ.');
        }

        $service = new Sheets($this->client());
        $columns = $this->headerColumns($service, $spreadsheetId, $sheetName);
        $data = [];
        foreach ($fields as $header => $value) {
            $normalized = $this->normalizeHeader($header);
            if (!isset($columns[$normalized])) {
                throw new RuntimeException("Không tìm thấy cột {$header} trong tab {$sheetName}.");
            }
            $data[] = new ValueRange([
                'range' => sprintf("'%s'!%s%d", str_replace("'", "''", $sheetName), $columns[$normalized], $sourceRow),
                'values' => [[$value]],
            ]);
        }

        $service->spreadsheets_values->batchUpdate($spreadsheetId, new BatchUpdateValuesRequest([
            'valueInputOption' => 'RAW',
            'data' => $data,
        ]));
    }

    public function appendRows(string $spreadsheetId, string $sheetName, array $rows): array
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Chưa cấu hình quyền ghi Google Sheet bằng service account.');
        }
        if (!$rows) {
            return [];
        }

        $service = new Sheets($this->client());
        $columns = $this->headerColumns($service, $spreadsheetId, $sheetName);
        $columnIndexes = [];
        foreach ($columns as $header => $letter) {
            $columnIndexes[$header] = $this->columnNumber($letter);
        }
        $maxColumn = max($columnIndexes ?: [1]);
        $values = [];

        foreach ($rows as $row) {
            $sheetRow = array_fill(0, $maxColumn, '');
            foreach ($row as $header => $value) {
                $normalized = $this->normalizeHeader($header);
                if (!isset($columnIndexes[$normalized])) {
                    continue;
                }
                $sheetRow[$columnIndexes[$normalized] - 1] = $value;
            }
            $values[] = $sheetRow;
        }

        $escapedSheet = str_replace("'", "''", $sheetName);
        $response = $service->spreadsheets_values->append(
            $spreadsheetId,
            "'{$escapedSheet}'!A:ZZ",
            new ValueRange(['values' => $values]),
            [
                'valueInputOption' => 'RAW',
                'insertDataOption' => 'INSERT_ROWS',
            ]
        );
        $updatedRange = (string) optional($response->getUpdates())->getUpdatedRange();
        if (!preg_match('/![A-Z]+(\d+):/i', $updatedRange, $matches)) {
            throw new RuntimeException('Google Sheet đã nhận dữ liệu nhưng không trả về vị trí dòng mới.');
        }
        $firstRow = (int) $matches[1];

        return array_map(fn ($offset) => $firstRow + $offset, array_keys($rows));
    }

    private function client(): Client
    {
        $client = new Client();
        $client->setApplicationName('TTV Internal Warehouse');
        $client->setAuthConfig($this->credentialsPath());
        $client->setScopes([Sheets::SPREADSHEETS]);
        return $client;
    }

    private function findItemCodeColumn(Sheets $service, string $spreadsheetId, string $sheetName): string
    {
        $columns = $this->headerColumns($service, $spreadsheetId, $sheetName);
        foreach (['ma hang', 'mahang'] as $header) {
            if (isset($columns[$header])) {
                return $columns[$header];
            }
        }
        throw new RuntimeException('Không tìm thấy cột MÃ HÀNG trong tab DANH MỤC.');
    }

    private function headerColumns(Sheets $service, string $spreadsheetId, string $sheetName): array
    {
        $escapedSheet = str_replace("'", "''", $sheetName);
        $response = $service->spreadsheets_values->get($spreadsheetId, "'{$escapedSheet}'!1:5");
        $columns = [];
        foreach ($response->getValues() ?: [] as $row) {
            foreach ($row as $index => $header) {
                $normalized = $this->normalizeHeader($header);
                if ($normalized !== '' && !isset($columns[$normalized])) {
                    $columns[$normalized] = $this->columnLetter($index + 1);
                }
            }
        }
        return $columns;
    }

    private function normalizeHeader($value): string
    {
        return trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9]+/', ' ', Str::ascii(mb_strtolower(trim((string) $value))))));
    }

    private function columnLetter(int $number): string
    {
        $letters = '';
        while ($number > 0) {
            $number--;
            $letters = chr(65 + ($number % 26)) . $letters;
            $number = intdiv($number, 26);
        }
        return $letters;
    }

    private function columnNumber(string $letters): int
    {
        $number = 0;
        foreach (str_split(strtoupper($letters)) as $letter) {
            $number = ($number * 26) + (ord($letter) - 64);
        }
        return $number;
    }

    private function credentialsPath(): string
    {
        $path = trim((string) config('services.google_sheets.credentials_path'));
        if ($path === '') {
            return '';
        }
        return preg_match('/^[A-Za-z]:[\\\\\/]/', $path) || substr($path, 0, 1) === '/'
            ? $path
            : base_path($path);
    }
}
