<?php

return [
    'mailgun' => [
        'domain'   => env('MAILGUN_DOMAIN'),
        'secret'   => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme'   => 'https',
    ],
    'service_b' => [
        'base_url' => env('SERVICE_B_BASE_URL', 'http://service-penawaran:80'),
        'api_key'  => env('SERVICE_B_API_KEY', 'rahasia-bids-123'),
        'timeout'  => 10,
    ],
    'service_a' => [
        'base_url' => env('SERVICE_A_BASE_URL', 'http://localhost:8002'),
        'api_key'  => env('SERVICE_A_API_KEY', ''),
    ],
    'service_d' => [
        'base_url' => env('SERVICE_D_BASE_URL', 'http://localhost:8003'),
        'api_key'  => env('SERVICE_D_API_KEY', ''),
    ],
    'iae' => [
        'base_url' => env('IAE_BASE_URL', 'https://iae-sso.virtualfri.id'),
        'api_key'  => env('IAE_API_KEY', 'KEY-MHS-71'),
        'team_id'  => env('IAE_TEAM_ID', 'TEAM-02'),
    ],
];
