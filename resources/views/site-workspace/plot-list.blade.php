@php
    $plotViewDefault = 'cards';
    $plotViewDesktopDefault = 'table';
    $plotViewDesktopQuery = '(min-width: 1024px)';
    $plotViewStorageKey = 'customerapp:site-plots:view:'.auth()->user()->uuid;
@endphp
<div class="sw-view-workspace" x-data="sitePlotView(@js($plotViewStorageKey), @js($plotViewDefault), @js($plotViewDesktopDefault), @js($plotViewDesktopQuery))" data-default-view="{{ $plotViewDefault }}" data-desktop-default-view="{{ $plotViewDesktopDefault }}" data-desktop-query="{{ $plotViewDesktopQuery }}">
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
    window.sitePlotView = window.sitePlotView || function (storageKey, fallback, desktopView, desktopQuery) {
        const isView = (value) => ['table', 'cards'].includes(value);
        let desktop = false;
        try { desktop = Boolean(desktopQuery) && window.matchMedia(desktopQuery).matches; } catch (error) {}

        return {
            view: desktop && isView(desktopView) ? desktopView : (isView(fallback) ? fallback : 'cards'),
            init() {
                try {
                    const stored = window.localStorage.getItem(storageKey);
                    if (isView(stored)) this.view = stored;
                } catch (error) {}
            },
            choose(next) {
                if (!isView(next)) return;
                this.view = next;
                try { window.localStorage.setItem(storageKey, next); } catch (error) {}
            },
        };
    };
</script>
