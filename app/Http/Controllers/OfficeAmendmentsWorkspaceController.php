<?php

namespace App\Http\Controllers;

use App\Enums\CallOffServiceType;
use App\Services\OfficeAmendmentsWorkspaceQuery;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class OfficeAmendmentsWorkspaceController extends Controller
{
    public function __invoke(Request $request, OfficeAmendmentsWorkspaceQuery $query): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['attention', 'waiting', 'closed', 'all'])],
            'customer' => ['nullable', 'uuid'],
            'site' => ['nullable', 'uuid'],
            'service' => ['nullable', Rule::enum(CallOffServiceType::class)],
            'search' => ['nullable', 'string', 'max:100'],
            'request' => ['nullable', 'uuid'],
            'page' => ['nullable', 'integer', 'min:1'],
            'history_page' => ['nullable', 'integer', 'min:1'],
        ]);

        return view('office.amendments.index', $query->forUser($request->user(), $filters));
    }
}
