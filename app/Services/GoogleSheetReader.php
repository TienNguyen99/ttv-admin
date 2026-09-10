<?php

namespace App\Services;

use Google\Client;
use Google\Service\Sheets;
use RuntimeException;

class GoogleSheetReader
{
    public function isConfigured(): bool
    {
        $path = $this->credentialsPath();

        return $path !== '' && is_file($path);
    }

    public function serviceAccountEmail(): string
    {
        $path = $this->credentialsPath();
        if ($path === '' || !is_file($path)) {
            return '';
        }

        $credentials = json_decode((string) file_get_contents($path), true);

        return trim((string) ($credentials['client_email'] ?? ''));
    }

    public function values(string $spreadsheetId, string $sheetName, string $columns = 'A:AZ'): array
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Chưa cấu hình service account để đọc Google Sheet.');
        }

        $escapedSheet = str_replace("'", "''", $sheetName);
        $service = new Sheets($this->client());
        $response = $service->spreadsheets_values->get(
            $spreadsheetId,
            "'{$escapedSheet}'!{$columns}",
            [
                'valueRenderOption' => 'UNFORMATTED_VALUE',
                'dateTimeRenderOption' => 'FORMATTED_STRING',
            ]
        );

        return $response->getValues() ?: [];
    }

    private function client(): Client
    {
        $client = new Client();
        $client->setAuthConfig($this->credentialsPath());
        $client->setScopes([Sheets::SPREADSHEETS_READONLY]);

        return $client;
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
