<?php

namespace App\Http\Controllers;

use App\SourceImport\Integration\DryRunHeaderConflict;
use App\SourceImport\Integration\ImportConflict;
use App\SourceImport\Integration\PilotImportPolicy;
use App\SourceImport\Integration\WaldDryRun;
use App\SourceImport\Integration\WaldDryRunDiagnostics;
use App\Wald\Services\AnalysisProblem;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class OfficeWaldDryRunController extends Controller
{
    public function index(Request $request): View
    {
        (new PilotImportPolicy)->authorize($request->user());

        return view('office.pilot-import.dry-run', ['report' => null]);
    }

    public function analyse(Request $request, WaldDryRun $dryRun)
    {
        (new PilotImportPolicy)->authorize($request->user());
        $request->validate(['workbook' => ['required', 'file', 'max:20480', 'mimes:xls,xlsx,csv']]);
        $started = hrtime(true);
        try {
            $report = $dryRun->analyse($request->user(), $request->file('workbook'));
        } catch (DryRunHeaderConflict $exception) {
            return back()->withErrors(['workbook' => $exception->guidance]);
        } catch (ImportConflict $exception) {
            return back()->withErrors(['workbook' => 'Analysis stopped safely: '.str($exception->getMessage())->replace('_', ' ')->ucfirst().'.']);
        } catch (AnalysisProblem|\InvalidArgumentException $exception) {
            (new WaldDryRunDiagnostics)->stopped($exception, $started);

            return back()->withErrors(['workbook' => 'Analysis stopped safely. The workbook could not be analysed within the supported structure and resource limits. No data was applied.']);
        }

        return view('office.pilot-import.dry-run', ['report' => $report]);
    }
}
