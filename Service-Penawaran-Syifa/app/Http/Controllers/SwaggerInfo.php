<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    title: "Service Penawaran API",
    version: "1.0.0",
    description: "API untuk manajemen penawaran lelang"
)]

#[OA\SecurityScheme(
    securityScheme: "ApiKeyAuth",
    type: "apiKey",
    in: "header",
    name: "X-IAE-KEY"
)]

class SwaggerInfo
{
}