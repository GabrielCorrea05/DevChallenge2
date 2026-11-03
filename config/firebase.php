<?php

return [

    /*
    |---------------------------------------------------------------------------
    | Proyecto de Firebase
    |---------------------------------------------------------------------------
    |
    | El ID del proyecto sirve para dos cosas: validar el token que envía el
    | navegador (aud/iss) y decidir si el botón de Google está activo.
    |
    */

    'project_id' => env('FIREBASE_PROJECT_ID'),

    /*
    |---------------------------------------------------------------------------
    | Configuración web (pública)
    |---------------------------------------------------------------------------
    |
    | Estos valores NO son secretos: el SDK de Firebase en el navegador los
    | necesita sí o sí. Se copian de la consola de Firebase
    | (Configuración del proyecto > Tus apps > Configuración del SDK).
    |
    */

    'api_key' => env('FIREBASE_API_KEY'),
    'auth_domain' => env('FIREBASE_AUTH_DOMAIN'),
    'app_id' => env('FIREBASE_APP_ID'),
    'messaging_sender_id' => env('FIREBASE_MESSAGING_SENDER_ID'),
    'storage_bucket' => env('FIREBASE_STORAGE_BUCKET'),

    /*
    |---------------------------------------------------------------------------
    | Certificados públicos de Google
    |---------------------------------------------------------------------------
    |
    | Firebase firma los ID tokens con claves RSA de Google. Con el `kid` de la
    | cabecera descargamos la clave pública correspondiente. No hace falta
    | cuenta de servicio ni secreto alguno para verificar un ID token.
    |
    */

    'certs_url' => env(
        'FIREBASE_CERTS_URL',
        'https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com'
    ),

    'certs_ttl_seconds' => (int) env('FIREBASE_CERTS_TTL', 3600),

];
