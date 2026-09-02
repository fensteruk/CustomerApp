<?php

namespace App\Http\Controllers;

use App\Enums\SourceImportScope;
use App\Exceptions\InvalidSourceWorkbook;
use App\Exceptions\SourceWorkbookContractUnavailable;
use App\Http\Requests\CommitManualSourceImportRequest;
use App\Http\Requests\ConfirmWorkbookInterpretationRequest;
use App\Http\Requests\PreviewManualSourceImportRequest;
use App\Models\ManualSourceImportPreview;
use App\Models\SourceImportRun;
use App\Services\ManualSourceImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ManualSourceImportController extends Controller
{
    public function __construct(private readonly ManualSourceImportService $imports) {}

    public function preview(PreviewManualSourceImportRequest $request): JsonResponse
    {
        Gate::authorize('manage-source-imports');

        try {
            $preview = $this->imports->preview(
                $request->user(),
                $request->file('workbook'),
                SourceImportScope::tryFrom((string) $request->input('import_scope')) ?? SourceImportScope::PartialFilteredExport,
                $request->validated('complete_site_identifiers', []),
            );
        } catch (SourceWorkbookContractUnavailable $exception) {
            return response()->json([
                'error' => 'WORKBOOK_CONTRACT_REQUIRED',
                'message' => $exception->getMessage(),
            ], 409);
        } catch (InvalidSourceWorkbook $exception) {
            return response()->json([
                'error' => 'INVALID_SOURCE_WORKBOOK',
                'message' => $exception->getMessage(),
                'errors' => $exception->errors,
            ], 422);
        } catch (\InvalidArgumentException $exception) {
            return response()->json([
                'error' => 'INVALID_IMPORT_SCOPE',
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json($this->imports->previewView($request->user(), $preview), 201);
    }

    public function show(ManualSourceImportPreview $manualSourceImportPreview): JsonResponse
    {
        Gate::authorize('manage-source-imports');

        return response()->json($this->imports->previewView(request()->user(), $manualSourceImportPreview));
    }

    public function confirmInterpretation(ConfirmWorkbookInterpretationRequest $request, ManualSourceImportPreview $manualSourceImportPreview): JsonResponse
    {
        Gate::authorize('manage-source-imports');

        try {
            $preview = $this->imports->confirmMapping($request->user(), $manualSourceImportPreview, [
                'sheet' => $request->string('sheet')->toString(),
                'header_row' => $request->integer('header_row'),
                'columns' => $request->validated('columns'),
            ]);
        } catch (InvalidSourceWorkbook $exception) {
            return response()->json([
                'error' => 'MAPPING_REJECTED',
                'message' => $exception->getMessage(),
                'errors' => $exception->errors,
            ], 422);
        } catch (\InvalidArgumentException $exception) {
            return response()->json([
                'error' => 'MAPPING_REJECTED',
                'message' => $exception->getMessage(),
            ], 422);
        } catch (\DomainException $exception) {
            return response()->json([
                'error' => 'PREVIEW_NOT_MAPPABLE',
                'message' => $exception->getMessage(),
            ], 409);
        }

        return response()->json($this->imports->previewView($request->user(), $preview));
    }

    public function commit(CommitManualSourceImportRequest $request, ManualSourceImportPreview $manualSourceImportPreview): JsonResponse
    {
        Gate::authorize('manage-source-imports');

        try {
            $run = $this->imports->commit($request->user(), $manualSourceImportPreview, $request->string('content_sha256')->toString());
        } catch (InvalidSourceWorkbook $exception) {
            return response()->json([
                'error' => 'IMPORT_BLOCKED',
                'message' => $exception->getMessage(),
                'errors' => $exception->errors,
            ], 422);
        } catch (\DomainException $exception) {
            return response()->json([
                'error' => 'PREVIEW_NOT_COMMITTABLE',
                'message' => $exception->getMessage(),
            ], 409);
        }

        return response()->json($this->imports->resultView($request->user(), $run));
    }

    public function result(SourceImportRun $sourceImportRun): JsonResponse
    {
        Gate::authorize('manage-source-imports');

        return response()->json($this->imports->resultView(request()->user(), $sourceImportRun));
    }
}
