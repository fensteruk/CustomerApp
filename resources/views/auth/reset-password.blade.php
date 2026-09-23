<x-layouts.portal title="Choose a new password | Fenster Customer Portal">
    @include('office.partials.support-styles')
    <section class="support-workspace support-auth" aria-labelledby="page-title">
        <p class="eyebrow">Account recovery</p>
        <h1 id="page-title" class="page-title">Choose a new password</h1>
        <p class="page-intro">Enter a new password for your portal account.</p>

        @if($errors->any())<div class="admin-error mt-5" role="alert"><h2 class="font-bold">Please check your details</h2><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <form method="POST" action="{{ route('password.store') }}" class="support-panel space-y-5">
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
