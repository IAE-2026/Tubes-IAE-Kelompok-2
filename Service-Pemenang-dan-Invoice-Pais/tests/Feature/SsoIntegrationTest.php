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

class SsoIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private string $privateKey;
    private string $publicKey;
    private array $jwks;

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

        // Clear JWKS cache in tests
        Cache::forget('sso_jwks');
    }

    /**
     * Helper to generate signed JWT for testing
     */
    private function generateJwt(array $payload): string
    {
        return JWT::encode($payload, $this->privateKey, 'RS256', 'test-kid-2026');
    }

    public function test_sso_middleware_authenticates_valid_jwt_and_maps_user_role()
    {
        // Mock JWKS endpoint
        Http::fake([
            'https://iae-sso.virtualfri.id/api/v1/auth/jwks' => Http::response($this->jwks, 200),
        ]);

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

        $token = $this->generateJwt($payload);

        // Access the public health endpoint protected under SsoJwtAuth middleware for testing
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/winners'); // Requesting winners which is under auth.sso

        // Assert HTTP status is 200 OK (the endpoint is reached meaning auth passed)
        $response->assertStatus(200);

        // Assert user was created locally
        $user = User::where('email', 'warga07@ktp.iae.id')->first();
        $this->assertNotNull($user);
        $this->assertEquals('Galih Mahendra', $user->name);

        // Assert role was mapped to 'Warga'
        $this->assertNotNull($user->role);
        $this->assertEquals('Warga', $user->role->name);
    }

    public function test_invoice_creation_triggers_soap_audit_and_amqp_publisher()
    {
        // 1. Create a winner to be invoiced
        $winner = Winner::create([
            'auction_id'        => 'AUC-999',
            'item_id'           => 'ITM-999',
            'bidder_id'         => 'BID-999',
            'bidder_name'       => 'Galih Mahendra',
            'bidder_email'      => 'warga07@ktp.iae.id',
            'item_name'         => 'Test Item',
            'winning_bid'       => 100000,
            'status'            => 'pending',
            'auction_ended_at'  => now(),
        ]);

        // 2. Generate token for authentication
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
        $token = $this->generateJwt($payload);

        // SOAP Response with ReceiptNumber
        $soapXmlResponse = '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:iae="http://iae.central/audit">
            <soap:Body>
                <iae:AuditResponse>
                    <iae:Status>SUCCESS</iae:Status>
                    <iae:ReceiptNumber>IAE-LOG-2026-TEST-RECEIPT-99</iae:ReceiptNumber>
                </iae:AuditResponse>
            </soap:Body>
        </soap:Envelope>';

        // Mock external endpoints
        Http::fake([
            'https://iae-sso.virtualfri.id/api/v1/auth/jwks' => Http::response($this->jwks, 200),
            'https://iae-sso.virtualfri.id/api/v1/auth/token' => Http::response(['token' => 'mock-m2m-token'], 200),
            'https://iae-sso.virtualfri.id/soap/v1/audit' => Http::response($soapXmlResponse, 200),
            'https://iae-sso.virtualfri.id/api/v1/messages/publish' => Http::response(['status' => 'success'], 200),
        ]);

        // 3. Request Invoice Creation
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/invoices', [
            'winner_id' => $winner->id,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('status', 'success');

        // Assert Invoice database storage
        $invoice = Invoice::where('winner_id', $winner->id)->first();
        $this->assertNotNull($invoice);
        $this->assertEquals('IAE-LOG-2026-TEST-RECEIPT-99', $invoice->soap_receipt_number);

        // Assert winner status updated
        $winner->refresh();
        $this->assertEquals('invoiced', $winner->status);

        // Verify SOAP and AMQP endpoints were actually hit
        Http::assertSent(function ($request) {
            return $request->url() === 'https://iae-sso.virtualfri.id/soap/v1/audit' &&
                   str_contains($request->body(), 'Fariz Shadiq') &&
                   str_contains($request->body(), '102022430010');
        });

        Http::assertSent(function ($request) {
            return $request->url() === 'https://iae-sso.virtualfri.id/api/v1/messages/publish' &&
                   $request['routing_key'] === 'invoice.created' &&
                   $request['message']['student_name'] === 'Fariz Shadiq';
        });
    }
}
