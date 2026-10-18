<?php

namespace App\Http\Controllers;

use App\SourceImport\Integration\ImportConflict;
use App\SourceImport\Integration\PairedWorkbookAnalyse;
use App\SourceImport\Integration\PairedWorkbookAnalysePolicy;
use App\Wald\Services\AnalysisProblem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class OfficePairedWorkbookAnalyseController extends Controller
{
    public function index(Request $request): View
    {
        (new PairedWorkbookAnalysePolicy)->authorize($request->user());

        return view('office.pilot-import.paired-analyse', ['report' => null]);
    }

    public function analyse(Request $request, PairedWorkbookAnalyse $analyse): View|RedirectResponse
    {
        (new PairedWorkbookAnalysePolicy)->authorize($request->user());
        $validated = $request->validate([
            'master_register' => ['required', 'file', 'max:5120', 'mimes:xls,xlsx'],
            'call_off_report' => ['required', 'file', 'max:5120', 'mimes:xls,xlsx'],
        ]);
        $combinedBytes = $validated['master_register']->getSize() + $validated['call_off_report']->getSize();
        if ($combinedBytes > 5 * 1024 * 1024) {
            return back()->withErrors(['master_register' => 'Together, both workbooks must be no larger than 5 MB.']);
        }
        try {
            $report = $analyse->analyse($request->user(), $validated['master_register'], $validated['call_off_report']);
        } catch (ImportConflict $exception) {
            return back()->withErrors(['master_register' => 'Analysis stopped safely: '.str($exception->getMessage())->replace('_', ' ')->ucfirst().'.']);
        } catch (AnalysisProblem|\InvalidArgumentException $exception) {
            return back()->withErrors(['master_register' => 'Analysis stopped safely. One or both workbooks could not be analysed within the supported structure and resource limits. No data was applied.']);
        }

        return view('office.pilot-import.paired-analyse', ['report' => $report]);
    }
}
