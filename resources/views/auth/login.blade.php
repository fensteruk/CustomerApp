<x-layouts.portal title="Sign in | Fenster Customer Portal">
    <section class="mx-auto flex min-h-[calc(100vh-73px)] max-w-md flex-col justify-center px-4 py-10 sm:px-6" aria-labelledby="page-title">
        <p class="eyebrow">Secure access</p>
        <h1 id="page-title" class="page-title">Sign in to the Customer Portal</h1>
        <p class="page-intro">Use your authorised portal account to continue.</p>

        @if (session('status'))
            <p class="mt-6 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-900">{{ session('status') }}</p>
        @endif

        <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-5 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            @csrf

            <div>
                <label for="email" class="form-label">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="form-input">
                @error('email')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="form-label">Password</label>
                <input id="password" name="password" type="password" required autocomplete="current-password" class="form-input">
                @error('password')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>

            <label class="flex items-center gap-3 text-sm font-medium text-slate-700">
                <input type="checkbox" name="remember" class="rounded border-slate-300 text-sky-700 focus:ring-sky-600">
                Remember this device
            </label>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <a href="{{ route('password.request') }}" class="text-sm font-semibold text-sky-700 hover:text-sky-900">Forgot password?</a>
                <button type="submit" class="primary-button">Sign in</button>
            </div>
        </form>
    </section>
</x-layouts.portal>
