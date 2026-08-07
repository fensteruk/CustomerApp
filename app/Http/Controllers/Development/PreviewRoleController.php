<?php

namespace App\Http\Controllers\Development;

use App\Enums\PortalRoleIdentifier;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PreviewRoleController extends Controller
{
    /**
     * Show the local/test-only role preview screen.
     */
    public function create(): View
    {
        $this->guardPreviewEnvironment();

        return view('portal.role-preview');
    }

    /**
     * Sign in as a controlled preview user for the selected portal role.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->guardPreviewEnvironment();

        $validated = $request->validate([
            'role' => ['required', 'string'],
        ]);

        $role = PortalRoleIdentifier::tryFrom($validated['role']);

        abort_unless($role instanceof PortalRoleIdentifier, 404);

        $user = User::query()
            ->where('is_preview_user', true)
            ->whereHas('portalRole', fn ($query) => $query->where('identifier', $role->value))
            ->firstOrFail();

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    private function guardPreviewEnvironment(): void
    {
        abort_unless(app()->environment(['local', 'testing']), 404);
    }
}
