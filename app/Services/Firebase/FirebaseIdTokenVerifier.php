<?php

namespace App\Services\Firebase;

use App\Exceptions\FirebaseAuthException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Verifica un ID token de Firebase sin SDK de administrador.
 *
 * Firebase (Google Identity Toolkit) firma los ID tokens con RSA-SHA256 y
 * publica las claves públicas en una URL con formato {kid: certificado X.509}.
 * Con eso basta para comprobar: firma, caducidad, emisor (iss) y destinatario
 * (aud = nuestro proyecto). No hace falta cuenta de servicio ni secreto.
 */
class FirebaseIdTokenVerifier
{
    public function __construct(
        private readonly ?string $projectId = null,
        private readonly ?string $certsUrl = null,
        private readonly int $certsTtl = 3600,
        private readonly ?int $clockSkewSeconds = 60,
    ) {}

    /**
     * @throws FirebaseAuthException
     */
    public function verify(string $idToken): FirebaseIdentity
    {
        $projectId = $this->resolveProjectId();

        [$header, $payload, $signature] = $this->split($idToken);

        if (($header['alg'] ?? null) !== 'RS256') {
            throw FirebaseAuthException::badAlgorithm($header['alg'] ?? null);
        }

        $kid = $header['kid'] ?? null;

        if (! is_string($kid) || $kid === '') {
            throw FirebaseAuthException::unknownKey();
        }

        $certificate = $this->certificateFor($kid);
        $publicKey = openssl_pkey_get_public($certificate);

        if ($publicKey === false) {
            throw FirebaseAuthException::unknownKey();
        }

        $verified = openssl_verify(
            $this->signingInput($idToken),
            $signature,
            $publicKey,
            OPENSSL_ALGO_SHA256,
        );

        if ($verified !== 1) {
            throw FirebaseAuthException::badSignature();
        }

        $this->assertClaims($payload, $projectId);

        return new FirebaseIdentity(
            uid: (string) $payload['sub'],
            email: strtolower((string) $payload['email']),
            emailVerified: (bool) ($payload['email_verified'] ?? false),
            name: $this->cleanName($payload['name'] ?? null, $payload['email']),
            avatar: is_string($payload['picture'] ?? null) ? $payload['picture'] : null,
        );
    }

    public function isConfigured(): bool
    {
        return filled($this->projectId ?? config('firebase.project_id'));
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function assertClaims(array $claims, string $projectId): void
    {
        $now = time();
        $skew = $this->clockSkewSeconds ?? 0;

        if (($claims['aud'] ?? null) !== $projectId) {
            throw FirebaseAuthException::wrongAudience();
        }

        if (($claims['iss'] ?? null) !== 'https://securetoken.google.com/'.$projectId) {
            throw FirebaseAuthException::wrongIssuer();
        }

        if (! isset($claims['exp']) || (int) $claims['exp'] <= $now) {
            throw FirebaseAuthException::expired();
        }

        if (isset($claims['iat']) && (int) $claims['iat'] > $now + $skew) {
            throw FirebaseAuthException::expired();
        }

        if (! isset($claims['sub']) || (string) $claims['sub'] === '') {
            throw FirebaseAuthException::missingClaim('sub');
        }

        if (! isset($claims['email']) || (string) $claims['email'] === '') {
            throw FirebaseAuthException::noEmail();
        }
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, mixed>, 2: string}
     */
    private function split(string $idToken): array
    {
        $parts = explode('.', trim($idToken));

        if (count($parts) !== 3) {
            throw FirebaseAuthException::malformedToken();
        }

        $header = json_decode($this->base64UrlDecode($parts[0]), true);
        $payload = json_decode($this->base64UrlDecode($parts[1]), true);
        $signature = $this->base64UrlDecode($parts[2]);

        if (! is_array($header) || ! is_array($payload) || $signature === '') {
            throw FirebaseAuthException::malformedToken();
        }

        return [$header, $payload, $signature];
    }

    private function signingInput(string $idToken): string
    {
        $parts = explode('.', trim($idToken));

        return $parts[0].'.'.$parts[1];
    }

    private function certificateFor(string $kid): string
    {
        $certificates = $this->certificates();

        if (! isset($certificates[$kid]) || ! is_string($certificates[$kid])) {
            throw FirebaseAuthException::unknownKey();
        }

        return $certificates[$kid];
    }

    /**
     * @return array<string, string>
     */
    private function certificates(): array
    {
        $url = $this->certsUrl ?? config('firebase.certs_url');

        /** @var array<string, string> $certs */
        $certs = Cache::remember(
            'firebase:securetoken-certificates:'.sha1($url),
            $this->certsTtl,
            function () use ($url): array {
                $response = Http::timeout(10)->acceptJson()->get($url);

                if (! $response->successful()) {
                    throw new FirebaseAuthException(
                        'No se han podido descargar los certificados de Firebase ('.$response->status().').'
                    );
                }

                return (array) $response->json();
            },
        );

        return $certs;
    }

    private function resolveProjectId(): string
    {
        $projectId = $this->projectId ?? config('firebase.project_id');

        if (! is_string($projectId) || $projectId === '') {
            throw FirebaseAuthException::missingProject();
        }

        return $projectId;
    }

    private function cleanName(mixed $name, string $email): string
    {
        if (is_string($name) && trim($name) !== '') {
            return trim($name);
        }

        return ucfirst(strtok($email, '@') ?: 'Usuari');
    }

    private function base64UrlDecode(string $value): string
    {
        $remainder = strlen($value) % 4;

        if ($remainder !== 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? '' : $decoded;
    }
}
