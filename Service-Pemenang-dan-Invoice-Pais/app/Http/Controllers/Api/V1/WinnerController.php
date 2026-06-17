<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Winner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Winners",
 *     description="Endpoint untuk mengelola data pemenang lelang"
 * )
 */
class WinnerController extends Controller
{
    use BaseApiResponse;

    /**
     * @OA\Get(
     *     path="/api/v1/winners",
     *     summary="Daftar semua pemenang lelang",
     *     description="Menampilkan daftar pemenang lelang dengan pagination. Bisa difilter berdasarkan status.",
     *     operationId="getWinners",
     *     tags={"Winners"},
     *     security={{"ApiKeyAuth": {}}},
     *
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter berdasarkan status: pending, invoiced, paid, cancelled",
     *         required=false,
     *         @OA\Schema(type="string", enum={"pending", "invoiced", "paid", "cancelled"})
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Jumlah data per halaman (default: 10)",
     *         required=false,
     *         @OA\Schema(type="integer", default=10)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Daftar pemenang berhasil diambil",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Data retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/Winner")
     *             ),
     *             @OA\Property(ref="#/components/schemas/MetaResponse")
     *         )
     *     ),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthorized"),
     *     @OA\Response(response=403, ref="#/components/responses/Forbidden")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = Winner::with('invoice')
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->latest();

        $perPage = min($request->get('per_page', 10), 100);
        $winners = $query->paginate($perPage);

        return $this->paginatedResponse($winners, 'Daftar pemenang lelang berhasil diambil.');
    }

    /**
     * @OA\Get(
     *     path="/api/v1/winners/{id}",
     *     summary="Detail pemenang berdasarkan ID",
     *     description="Menampilkan detail pemenang lelang beserta invoice yang terkait.",
     *     operationId="getWinnerById",
     *     tags={"Winners"},
     *     security={{"ApiKeyAuth": {}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID pemenang",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Detail pemenang berhasil diambil",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Data retrieved successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/WinnerDetail"),
     *             @OA\Property(ref="#/components/schemas/MetaResponse")
     *         )
     *     ),
     *     @OA\Response(response=404, ref="#/components/responses/NotFound"),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthorized")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $winner = Winner::with('invoice')->find($id);

        if (!$winner) {
            return $this->notFoundResponse('Pemenang');
        }

        return $this->successResponse($winner, 'Detail pemenang lelang berhasil diambil.');
    }
}
