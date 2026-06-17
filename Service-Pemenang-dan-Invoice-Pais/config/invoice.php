<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Invoice Configuration
    |--------------------------------------------------------------------------
    |
    | Konfigurasi untuk pembuatan invoice otomatis.
    |
    */

    // Prefix nomor invoice (contoh: INV-2024-000001)
    'prefix' => env('INVOICE_PREFIX', 'INV'),

    // Jumlah hari tenggat pembayaran setelah invoice diterbitkan
    'due_days' => (int) env('INVOICE_DUE_DAYS', 7),

    // Tarif PPN (11%)
    'tax_rate' => 0.11,

    // Biaya administrasi (2%)
    'admin_fee_rate' => 0.02,
];
