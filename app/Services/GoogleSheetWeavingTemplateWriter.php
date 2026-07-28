<?php

namespace App\Services;

use Google\Client;
use Google\Service\Sheets;
use Google\Service\Sheets\BatchUpdateValuesRequest;
use Google\Service\Sheets\ValueRange;
use RuntimeException;

class GoogleSheetWeavingTemplateWriter
{
    public const SHEET_NAME = 'LENH_DET';

    public function isConfigured(): bool
    {
        $path = $this->credentialsPath();

        return (bool) config('services.google_sheets.write_enabled')
            && $path !== ''
            && is_file($path);
    }

    public function write(string $spreadsheetId, array $plan): string
    {
        $this->verifyTemplate($spreadsheetId);

        $data = collect($this->buildRanges($plan))
            ->map(fn (array $item) => new ValueRange([
                'range' => "'" . self::SHEET_NAME . "'!" . $item['range'],
                'values' => $item['values'],
            ]))
            ->values()
            ->all();

        $service = new Sheets($this->client());
        $service->spreadsheets_values->batchUpdate(
            $spreadsheetId,
            new BatchUpdateValuesRequest([
                'valueInputOption' => 'USER_ENTERED',
                'data' => $data,
            ])
        );

        return sprintf(
            'https://docs.google.com/spreadsheets/d/%s/edit#gid=1622760642',
            rawurlencode($spreadsheetId)
        );
    }

    public function verifyTemplate(string $spreadsheetId): void
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Chưa cấu hình quyền ghi Google Sheet bằng service account.');
        }

        $service = new Sheets($this->client());
        $response = $service->spreadsheets_values->get(
            $spreadsheetId,
            "'" . self::SHEET_NAME . "'!A1:K5"
        );
        $values = $response->getValues() ?: [];
        if (trim((string) ($values[0][0] ?? '')) !== 'LỆNH DỆT') {
            throw new RuntimeException('Tab LENH_DET không đúng mẫu hoặc ô A1 không phải LỆNH DỆT.');
        }
    }

    public function buildRanges(array $plan): array
    {
        $order = (array) ($plan['order'] ?? []);
        $sourceItem = (array) (($plan['source_items'][0] ?? []));
        $metadata = array_merge(
            (array) ($sourceItem['metadata'] ?? []),
            (array) ($order['metadata'] ?? [])
        );
        $operations = (array) ($metadata['operations'] ?? []);
        $summaryLines = collect((array) ($plan['data'] ?? []))
            ->keyBy(fn ($line) => mb_strtoupper(trim((string) ($line['material_code'] ?? ''))));
        $sourceMaterials = array_values((array) ($sourceItem['materials'] ?? []));
        $lines = $sourceMaterials ?: array_values((array) ($plan['data'] ?? []));
        $quantity = (float) ($order['planned_quantity'] ?? $sourceItem['order_quantity'] ?? 0);

        // Row 13 is reserved for the warp-yarn total in I13:K13.
        $bomValues = array_fill(0, 7, array_fill(0, 6, ''));
        foreach (array_slice($lines, 0, 7) as $index => $line) {
            $line = (array) $line;
            $summaryLine = (array) ($summaryLines->get(mb_strtoupper(trim((string) ($line['material_code'] ?? '')))) ?? []);
            $bomValues[$index] = [
                $line['type'] ?? '',
                $line['material_code'] ?? '',
                $this->firstText(
                    $line['first_location'] ?? '',
                    $summaryLine['first_location'] ?? '',
                    $line['catalog_shelf_code'] ?? '',
                    $summaryLine['catalog_shelf_code'] ?? '',
                    $line['shelf_hint'] ?? ''
                ),
                $this->firstText(
                    $line['catalog_name'] ?? '',
                    $summaryLine['catalog_name'] ?? '',
                    $line['material_name'] ?? ''
                ),
                $this->number($line['consumption_per_unit'] ?? 0),
                $this->number(
                    ((float) ($line['total_grams'] ?? 0)) > 0
                        ? $line['total_grams']
                        : ($line['required_quantity_raw'] ?? $line['required_quantity'] ?? 0)
                ),
            ];
        }

        $imageUrl = $this->firstText(
            $order['image_url'] ?? '',
            $sourceItem['image_url'] ?? '',
            $metadata['image_url'] ?? ''
        );
        $imageFormula = preg_match('/^https:\/\//i', $imageUrl)
            ? '=IMAGE("' . str_replace('"', '""', $imageUrl) . '")'
            : '';
        $orderCode = trim((string) ($order['production_order'] ?? $order['order_code'] ?? ''));
        $qrFormula = $orderCode !== ''
            ? '=IMAGE("https://quickchart.io/qr?size=120&text=' . rawurlencode($orderCode) . '")'
            : '';

        return [
            ['range' => 'B2', 'values' => [[$order['customer'] ?? $sourceItem['customer'] ?? '']]],
            ['range' => 'H2', 'values' => [[$orderCode]]],
            ['range' => 'J2', 'values' => [[$qrFormula]]],
            ['range' => 'B3', 'values' => [[$order['po_number'] ?? $sourceItem['po_number'] ?? '']]],
            ['range' => 'H3', 'values' => [[$order['item_code'] ?? $sourceItem['item_code'] ?? '']]],
            ['range' => 'B4', 'values' => [[$metadata['job_number'] ?? '']]],
            ['range' => 'D4', 'values' => [[$this->dateText($order['order_date'] ?? null)]]],
            ['range' => 'G4', 'values' => [[$order['design_code'] ?? $sourceItem['design_code'] ?? '']]],
            ['range' => 'J4', 'values' => [[$this->dateText($order['due_date'] ?? null)]]],
            ['range' => 'B5:B13', 'values' => [
                [$this->firstText($metadata['label_name'] ?? '', $sourceItem['item_name'] ?? '')],
                [$this->operation($operations, 'UI KEO')],
                [$this->operation($operations, 'LOOP')],
                [$this->operation($operations, 'PHAN TREN')],
                [$this->operation($operations, 'PHAN DUOI')],
                [$this->firstText($metadata['length'] ?? '', $this->operation($operations, 'CHIEU DAI'))],
                [$this->firstText($metadata['finished_size'] ?? '', $this->operation($operations, 'HOAN CHINH'))],
                [$this->firstText($metadata['box_code'] ?? '', $this->operation($operations, 'MA SO HOP'))],
                [$this->firstText($metadata['quantity_per_box'] ?? '', $this->operation($operations, 'SO LUONG/HOP'))],
            ]],
            ['range' => 'F6:K12', 'values' => $bomValues],
            ['range' => 'F13:H13', 'values' => [['', '', '']]],
            ['range' => 'K13', 'values' => [[$this->numericOrBlank($metadata['warp_grams'] ?? '')]]],
            ['range' => 'A15:K16', 'values' => [
                [
                    $metadata['pick'] ?? '',
                    $metadata['density'] ?? '',
                    $metadata['machine'] ?? '',
                    $metadata['roll_machine_small'] ?? '',
                    $this->numericOrBlank($metadata['roll_count_small'] ?? $metadata['roll_count'] ?? ''),
                    '',
                    $this->numericOrBlank($metadata['quantity_plus_10'] ?? ''),
                    $metadata['row_machine_small'] ?? '',
                    $this->numericOrBlank($metadata['row_count_plus_10'] ?? ''),
                    '',
                    $metadata['shift'] ?? '',
                ],
                [
                    '',
                    '',
                    '',
                    $metadata['roll_machine_large'] ?? '',
                    $this->numericOrBlank($metadata['roll_count_large'] ?? ''),
                    '',
                    '',
                    $metadata['row_machine_large'] ?? '',
                    $this->numericOrBlank($metadata['row_count_plus_10_large'] ?? ''),
                    '',
                    $metadata['shift_large'] ?? '',
                ],
            ]],
            ['range' => 'A20', 'values' => [[$metadata['file_name'] ?? '']]],
            ['range' => 'C20', 'values' => [[$metadata['usb_small'] ?? '']]],
            ['range' => 'E20', 'values' => [[$metadata['usb_large'] ?? '']]],
            ['range' => 'G19', 'values' => [[$imageFormula]]],
            ['range' => 'A33:D33', 'values' => [[
                $order['item_code'] ?? $sourceItem['item_code'] ?? '',
                $this->number($quantity),
                '',
                $this->numericOrBlank($metadata['row_count'] ?? ''),
            ]]],
        ];
    }

    private function operation(array $operations, string $wanted): string
    {
        $wanted = $this->normalizeKey($wanted);
        foreach ($operations as $key => $value) {
            if ($this->normalizeKey((string) $key) === $wanted) {
                return trim((string) $value);
            }
        }

        return '';
    }

    private function normalizeKey(string $value): string
    {
        $ascii = \Illuminate\Support\Str::ascii(mb_strtoupper(trim($value)));

        return trim(preg_replace('/[^A-Z0-9]+/', ' ', $ascii));
    }

    private function dateText($value): string
    {
        if (!$value) {
            return '';
        }

        try {
            return \Carbon\Carbon::parse($value)->format('d/m/Y');
        } catch (\Throwable $e) {
            return trim((string) $value);
        }
    }

    private function number($value)
    {
        return is_numeric($value) ? round((float) $value, 6) : 0;
    }

    private function numericOrBlank($value)
    {
        if ($value === '' || $value === null || !is_numeric($value)) {
            return '';
        }

        return $this->number($value);
    }

    private function firstText(...$values): string
    {
        foreach ($values as $value) {
            $text = trim((string) $value);
            if ($text !== '') {
                return $text;
            }
        }

        return '';
    }

    private function client(): Client
    {
        $client = new Client();
        $client->setApplicationName('TTV Internal Warehouse');
        $client->setAuthConfig($this->credentialsPath());
        $client->setScopes([Sheets::SPREADSHEETS]);

        return $client;
    }

    private function credentialsPath(): string
    {
        $path = trim((string) config('services.google_sheets.credentials_path'));
        if ($path === '') {
            return '';
        }

        if (preg_match('/^[A-Za-z]:[\\\\\/]/', $path) || str_starts_with($path, '/')) {
            return $path;
        }

        return base_path($path);
    }
}
