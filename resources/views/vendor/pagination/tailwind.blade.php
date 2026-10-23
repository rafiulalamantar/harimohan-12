{{-- Previous / Next stay labelled buttons at every width; page numbers join them from sm up. --}}
@if ($paginator->hasPages())
    @php($buttonClass = 'inline-flex items-center gap-1.5 rounded-xl px-4 py-2 text-sm font-semibold ring-1 transition')
    @php($activeButton = 'bg-white text-slate-800 ring-slate-200 shadow-xs hover:bg-brand-50 hover:text-brand-800 hover:ring-brand-300')
    @php($disabledButton = 'bg-slate-50 text-slate-400 ring-slate-200 cursor-not-allowed')
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="flex items-center justify-between gap-3">
        @if ($paginator->onFirstPage())
            <span class="{{ $buttonClass }} {{ $disabledButton }}" aria-disabled="true">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                {{ __('Previous') }}
            </span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $buttonClass }} {{ $activeButton }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                {{ __('Previous') }}
            </a>
        @endif

        <span class="text-sm text-slate-500 sm:hidden">Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</span>

        <div class="hidden sm:flex flex-wrap items-center justify-center gap-1.5">
            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="px-2 text-sm text-slate-400" aria-disabled="true">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span aria-current="page" class="grid place-items-center min-w-9 h-9 rounded-xl bg-slate-900 px-2 text-sm font-semibold text-white">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="grid place-items-center min-w-9 h-9 rounded-xl px-2 text-sm font-semibold text-slate-700 ring-1 ring-slate-200 bg-white hover:bg-brand-50 hover:text-brand-800 hover:ring-brand-300 transition" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach
        </div>

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $buttonClass }} {{ $activeButton }}">
                {{ __('Next') }}
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </a>
        @else
            <span class="{{ $buttonClass }} {{ $disabledButton }}" aria-disabled="true">
                {{ __('Next') }}
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </span>
        @endif
    </nav>
@endif
