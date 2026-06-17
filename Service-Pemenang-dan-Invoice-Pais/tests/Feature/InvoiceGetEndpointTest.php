<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Role;
use App\Models\User;
use App\Models\Winner;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InvoiceGetEndpointTest extends TestCase
{
    use RefreshDatabase;

    private string $privateKey;
    private string $publicKey;
    private array $jwks;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles table
        $this->artisan('db:seed', ['--class' => 'RoleSeeder']);

        // Generate a test-only RSA keypair
        $config = [
            "private_key_bits" => 2048,
            "private_key_type" => OPENSSL_KEYTYPE_RSA,
            "config" => "C:\\xampp\\php\\extras\\ssl\\openssl.cnf",
        ];
        $res = openssl_pkey_new($config);
        openssl_pkey_export($res, $privKey, null, $config);
        $this->privateKey = $privKey;

        $pubKeyDetails = openssl_pkey_get_details($res);
        $this->publicKey = $pubKeyDetails['key'];

        // Base64URL encoding helpers
        $n = rtrim(strtr(base64_encode($pubKeyDetails['rsa']['n']), '+/', '-_'), '=');
        $e = rtrim(strtr(base64_encode($pubKeyDetails['rsa']['e']), '+/', '-_'), '=');

        $this->jwks = [
            "keys" => [
                [
                    "kty" => "RSA",
                    "use" => "sig",
                    "alg" => "RS256",
                    "kid" => "test-kid-2026",
                    "n"   => $n,
                    "e"   => $e
                ]
            ]
        ];

        // Mock JWKS endpoint
        Http::fake([
            'https://iae-sso.virtualfri.id/api/v1/auth/jwks' => Http::response($this->jwks, 200),
        ]);

        // Clear JWKS cache in tests
        Cache::forget('sso_jwks');

        // Generate standard token for testing
        $payload = [
            "iss"        => "iae-central-mock",
            "sub"        => "warga07@ktp.iae.id",
            "iat"        => time(),
            "exp"        => time() + 3600,
            "token_type" => "user",
            "profile"    => [
                "name"  => "Galih Mahendra",
                "nim"   => "2026000007",
                "email" => "warga07@ktp.iae.id"
            ]
        ];
        $this->token = JWT::encode($payload, $this->privateKey, 'RS256', 'test-kid-2026');
    }

    public function test_get_invoices_returns_unauthorized_if_no_token()
    {
        $response = $this->getJson('/api/v1/invoices');
        $response->assertStatus(401);
    }

    public function test_get_invoices_returns_paginated_list()
    {
        // Create a winner and an invoice
        $winner = Winner::create([
            'auction_id'        => 'AUC-101',
            'item_id'           => 'ITM-101',
            'bidder_id'         => 'BID-101',
            'bidder_name'       => 'Galih Mahendra',
            'bidder_email'      => 'warga07@ktp.iae.id',
            'item_name'         => 'Test Item 1',
            'winning_bid'       => 150000,
            'status'            => 'invoiced',
            'auction_ended_at'  => now(),
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-2026-000001',
            'winner_id'      => $winner->id,
            'auction_id'     => $winner->auction_id,
            'item_id'        => $winner->item_id,
            'bidder_id'      => $winner->bidder_id,
            'bidder_name'    => $winner->bidder_name,
            'bidder_email'   => $winner->bidder_email,
            'item_name'      => $winner->item_name,
            'subtotal'       => 150000,
            'tax_amount'     => 16500,
            'admin_fee'      => 3000,
            'total_amount'   => 169500,
            'status'         => 'unpaid',
            'issued_at'      => now(),
            'due_date'       => now()->addDays(7),
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/v1/invoices');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', 'Daftar invoice berhasil diambil.')
            ->assertJsonStructure([
                'status',
                'message',
                'data',
                'meta' => [
                    'service_name',
                    'api_version',
                    'pagination' => [
                        'total',
                        'per_page',
                        'current_page',
                        'last_page'
                    ]
                ]
            ]);

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('INV-2026-000001', $response->json('data.0.invoice_number'));
    }

    public function test_get_invoices_filtering_by_status()
    {
        // Setup two winners and two invoices (one paid, one unpaid)
        $w1 = Winner::create([
            'auction_id' => 'AUC-201', 'item_id' => 'ITM-201', 'bidder_id' => 'BID-201',
            'bidder_name' => 'User One', 'bidder_email' => 'user1@email.com', 'item_name' => 'Item 1',
            'winning_bid' => 100000, 'status' => 'invoiced', 'auction_ended_at' => now(),
        ]);
        Invoice::create([
            'invoice_number' => 'INV-2026-000002', 'winner_id' => $w1->id, 'auction_id' => $w1->auction_id,
            'item_id' => $w1->item_id, 'bidder_id' => $w1->bidder_id, 'bidder_name' => $w1->bidder_name,
            'bidder_email' => $w1->bidder_email, 'item_name' => $w1->item_name, 'subtotal' => 100000,
            'tax_amount' => 11000, 'admin_fee' => 2000, 'total_amount' => 113000,
            'status' => 'unpaid', 'issued_at' => now(), 'due_date' => now()->addDays(7),
        ]);

        $w2 = Winner::create([
            'auction_id' => 'AUC-202', 'item_id' => 'ITM-202', 'bidder_id' => 'BID-202',
            'bidder_name' => 'User Two', 'bidder_email' => 'user2@email.com', 'item_name' => 'Item 2',
            'winning_bid' => 200000, 'status' => 'paid', 'auction_ended_at' => now(),
        ]);
        Invoice::create([
            'invoice_number' => 'INV-2026-000003', 'winner_id' => $w2->id, 'auction_id' => $w2->auction_id,
            'item_id' => $w2->item_id, 'bidder_id' => $w2->bidder_id, 'bidder_name' => $w2->bidder_name,
            'bidder_email' => $w2->bidder_email, 'item_name' => $w2->item_name, 'subtotal' => 200000,
            'tax_amount' => 22000, 'admin_fee' => 4000, 'total_amount' => 226000,
            'status' => 'paid', 'issued_at' => now(), 'due_date' => now()->addDays(7),
        ]);

        // Filter by paid
        $responsePaid = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/v1/invoices?status=paid');

        $responsePaid->assertStatus(200);
        $this->assertCount(1, $responsePaid->json('data'));
        $this->assertEquals('INV-2026-000003', $responsePaid->json('data.0.invoice_number'));

        // Filter by unpaid
        $responseUnpaid = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/v1/invoices?status=unpaid');

        $responseUnpaid->assertStatus(200);
        $this->assertCount(1, $responseUnpaid->json('data'));
        $this->assertEquals('INV-2026-000002', $responseUnpaid->json('data.0.invoice_number'));
    }

    public function test_get_invoice_by_id_or_number()
    {
        $winner = Winner::create([
            'auction_id'        => 'AUC-301',
            'item_id'           => 'ITM-301',
            'bidder_id'         => 'BID-301',
            'bidder_name'       => 'Galih Mahendra',
            'bidder_email'      => 'warga07@ktp.iae.id',
            'item_name'         => 'Test Item 3',
            'winning_bid'       => 150000,
            'status'            => 'invoiced',
            'auction_ended_at'  => now(),
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-2026-000004',
            'winner_id'      => $winner->id,
            'auction_id'     => $winner->auction_id,
            'item_id'        => $winner->item_id,
            'bidder_id'      => $winner->bidder_id,
            'bidder_name'    => $winner->bidder_name,
            'bidder_email'   => $winner->bidder_email,
            'item_name'      => $winner->item_name,
            'subtotal'       => 150000,
            'tax_amount'     => 16500,
            'admin_fee'      => 3000,
            'total_amount'   => 169500,
            'status'         => 'unpaid',
            'issued_at'      => now(),
            'due_date'       => now()->addDays(7),
        ]);

        // Retrieve by numeric ID
        $responseById = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson("/api/v1/invoices/{$invoice->id}");

        $responseById->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.invoice_number', 'INV-2026-000004')
            ->assertJsonPath('data.winner.auction_id', 'AUC-301');

        // Retrieve by invoice number string
        $responseByNumber = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson("/api/v1/invoices/INV-2026-000004");

        $responseByNumber->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.id', $invoice->id);
    }

    public function test_get_invoice_returns_404_if_not_found()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/v1/invoices/INV-999-NOT-REAL');

        $response->assertStatus(404)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', 'Invoice tidak ditemukan.');
    }
}
