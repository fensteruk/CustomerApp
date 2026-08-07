<x-layouts.portal title="Forgot password | Fenster Customer Portal">
    <section class="mx-auto flex min-h-[calc(100vh-73px)] max-w-md flex-col justify-center px-4 py-10 sm:px-6" aria-labelledby="page-title">
        <p class="eyebrow">Account recovery</p>
        <h1 id="page-title" class="page-title">Reset your password</h1>
        <p class="page-intro">Enter your email address and we will send a secure reset link.</p>

        @if (session('status'))
            <p class="mt-6 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-900">{{ session('status') }}</p>
        @endif

        <form method="POST" action="{{ route('password.email') }}" class="mt-8 space-y-5 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            @csrf

            <div>
                <label for="email" class="form-label">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="form-input">
                @error('email')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <a href="{{ route('login') }}" class="text-sm font-semibold text-sky-700 hover:text-sky-900">Back to sign in</a>
                <button type="submit" class="primary-button">Send reset link</button>
            </div>
        </form>
    </section>
</x-layouts.portal>
