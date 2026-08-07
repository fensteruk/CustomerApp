<x-layouts.portal title="Choose a new password | Fenster Customer Portal">
    <section class="mx-auto flex min-h-[calc(100vh-73px)] max-w-md flex-col justify-center px-4 py-10 sm:px-6" aria-labelledby="page-title">
        <p class="eyebrow">Account recovery</p>
        <h1 id="page-title" class="page-title">Choose a new password</h1>
        <p class="page-intro">Enter a new password for your portal account.</p>

        <form method="POST" action="{{ route('password.store') }}" class="mt-8 space-y-5 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            @csrf
            <input type="hidden" name="token" value="{{ $request->route('token') }}">

            <div>
                <label for="email" class="form-label">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email', $request->email) }}" required autocomplete="username" class="form-input">
                @error('email')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="form-label">New password</label>
                <input id="password" name="password" type="password" required autocomplete="new-password" class="form-input">
                @error('password')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password_confirmation" class="form-label">Confirm password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="form-input">
                @error('password_confirmation')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex justify-end">
                <button type="submit" class="primary-button">Reset password</button>
            </div>
        </form>
    </section>
</x-layouts.portal>
