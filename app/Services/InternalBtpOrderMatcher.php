<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InternalBtpOrderMatcher
{
    public function find(array $line)
    {
        $schema = Schema::connection('internal');
        if (!$schema->hasTable('internal_btp_production_order_lines')
            || !$schema->hasColumn('internal_btp_production_order_lines', 'ps_number')) {
            return null;
        }

        $psNumber = $this->upper($line['ps_number'] ?? $line['purchase_order'] ?? '');
        if ($psNumber === '') {
            $psNumber = $this->extractNoteValue((string) ($line['note'] ?? ''), '/PS#?:\s*([^-|]+)/iu');
        }
        if ($psNumber === '') {
            return null;
        }

        $criteria = [
            'ps_number' => $psNumber,
            'internal_item_code' => $this->upper($line['internal_item_code'] ?? ''),
            'ma_hh' => $this->upper($line['ma_hh'] ?? $line['ma_sp'] ?? ''),
            'size' => $this->upper($line['size'] ?? ''),
            'color' => $this->upper($line['color'] ?? ''),
            'logo_color' => $this->upper($line['logo_color'] ?? ''),
            'side' => $this->upper($line['side'] ?? ''),
            'ordered_quantity' => (float) ($line['ordered_quantity'] ?? 0),
        ];

        if ($criteria['logo_color'] === '') {
            $criteria['logo_color'] = $this->extractNoteValue((string) ($line['note'] ?? ''), '/M(?:à|a)u\s*in:\s*([^-|]+)/iu');
        }
        if ($criteria['side'] === '') {
            $criteria['side'] = $this->extractNoteValue((string) ($line['note'] ?? ''), '/M(?:ặ|a)t:\s*([^-|]+)/iu');
        }

        return $this->query($criteria, true) ?: $this->query($criteria, false);
    }

    private function query(array $criteria, bool $strict)
    {
        $schema = Schema::connection('internal');
        $query = DB::connection('internal')->table('internal_btp_production_order_lines as l')
            ->join('internal_btp_production_orders as o', 'o.id', '=', 'l.btp_order_id')
            ->whereRaw("UPPER(TRIM(COALESCE(l.ps_number, ''))) = ?", [$criteria['ps_number']]);

        if ($criteria['internal_item_code'] !== '') {
            $query->whereRaw("UPPER(TRIM(COALESCE(l.internal_item_code, ''))) = ?", [$criteria['internal_item_code']]);
        } elseif ($criteria['ma_hh'] !== '') {
            $query->whereRaw("UPPER(TRIM(COALESCE(l.ma_hh, ''))) = ?", [$criteria['ma_hh']]);
        }

        foreach (['size', 'color'] as $field) {
            if ($strict || $criteria[$field] !== '') {
                $query->whereRaw("UPPER(TRIM(COALESCE(l.$field, ''))) = ?", [$criteria[$field]]);
            }
        }

        if ($schema->hasColumn('internal_btp_production_order_lines', 'logo_color')
            && ($strict || $criteria['logo_color'] !== '')) {
            $query->whereRaw("UPPER(TRIM(COALESCE(l.logo_color, ''))) = ?", [$criteria['logo_color']]);
        }

        if ($strict || $criteria['side'] !== '') {
            $query->whereRaw("UPPER(TRIM(COALESCE(l.side, ''))) = ?", [$criteria['side']]);
        }

        if ($criteria['ordered_quantity'] > 0) {
            $query->whereRaw('ABS(COALESCE(l.ordered_quantity, 0) - ?) < 0.0001', [$criteria['ordered_quantity']]);
        }

        return $query
            ->orderByDesc('o.id')
            ->select('o.id', 'o.btp_order_code', 'o.status', 'o.issue_code', 'l.id as line_id')
            ->first();
    }

    private function upper($value): string
    {
        return mb_strtoupper(trim((string) $value));
    }

    private function extractNoteValue(string $note, string $pattern): string
    {
        if (!preg_match($pattern, $note, $matches)) {
            return '';
        }

        return $this->upper($matches[1] ?? '');
    }
}
