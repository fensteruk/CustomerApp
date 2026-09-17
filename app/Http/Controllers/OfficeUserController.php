<?php

namespace App\Http\Controllers;

use App\Actions\Administration\ManagePortalUserAction;
use App\Http\Requests\Office\SavePortalUserRequest;
use App\Http\Requests\Office\UserLifecycleRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

final class OfficeUserController extends Controller
{
    public function store(SavePortalUserRequest $request, ManagePortalUserAction $action): RedirectResponse
    {
        $user = $action->create($request->user(), $request->validated());

        return redirect()->route('office.workspace.users.show', $user)->with('status', 'User created.');
    }

    public function update(SavePortalUserRequest $request, User $user, ManagePortalUserAction $action): RedirectResponse
    {
        $user = $action->update($request->user(), $user, $request->validated());

        return redirect()->route('office.workspace.users.show', $user)->with('status', 'User updated.');
    }

    public function deactivate(UserLifecycleRequest $request, User $user, ManagePortalUserAction $action): RedirectResponse
    {
        abort_if($request->user()->is($user), 422, 'You cannot deactivate your own account.');
        $action->setActive($request->user(), $user, false, (int) $request->validated('lock_version'));

        return redirect()->route('office.workspace.users.show', $user)->with('status', 'User deactivated.');
    }

    public function reactivate(UserLifecycleRequest $request, User $user, ManagePortalUserAction $action): RedirectResponse
    {
        $action->setActive($request->user(), $user, true, (int) $request->validated('lock_version'));

        return redirect()->route('office.workspace.users.show', $user)->with('status', 'User reactivated.');
    }
}
