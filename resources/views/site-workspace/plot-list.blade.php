@php
    $plotViewDefault = $siteSummary['total'] >= 100 ? 'table' : 'cards';
    $plotViewStorageKey = 'customerapp:site-plots:view:'.auth()->user()->uuid;
@endphp
<div class="sw-view-workspace" x-data="sitePlotView(@js($plotViewStorageKey), @js($plotViewDefault))" data-default-view="{{ $plotViewDefault }}">
    <div class="sw-view-switcher" role="group" aria-label="Plot view">
        <span class="sw-muted">View</span>
        <button type="button" class="sw-view-button" :class="{ 'is-selected': view === 'table' }" :aria-pressed="(view === 'table').toString()" @click="choose('table')">Table</button>
        <button type="button" class="sw-view-button" :class="{ 'is-selected': view === 'cards' }" :aria-pressed="(view === 'cards').toString()" @click="choose('cards')">Cards</button>
    </div>
    <p class="sr-only" aria-live="polite" x-text="`${view === 'table' ? 'Table' : 'Cards'} view selected`"></p>
    <template x-if="view === 'table'">
        <div data-plot-view="table">@include('site-workspace.table')</div>
    </template>
    <template x-if="view === 'cards'">
        <div data-plot-view="cards">@include('site-workspace.cards')</div>
    </template>
</div>
<noscript>
    @include('site-workspace.cards')
</noscript>
<script>
    window.sitePlotView = window.sitePlotView || function (storageKey, fallback) {
        return {
            view: ['table', 'cards'].includes(fallback) ? fallback : 'cards',
            init() {
                try {
                    const stored = window.localStorage.getItem(storageKey);
                    if (['table', 'cards'].includes(stored)) this.view = stored;
                } catch (error) {}
            },
            choose(next) {
                if (!['table', 'cards'].includes(next)) return;
                this.view = next;
                try { window.localStorage.setItem(storageKey, next); } catch (error) {}
            },
        };
    };
</script>
