<?php

return [
    'spreadsheet_id' => env(
        'INTERNAL_ORDERS_SPREADSHEET_ID',
        '1z5-xnUOABUHh-nrQt8WTlQ8p6kzQVWjZYVZNUQHWcEM'
    ),
    'sheet_name' => env('INTERNAL_ORDERS_SHEET_NAME', 'DON_HANG_TACH'),
    'sheet_columns' => env('INTERNAL_ORDERS_SHEET_COLUMNS', 'A:AZ'),
];
