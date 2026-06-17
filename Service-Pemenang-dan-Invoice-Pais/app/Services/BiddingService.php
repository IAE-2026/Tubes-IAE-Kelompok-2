<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

/**
 * BiddingService
 *
 * Service layer untuk integrasi dengan Service B (Bidding/Penawaran).
 * Mengambil data highest bid untuk menentukan pemenang lelang.
 *
 * Alur Integrasi:
 *   Service C (Invoice-Winner) → HTTP Request → Service B (Bidding)
 *   Service B akan mengembalikan data highest bid beserta info bidder dan item.
 */
class BiddingService
{
    private Client $client;
    private string $baseUrl;
    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.service_b.base_url', env('BIDDING_SERVICE_URL', 'http://service-penawaran:80'));
        $this->apiKey  = config('services.service_b.api_key', env('BIDDING_SERVICE_KEY', 'rahasia-bids-123'));

        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout'  => 10.0,
            'headers'  => [
                'X-IAE-KEY'    => $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ],
        ]);
    }

    /**
     * Mengambil highest bid dari Service B berdasarkan auction_id.
     *
     * @param  string $auctionId  ID lelang
     * @return array|null         Data highest bid atau null jika tidak ditemukan
     *
     * Expected response dari Service B:
     * {
     *   "status": "success",
     *   "data": {
     *     "bid_id": "BID-001",
     *     "auction_id": "AUC-001",
     *     "item_id": "ITM-001",
     *     "bidder_id": "USR-001",
     *     "bidder_name": "Budi Santoso",
     *     "bidder_email": "budi@email.com",
     *     "item_name": "Laptop Asus ROG",
     *     "amount": 15000000,
     *     "starting_price": 10000000,
     *     "auction_status": "ended",
     *     "auction_ended_at": "2024-01-15T12:00:00Z"
     *   }
     * }
     */
    public function getHighestBid(string $auctionId): ?array
    {
        try {
            $response = $this->client->get("/api/v1/bids/highest/{$auctionId}");
            $body     = json_decode($response->getBody()->getContents(), true);

            if (isset($body['status']) && $body['status'] === 'success') {
                Log::info("[BiddingService] Successfully fetched highest bid for auction: {$auctionId}", [
                    'bid_data' => $body['data'],
                ]);
                return $body['data'];
            }

            Log::warning("[BiddingService] Unexpected response from Service B", [
                'auction_id' => $auctionId,
                'response'   => $body,
            ]);
            return null;

        } catch (RequestException $e) {
            Log::error("[BiddingService] Failed to fetch highest bid from Service B", [
                'auction_id' => $auctionId,
                'error'      => $e->getMessage(),
                'status'     => $e->hasResponse() ? $e->getResponse()->getStatusCode() : null,
            ]);
            return null;
        }
    }

    /**
     * Cek apakah lelang sudah berakhir di Service B.
     *
     * @param  string $auctionId
     * @return bool
     */
    public function isAuctionEnded(string $auctionId): bool
    {
        try {
            $response = $this->client->get("/api/v1/auctions/{$auctionId}");
            $body     = json_decode($response->getBody()->getContents(), true);

            return isset($body['data']['status']) && $body['data']['status'] === 'ended';

        } catch (RequestException $e) {
            Log::error("[BiddingService] Failed to check auction status", [
                'auction_id' => $auctionId,
                'error'      => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Simulasi data untuk development/testing tanpa Service B aktif.
     * Gunakan ini jika Service B belum tersedia.
     *
     * @param  string $auctionId
     * @return array
     */
    public function getMockHighestBid(string $auctionId): array
    {
        return [
            'bid_id'           => 'BID-MOCK-001',
            'auction_id'       => $auctionId,
            'item_id'          => 'ITM-MOCK-001',
            'bidder_id'        => 'USR-MOCK-001',
            'bidder_name'      => 'Budi Santoso (Mock)',
            'bidder_email'     => 'budi.mock@email.com',
            'item_name'        => 'Barang Lelang Mock - ' . $auctionId,
            'amount'           => 15000000.00,
            'starting_price'   => 10000000.00,
            'auction_status'   => 'ended',
            'auction_ended_at' => now()->subHour()->toIso8601String(),
        ];
    }
}
