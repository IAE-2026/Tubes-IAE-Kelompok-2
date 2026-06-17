<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;


trait BaseApiResponse
{
    /**
     * Standard Success Response
     *
     * @param  mixed  $data
     * @param  string $message
     * @param  int    $statusCode
     * @return JsonResponse
     */
    protected function successResponse(
        mixed $data = null,
        string $message = 'Data retrieved successfully',
        int $statusCode = 200
    ): JsonResponse {
        return response()->json([
            'status'  => 'success',
            'message' => $message,
            'data'    => $data,
            'meta'    => [
                'service_name' => 'Invoice-Winner-Service',
                'api_version'  => 'v1',
            ],
        ], $statusCode);
    }

    /**
     * Success Response with Pagination
     *
     * @param  mixed  $paginator  LengthAwarePaginator instance
     * @param  string $message
     * @return JsonResponse
     */
    protected function paginatedResponse(mixed $paginator, string $message = 'Data retrieved successfully'): JsonResponse
    {
        return response()->json([
            'status'  => 'success',
            'message' => $message,
            'data'    => $paginator->items(),
            'meta'    => [
                'service_name' => 'Invoice-Winner-Service',
                'api_version'  => 'v1',
                'pagination'   => [
                    'total'        => $paginator->total(),
                    'per_page'     => $paginator->perPage(),
                    'current_page' => $paginator->currentPage(),
                    'last_page'    => $paginator->lastPage(),
                    'from'         => $paginator->firstItem(),
                    'to'           => $paginator->lastItem(),
                ],
            ],
        ], 200);
    }

    /**
     * Standard Error Response
     *
     * @param  string     $message
     * @param  mixed|null $errors
     * @param  int        $statusCode
     * @return JsonResponse
     */
    protected function errorResponse(
        string $message = 'An error occurred',
        mixed $errors = null,
        int $statusCode = 400
    ): JsonResponse {
        return response()->json([
            'status'  => 'error',
            'message' => $message,
            'errors'  => $errors,
        ], $statusCode);
    }

    /**
     * 404 Not Found Response
     */
    protected function notFoundResponse(string $resource = 'Resource'): JsonResponse
    {
        return $this->errorResponse("{$resource} tidak ditemukan.", null, 404);
    }

    /**
     * 201 Created Response
     */
    protected function createdResponse(mixed $data, string $message = 'Data berhasil dibuat.'): JsonResponse
    {
        return $this->successResponse($data, $message, 201);
    }
}
