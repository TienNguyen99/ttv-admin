<?php

return [
    'enabled' => env('INTERNAL_GOOGLE_SYNC_ENABLED', true),
    'timezone' => env('INTERNAL_GOOGLE_SYNC_TIMEZONE', 'Asia/Ho_Chi_Minh'),
    'operational_lock_seconds' => (int) env('INTERNAL_GOOGLE_SYNC_OPERATIONAL_LOCK', 110),
    'reference_lock_seconds' => (int) env('INTERNAL_GOOGLE_SYNC_REFERENCE_LOCK', 900),
];
