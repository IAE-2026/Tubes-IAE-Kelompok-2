<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    description: 'Dokumentasi interaktif untuk Tugas 2 IAE - Service Verifikasi',
    title: 'Service Verifikasi User API'
)]
#[OA\SecurityScheme(
    securityScheme: 'ApiKeyAuth',
    type: 'apiKey',
    in: 'header',
    name: 'X-IAE-KEY'
)]
class SwaggerDef
{
}