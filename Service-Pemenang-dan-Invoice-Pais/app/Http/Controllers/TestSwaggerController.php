<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

class TestSwaggerController extends Controller
{
    #[OA\Get(
        path: "/api/test",
        summary: "Test endpoint",
        tags: ["Test"],
        responses: [
            new OA\Response(
                response: 200,
                description: "Success"
            )
        ]
    )]
    public function test()
    {
        return response()->json([
            'message' => 'Swagger works!'
        ]);
    }
}