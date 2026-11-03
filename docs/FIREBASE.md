# Firebase en este proyecto

Documento de referencia: qué hace Firebase aquí, cómo está montado y qué pasos hay que hacer en la consola.

## 1. Para qué usamos Firebase

El enunciado pide **al menos dos métodos de login**. Tenemos:

1. **Email + contraseña** (Laravel Breeze, sesión de Laravel).
2. **Google**, mediante **Firebase Authentication** (proveedor Google).

Firebase **solo se ocupa del login**. La base de datos sigue siendo MySQL/SQLite con Eloquent
(`lists`, `categories`, `products`, `tags`, `list_user`…). No usamos Firestore: migrar toda la
app a Firestore obligaría a reescribir modelos, relaciones y permisos, y no aporta nada al
enunciado.

## 2. Cómo funciona el login con Google (paso a paso)

```
Navegador                    Firebase (Google)              Laravel
─────────                    ─────────────────              ───────
click "Continuar con Google"
   │
   ├── signInWithPopup() ───► popup de cuentas Google
   │                          devuelve usuario + ID token (JWT RS256, 1 h)
   │
   ├── POST /auth/firebase {id_token} ─────────────────────► FirebaseAuthController
   │                                                          │
   │                                                          ├─ verifica firma con las
   │                                                          │  claves públicas de Google
   │                                                          ├─ comprueba aud/iss/exp
   │                                                          ├─ busca usuario por
   │                                                          │  firebase_uid o email
   │                                                          │  (lo crea si no existe)
   │                                                          └─ Auth::login() + sesión
   │◄────────────── {ok:true, redirect:"/dashboard"} ─────────┘
   └── window.location = /dashboard
```

Puntos clave:

- **La sesión que manda es la de Laravel** (cookie). Firebase solo demuestra *quién* es el usuario.
- **No se guarda ningún secreto de Firebase**: los ID tokens se verifican con los certificados
  públicos que Google publica en
  `https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com`.
  Por eso no hace falta cuenta de servicio ni `firebase-admin`.
- Las claves `FIREBASE_*` del `.env` **no son secretas**: el SDK web las necesita en el navegador.
  La seguridad está en la verificación del token en servidor, no en esconder esas claves.
- **Vinculación de cuentas**: si ya existe un usuario con ese email (por ejemplo el profesor
  `oscar@ejemplo.com` creado con contraseña), al entrar con Google **se vincula** (`firebase_uid`)
  y se conserva su contraseña y su nombre. No se duplica el usuario. Así una lista compartida por
  email sigue siendo accesible entre los dos métodos de login.
- Usuarios nuevos de Google: `password = NULL` (no tienen contraseña local) y
  `email_verified_at` marcado, porque Google ya verificó el correo.

## 3. Qué pasos hay que dar en la consola de Firebase (una sola vez)

> **Estado actual del proyecto (hecho con la CLI):** proyecto `listacompradev2`
> ("ListaCompraDev2"), app web `devchallenge2-web`
> (`1:305342638764:web:2f9f75588eeab7a7b44d09`), Authentication inicializado y
> proveedor **Google habilitado**, dominios autorizados `localhost`, `127.0.0.1`,
> `listacompradev2.firebaseapp.com` y `listacompradev2.web.app`.
> Las claves están en el `.env` local.

1. Entra en <https://console.firebase.google.com> con la cuenta de Google del equipo.
2. **Crear proyecto** → nombre, p. ej. `devchallenge2` → desactiva Google Analytics (no lo usamos).
3. Menú **Compilación → Authentication → Comenzar**.
4. Pestaña **Sign-in method → Google** → *Habilitar* → elige un correo de soporte → *Guardar*.
5. Pestaña **Settings → Authorized domains**: deben estar
   `localhost` y el dominio donde publiques (p. ej. `devchallenge2.web.app` o tu dominio de Cloud Run).
   Si falta el dominio, el popup falla con `auth/unauthorized-domain`.
6. **Configuración del proyecto** (engranaje) → **General** → *Tus apps* → icono **Web `</>`** →
   registra la app (nombre `devchallenge2-web`, sin Hosting) → copia el objeto `firebaseConfig`.
7. Pega esos valores en el `.env`:

   ```dotenv
   FIREBASE_PROJECT_ID=devchallenge2
   FIREBASE_API_KEY=AIza...
   FIREBASE_AUTH_DOMAIN=devchallenge2.firebaseapp.com
   FIREBASE_APP_ID=1:1234567890:web:abc123
   FIREBASE_MESSAGING_SENDER_ID=1234567890
   FIREBASE_STORAGE_BUCKET=devchallenge2.appspot.com
   ```

8. Aplica los cambios:

   ```bash
   php artisan config:clear
   php artisan migrate        # añade users.firebase_uid
   npm run build              # compila resources/js/firebase-auth.js
   ```

9. Abre `/login` → el botón de Google debe estar activo. Entra con una cuenta y comprueba en
   `/dashboard` que aparece tu usuario y las listas compartidas con ese email.

## 4. Archivos implicados

| Archivo | Función |
|---|---|
| `config/firebase.php` | Lee las claves `FIREBASE_*` y la URL de certificados. |
| `resources/js/firebase-auth.js` | SDK web: popup de Google, obtiene el ID token y lo envía a Laravel. |
| `app/Services/Firebase/FirebaseIdTokenVerifier.php` | Verifica firma RS256, `aud`, `iss`, `exp`; devuelve `FirebaseIdentity`. |
| `app/Http/Controllers/Auth/FirebaseAuthController.php` | Crea/vincula el usuario y abre la sesión. |
| `app/Exceptions/FirebaseAuthException.php` | Errores legibles (token caducado, proyecto ajeno, etc.). |
| `resources/views/components/google-login-button.blade.php` | Botón reutilizado en login y registro. |
| `routes/auth.php` | `POST /auth/firebase` (solo invitados, límite 10 intentos/minuto). |
| `database/migrations/2026_11_02_000001_add_firebase_uid_to_users_table.php` | Columna `users.firebase_uid` única. |
| `tests/Feature/FirebaseAuthTest.php` | 8 pruebas: alta, reutilización, vinculación, lista compartida, firma falsa, caducado, otro proyecto, sin email. |

## 5. Probar sin Firebase (tests)

```bash
php artisan test --filter=FirebaseAuthTest
```

Los tests generan un par de claves RSA, firman un token como lo haría Google y simulan la descarga
de certificados con `Http::fake()`. Así se valida la lógica real de verificación sin depender de red.

## 6. Publicar la app (importante)

**Firebase App Hosting NO soporta PHP/Laravel.** Solo Next.js, Angular y frameworks con adaptador
Node. El `apphosting.yaml` que hay en el repo es una plantilla de ejemplo y **no va a desplegar esta
aplicación**. Opciones reales:

- **Cloud Run + Cloud SQL (MySQL)** dentro del mismo proyecto de Google Cloud que el Firebase
  (es el camino "todo Google"; requiere activar facturación, el crédito de 300 $ cubre de sobra un
  proyecto de clase). Luego se puede poner **Firebase Hosting delante con rewrite a Cloud Run** para
  tener dominio `*.web.app`.
- **Host gratuito** (Render/Railway/Fly) + este mismo Firebase Auth. Solo hay que añadir el dominio
  público en *Authorized domains*.

En ambos casos: `APP_KEY` y credenciales de BD como secretos, `APP_DEBUG=false`, `APP_ENV=production`,
y `php artisan migrate --force` en el arranque.

## 7. Problemas típicos

- **Botón desactivado y aviso amarillo** → faltan claves en `.env` o no se ejecutó `php artisan config:clear`.
- **`auth/unauthorized-domain`** → añade el dominio en Authentication → Settings → Authorized domains.
- **`auth/popup-blocked`** → el navegador bloqueó el popup; permite popups para el dominio.
- **422 "El token pertenece a otro proyecto"** → `FIREBASE_PROJECT_ID` no coincide con el proyecto real.
- **422 "token caducado"** → reloj del servidor desfasado o token viejo; vuelve a iniciar sesión.
- **Error de CORS/`Cross-Origin-Opener-Policy`** → el popup se abre pero no vuelve; ocurre con
  cabeceras COOP estrictas. En ese caso se usaría `signInWithRedirect` (no implementado).
