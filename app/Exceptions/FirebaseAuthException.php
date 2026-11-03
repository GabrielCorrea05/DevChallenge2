<?php

namespace App\Exceptions;

use RuntimeException;

class FirebaseAuthException extends RuntimeException
{
    public static function missingProject(): self
    {
        return new self('Firebase no está configurado: falta FIREBASE_PROJECT_ID en el archivo .env.');
    }

    public static function malformedToken(): self
    {
        return new self('El token de Firebase no tiene el formato esperado.');
    }

    public static function badAlgorithm(?string $alg): self
    {
        return new self('Algoritmo de firma no admitido: '.($alg ?: 'desconocido').'.');
    }

    public static function unknownKey(): self
    {
        return new self('No se ha encontrado la clave pública que firmó el token (kid desconocido).');
    }

    public static function badSignature(): self
    {
        return new self('La firma del token de Firebase no es válida.');
    }

    public static function expired(): self
    {
        return new self('El token de Firebase ha caducado. Vuelve a iniciar sesión.');
    }

    public static function wrongAudience(): self
    {
        return new self('El token pertenece a otro proyecto de Firebase.');
    }

    public static function wrongIssuer(): self
    {
        return new self('El emisor del token no es el esperado.');
    }

    public static function missingClaim(string $claim): self
    {
        return new self('Al token de Firebase le falta el dato obligatorio "'.$claim.'".');
    }

    public static function noEmail(): self
    {
        return new self('La cuenta de Google no ha compartido un correo electrónico.');
    }
}
