<?php

namespace App\Services;

use App\Models\InternalCustomer;
use App\Models\InternalProductionOrder;

class InternalCustomerCatalogSync
{
    public function syncFromProductionOrders(): array
    {
        $customers = [];

        InternalProductionOrder::query()
            ->where('is_active', true)
            ->whereNotNull('customer')
            ->where('customer', '<>', '')
            ->select(['id', 'production_order', 'customer', 'received_date'])
            ->orderBy('id')
            ->chunkById(500, function ($orders) use (&$customers) {
                foreach ($orders as $order) {
                    $name = $this->cleanName($order->customer);
                    if ($name === '') {
                        continue;
                    }

                    $key = self::key($name);
                    if (!isset($customers[$key])) {
                        $customers[$key] = [
                            'name' => $name,
                            'orders' => [],
                            'last_order_date' => null,
                        ];
                    }

                    $orderCode = trim((string) $order->production_order);
                    if ($orderCode !== '') {
                        $customers[$key]['orders'][mb_strtoupper($orderCode)] = true;
                    }
                    $receivedDate = $order->received_date ? $order->received_date->format('Y-m-d') : null;
                    if ($receivedDate && (!$customers[$key]['last_order_date'] || $receivedDate > $customers[$key]['last_order_date'])) {
                        $customers[$key]['last_order_date'] = $receivedDate;
                    }
                }
            });

        $created = 0;
        $updated = 0;
        $unchanged = 0;
        foreach ($customers as $key => $data) {
            $customer = InternalCustomer::query()->firstOrNew(['customer_key' => $key]);
            $isNew = !$customer->exists;
            if ($isNew) {
                $customer->customer_group = 'Chưa phân loại';
                $customer->source = 'production_order';
                $customer->is_active = true;
            }
            $customer->name = $data['name'];
            $customer->order_count = count($data['orders']);
            $customer->last_order_date = $data['last_order_date'];
            if ($isNew || $customer->isDirty()) {
                $customer->save();
                $isNew ? $created++ : $updated++;
            } else {
                $unchanged++;
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'unchanged' => $unchanged,
            'total' => count($customers),
        ];
    }

    public static function normalize($value): string
    {
        return mb_strtoupper(preg_replace('/\s+/u', ' ', trim((string) $value)));
    }

    public static function key($value): string
    {
        return hash('sha256', self::normalize($value));
    }

    private function cleanName($value): string
    {
        return preg_replace('/\s+/u', ' ', trim((string) $value));
    }
}
