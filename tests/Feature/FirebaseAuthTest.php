<?php

namespace Tests\Feature;

use App\Models\ShoppingList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FirebaseAuthTest extends TestCase
{
    use RefreshDatabase;

    private const PROJECT_ID = 'compra-test';

    private string $kid = 'test-kid';

    private string $privateKey;

    private string $publicCertificate;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'firebase.project_id' => self::PROJECT_ID,
            'firebase.certs_url' => 'https://firebase.test/certs',
        ]);

        $key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        $this->assertNotFalse($key, 'No se ha podido generar la clave RSA de prueba.');

        $csr = openssl_csr_new(['commonName' => 'securetoken.test'], $key, ['digest_alg' => 'sha256']);
        $certificate = openssl_csr_sign($csr, null, $key, 1, ['digest_alg' => 'sha256']);

        openssl_pkey_export($key, $privateKeyPem);
        openssl_x509_export($certificate, $certificatePem);

        $this->privateKey = $privateKeyPem;
        $this->publicCertificate = $certificatePem;

        Http::fake([
            'https://firebase.test/certs' => Http::response([$this->kid => $this->publicCertificate]),
        ]);
    }

    public function test_google_login_creates_a_verified_user_and_opens_the_session(): void
    {
        $token = $this->idToken([
            'sub' => 'firebase-uid-123',
            'email' => 'maria@gmail.com',
            'name' => 'Maria Puig',
            'picture' => 'https://example.com/maria.png',
        ]);

        $response = $this->postJson(route('auth.firebase'), ['id_token' => $token]);

        $response->assertOk()->assertJson(['ok' => true]);

        $user = User::where('email', 'maria@gmail.com')->first();

        $this->assertNotNull($user);
        $this->assertSame('firebase-uid-123', $user->firebase_uid);
        $this->assertSame('Maria Puig', $user->name);
        $this->assertNull($user->password);
        $this->assertNotNull($user->email_verified_at);

        $this->assertAuthenticatedAs($user);
    }

    public function test_second_login_with_the_same_account_reuses_the_user(): void
    {
        $claims = ['sub' => 'firebase-uid-999', 'email' => 'repetit@gmail.com'];

        $this->postJson(route('auth.firebase'), ['id_token' => $this->idToken($claims)])->assertOk();

        Auth::logout();

        $this->postJson(route('auth.firebase'), ['id_token' => $this->idToken($claims)])->assertOk();

        $this->assertSame(1, User::where('email', 'repetit@gmail.com')->count());
    }

    public function test_an_existing_password_account_is_linked_instead_of_duplicated(): void
    {
        $user = User::factory()->create([
            'email' => 'oscar@ejemplo.com',
            'name' => 'Oscar Profe',
        ]);

        $response = $this->postJson(route('auth.firebase'), [
            'id_token' => $this->idToken(['sub' => 'uid-profe', 'email' => 'oscar@ejemplo.com']),
        ]);

        $response->assertOk();

        $this->assertSame(1, User::where('email', 'oscar@ejemplo.com')->count());

        $user->refresh();

        $this->assertSame('uid-profe', $user->firebase_uid);
        $this->assertSame('Oscar Profe', $user->name, 'No debe sobrescribir el nombre ya existente.');
        $this->assertNotNull($user->password, 'La contraseña local debe conservarse.');
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_list_shared_by_email_is_visible_after_logging_in_with_google(): void
    {
        $owner = User::factory()->create(['email' => 'owner@example.com']);

        $list = ShoppingList::create(['name' => 'Compra pis', 'owner_id' => $owner->id]);
        $list->members()->attach($owner->id, ['role' => 'owner']);

        $this->actingAs($owner)->post(route('lists.share', $list), [
            'email' => 'compartit@gmail.com',
        ])->assertRedirect();

        Auth::logout();

        $this->postJson(route('auth.firebase'), [
            'id_token' => $this->idToken(['sub' => 'uid-compartit', 'email' => 'compartit@gmail.com']),
        ])->assertOk();

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Compra pis');
    }

    public function test_a_token_signed_with_another_key_is_rejected(): void
    {
        $otherKey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($otherKey, $otherPem);

        $response = $this->postJson(route('auth.firebase'), [
            'id_token' => $this->idToken(['sub' => 'fake'], $otherPem),
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('firebase');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_an_expired_token_is_rejected(): void
    {
        $response = $this->postJson(route('auth.firebase'), [
            'id_token' => $this->idToken([
                'sub' => 'uid-antic',
                'email' => 'antic@gmail.com',
                'exp' => time() - 10,
            ]),
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('firebase');
        $this->assertGuest();
    }

    public function test_a_token_from_another_firebase_project_is_rejected(): void
    {
        $response = $this->postJson(route('auth.firebase'), [
            'id_token' => $this->idToken([
                'sub' => 'uid-aliè',
                'email' => 'altre@gmail.com',
                'aud' => 'un-altre-projecte',
            ]),
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('firebase');
        $this->assertGuest();
    }

    public function test_a_token_without_email_is_rejected(): void
    {
        $claims = ['sub' => 'uid-sense-email'];
        $token = $this->idToken($claims, removeClaims: ['email']);

        $this->postJson(route('auth.firebase'), ['id_token' => $token])
            ->assertStatus(422)
            ->assertJsonValidationErrors('firebase');

        $this->assertGuest();
    }

    /**
     * @param  array<string, mixed>  $claims
     * @param  array<int, string>  $removeClaims
     */
    private function idToken(array $claims = [], ?string $privateKey = null, array $removeClaims = []): string
    {
        $now = time();

        $payload = array_filter(array_merge([
            'iss' => 'https://securetoken.google.com/'.self::PROJECT_ID,
            'aud' => self::PROJECT_ID,
            'auth_time' => $now,
            'iat' => $now,
            'exp' => $now + 3600,
            'sub' => 'uid-per-defecte',
            'email' => 'usuari@gmail.com',
            'email_verified' => true,
        ], $claims), static fn ($value, $key) => ! in_array($key, $removeClaims, true), ARRAY_FILTER_USE_BOTH);

        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'kid' => $this->kid, 'typ' => 'JWT']));
        $body = $this->base64UrlEncode(json_encode($payload));
        $signingInput = $header.'.'.$body;

        openssl_sign($signingInput, $signature, $privateKey ?? $this->privateKey, OPENSSL_ALGO_SHA256);

        return $signingInput.'.'.$this->base64UrlEncode($signature);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
