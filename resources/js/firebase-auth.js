import { initializeApp } from 'firebase/app';
import { GoogleAuthProvider, browserLocalPersistence, getAuth, setPersistence, signInWithPopup } from 'firebase/auth';

/**
 * Login con Google mediante Firebase Authentication.
 *
 * 1. El SDK abre el popup de Google y devuelve un usuario de Firebase.
 * 2. Pedimos su ID token (JWT firmado por Google, caduca en 1 hora).
 * 3. Lo mandamos a Laravel (/auth/firebase), que lo verifica y abre sesión.
 *
 * La sesión que manda en la app es la de Laravel (cookie); Firebase solo se
 * usa para demostrar quién es el usuario.
 */
const config = window.__firebaseConfig ?? {};
const buttons = document.querySelectorAll('[data-google-login]');
const errorBox = document.querySelector('[data-firebase-error]');
const hint = document.querySelector('[data-firebase-hint]');
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

const isConfigured = Boolean(config.apiKey && config.authDomain && config.projectId);

function showError(message) {
    if (!errorBox) {
        return;
    }

    errorBox.textContent = message;
    errorBox.hidden = false;
}

function clearError() {
    if (errorBox) {
        errorBox.hidden = true;
        errorBox.textContent = '';
    }
}

function setLoading(button, loading) {
    button.disabled = loading;
    button.classList.toggle('opacity-60', loading);
    button.querySelector('[data-google-label]').textContent = loading
        ? 'Conectando con Google…'
        : 'Continuar con Google';
}

if (!isConfigured) {
    buttons.forEach((button) => {
        button.disabled = true;
        button.title = 'Añade las claves FIREBASE_* en el archivo .env';
    });

    if (hint) {
        hint.hidden = false;
    }
} else {
    const auth = getAuth(initializeApp(config));
    auth.useDeviceLanguage();
    setPersistence(auth, browserLocalPersistence).catch(() => {});

    buttons.forEach((button) => {
        button.addEventListener('click', async () => {
            clearError();
            setLoading(button, true);

            try {
                const provider = new GoogleAuthProvider();
                provider.setCustomParameters({ prompt: 'select_account' });

                const credential = await signInWithPopup(auth, provider);
                const idToken = await credential.user.getIdToken();

                const response = await fetch('/auth/firebase', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({ id_token: idToken }),
                });

                const payload = await response.json().catch(() => ({}));

                if (!response.ok) {
                    const message =
                        payload?.errors?.firebase?.[0] ??
                        'No se ha podido iniciar sesión con Google. Inténtalo de nuevo.';
                    showError(message);
                    return;
                }

                window.location.assign(payload.redirect ?? '/dashboard');
            } catch (error) {
                if (error?.code === 'auth/popup-closed-by-user' || error?.code === 'auth/cancelled-popup-request') {
                    return;
                }

                if (error?.code === 'auth/unauthorized-domain') {
                    showError('Este dominio no está autorizado en Firebase Authentication.');
                    return;
                }

                showError('Error al conectar con Google: ' + (error?.message ?? 'desconocido'));
            } finally {
                setLoading(button, false);
            }
        });
    });
}
