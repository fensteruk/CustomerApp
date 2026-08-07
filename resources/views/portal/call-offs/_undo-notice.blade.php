@if (session('quickUndo'))
    <div
        class="mt-4 rounded-lg border border-sky-300 bg-sky-50 p-4 text-sm text-sky-950"
        role="status"
        x-data="{ seconds: 5, active: true }"
        x-init="setInterval(() => { if (seconds > 0) { seconds--; } else { active = false; } }, 1000)"
    >
        <div x-show="active" class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p><strong>Undo is available.</strong> This action can be reversed for five seconds. <span x-text="seconds + ' seconds remaining'"></span></p>
            <form method="POST" action="{{ route('portal.call-offs.operations.undo', session('quickUndo.operation_uuid')) }}">
                @csrf
                <button type="submit" class="secondary-button">Undo action</button>
            </form>
        </div>
        <p x-show="! active" x-cloak><strong>The quick Undo period has ended.</strong> The server decides whether Undo is still available.</p>
        <noscript><p class="mt-2">Undo availability is checked securely by the server when you select Undo.</p></noscript>
    </div>
@endif
