<?php

namespace App\Http\Controllers;

use App\Policies\OfficeAdministrationPolicy;
use App\SourceImport\Integration\BindingIntegrityAudit;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

final class OfficeWaldIntegrityController extends Controller
{
    public function __invoke(Request $request, BindingIntegrityAudit $audit): View
    {
        (new OfficeAdministrationPolicy)->authorize($request->user(), 'view');
        $report = $audit->run();
        $page = max(1, (int) $request->integer('page', 1));
        $items = new LengthAwarePaginator(array_slice($report['items'], ($page - 1) * 25, 25), count($report['items']), 25, $page,
            ['path' => $request->url()]);

        return view('office.settings.wald-integrity', ['report' => $report, 'items' => $items]);
    }
}
