<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: "1.0.0",
    title: "Invoice Winner API",
    description: "API Documentation"
)]

#[OA\Server(
    url: "http://127.0.0.1:8000",
    description: "Local Server"
)]

class OpenApiSpec
{
}