<section class="wald-panel" aria-labelledby="ignored-title">
    <h2 id="ignored-title" class="section-title">Ignored rows</h2>
    <p>This tab shows only rows ignored because their CustomerCode is XXTrade, XXTEST or 83. Other Call Type exclusions are counted in the whole-workbook summary but are not listed here. Rows left ignored do not enter any site preview or Apply.</p>
    @if($import['ignored_confirmed'])
        <p class="wald-status" role="status">Ignored CustomerCode rows confirmed for this upload.</p>
    @endif
    @if($ignoredRows->isEmpty())
        <p>No ignored rows are recorded for this upload.</p>
    @else
        <div class="overflow-x-auto">
            <table class="wald-table">
                <thead><tr><th scope="col">Workbook row</th><th scope="col">Call No.</th><th scope="col">CustomerCode</th><th scope="col">Call Type</th><th scope="col">Plot Ref</th><th scope="col">Reason</th><th scope="col">Decision</th></tr></thead>
                <tbody>
                    @foreach($ignoredRows as $row)
                        <tr>
                            <td data-label="Workbook row">{{ $row->row_number }}</td>
                            <td data-label="Call No.">{{ $row->call_no }}</td>
                            <td data-label="CustomerCode">{{ $row->customer_code ?: 'Blank' }}</td>
                            <td data-label="Call Type">{{ $row->call_type ?: 'Blank' }}</td>
                            <td data-label="Plot Ref">{{ $row->plot_ref ?: 'Blank' }}</td>
                            <td data-label="Reason">{{ match ($row->reason) {'CUSTOMER_CODE' => 'CustomerCode exclusion', 'CALL_TYPE' => 'Call Type exclusion', default => 'Workbook-specific exclusion'} }}</td>
                            <td data-label="Decision">
                                @if($row->disposition === 'RESTORED')
                                    Moved to approved review
                                @elseif(!$import['ignored_confirmed'] && $row->reason === 'CUSTOMER_CODE' && in_array($import['state'], ['READY', 'NEEDS_CLARIFICATION'], true) && !$import['selections'])
                                    <form method="POST" action="{{ route('office.workspace.pilot-import.ignored-rows.restore', ['upload' => $import['upload'], 'rowNumber' => $row->row_number]) }}">
                                        @csrf
                                        <input type="hidden" name="command_uuid" value="{{ (string) Illuminate\Support\Str::uuid() }}">
                                        <button class="secondary-button" type="submit" name="confirmation" value="MOVE TO APPROVED REVIEW">Not ignored — review normally</button>
                                    </form>
                                @else
                                    Ignored; dictionary review needed to include
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $ignoredRows->links() }}</div>
    @endif
    @if(!$import['ignored_confirmed'] && $ignoredRows->total() > 0 && in_array($import['state'], ['READY', 'NEEDS_CLARIFICATION', 'IN_PROGRESS'], true))
        <form method="POST" action="{{ route('office.workspace.pilot-import.ignored-rows.confirm', $import['upload']) }}" class="mt-6">
            @csrf
            <input type="hidden" name="command_uuid" value="{{ (string) Illuminate\Support\Str::uuid() }}">
            <label class="wald-check"><input type="checkbox" name="confirmation" value="CONFIRM IGNORED CUSTOMER CODE ROWS" required><span>I have reviewed the CustomerCode rows left ignored for this upload. This records their exclusion; it does not apply any site.</span></label>
            <button class="primary-button" type="submit">Confirm ignored rows</button>
        </form>
    @endif
</section>
