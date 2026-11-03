@props(['class' => ''])

<div class="mt-5">
    <div class="relative flex items-center">
        <div class="grow border-t border-stone-200"></div>
        <span class="mx-3 text-[11px] uppercase tracking-[0.06em] text-stone-400">o</span>
        <div class="grow border-t border-stone-200"></div>
    </div>

    <button
        type="button"
        data-google-login
        class="mt-4 inline-flex h-10 w-full items-center justify-center gap-2 rounded-lg border border-stone-300 bg-white px-4 text-[13px] font-medium text-stone-800 transition hover:border-stone-400 hover:bg-stone-50 focus:outline-none focus:ring-2 focus:ring-stone-900 focus:ring-offset-2 disabled:cursor-not-allowed {{ $class }}"
    >
        <svg class="h-4 w-4" viewBox="0 0 24 24" aria-hidden="true">
            <path fill="#4285F4" d="M23.49 12.27c0-.79-.07-1.54-.2-2.27H12v4.51h6.44a5.5 5.5 0 0 1-2.39 3.62v3h3.86c2.26-2.09 3.58-5.17 3.58-8.86Z"/>
            <path fill="#34A853" d="M12 24c3.24 0 5.96-1.08 7.95-2.91l-3.86-3c-1.08.72-2.45 1.16-4.09 1.16-3.13 0-5.78-2.11-6.73-4.96H1.28v3.09A12 12 0 0 0 12 24Z"/>
            <path fill="#FBBC05" d="M5.27 14.29a7.2 7.2 0 0 1 0-4.58V6.62H1.28a12 12 0 0 0 0 10.76l3.99-3.09Z"/>
            <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.23 0 12 0A12 12 0 0 0 1.28 6.62l3.99 3.09C6.22 6.86 8.87 4.75 12 4.75Z"/>
        </svg>
        <span data-google-label>Continuar con Google</span>
    </button>

    <p data-firebase-hint hidden class="mt-2 text-[12px] text-amber-700">
        Login con Google no disponible: faltan las claves <code>FIREBASE_*</code> en el <code>.env</code>.
    </p>

    <p data-firebase-error hidden class="mt-2 text-[13px] text-red-600" role="alert"></p>
</div>
