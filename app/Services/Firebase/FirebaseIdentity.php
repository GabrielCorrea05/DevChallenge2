<?php

namespace App\Services\Firebase;

/**
 * Datos de confianza extraídos de un ID token ya verificado.
 */
final readonly class FirebaseIdentity
{
    public function __construct(
        public string $uid,
        public string $email,
        public bool $emailVerified,
        public ?string $name = null,
        public ?string $avatar = null,
    ) {}
}
