<x-layouts.portal title="Forgot password | Fenster Customer Portal">
    @include('office.partials.support-styles')
    <section class="support-workspace support-auth" aria-labelledby="page-title">
        <p class="eyebrow">Account recovery</p>
        <h1 id="page-title" class="page-title">Reset your password</h1>
        <p class="page-intro">Enter your email address and we will send a secure reset link.</p>

        @if (session('status'))
            <p role="status" class="support-note mt-6">{{ session('status') }}</p>
        @endif

        @if($errors->any())<div class="admin-error mt-5" role="alert"><h2 class="font-bold">Please check your details</h2><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <form method="POST" action="{{ route('password.email') }}" class="support-panel space-y-5">
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
