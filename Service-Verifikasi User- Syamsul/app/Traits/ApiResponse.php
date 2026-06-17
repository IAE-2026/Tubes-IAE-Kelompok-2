<?php

namespace App\Traits;

trait ApiResponse
{
    protected function successResponse($data, $message = 'Success', $code = 200, $meta = null)
    {
        $response = [
            'status' => 'success',
            'message' => $message,
            'data' => $data,
        ];

        if ($meta) {
            $response['meta'] = $meta;
        } else {
            $response['meta'] = [
                'service_name' => 'Verifikasi-Service',
                'api_version' => 'v1'
            ];
        }

        return response()->json($response, $code);
    }

    protected function errorResponse($message = 'Error', $code = 400, $errors = null)
    {
        $response = [
            'status' => 'error',
            'message' => $message,
        ];

        if ($errors) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }
}