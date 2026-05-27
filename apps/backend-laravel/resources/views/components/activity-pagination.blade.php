@props([
    'paginator',
    'name',
])

@php
    $currentPage = $paginator->currentPage();
    $lastPage = $paginator->lastPage();
    $windowStart = max(1, $currentPage - 2);
    $windowEnd = min($lastPage, $currentPage + 2);
    $pageUrls = $paginator->getUrlRange($windowStart, $windowEnd);
    $label = \Illuminate\Support\Str::headline((string) $name);
@endphp

@if ($paginator->hasPages())
    <nav
        {{ $attributes->merge(['class' => 'activity-pagination']) }}
        data-activity-pagination="{{ $name }}"
        aria-label="{{ $label }} pagination"
    >
        <div class="activity-pagination__summary">
            {{ number_format($paginator->firstItem()) }}-{{ number_format($paginator->lastItem()) }} of {{ number_format($paginator->total()) }}
        </div>

        <div class="activity-pagination__controls">
            @if ($paginator->onFirstPage())
                <span class="activity-pagination__control is-disabled" aria-disabled="true" aria-label="Previous page">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15.41 7.41 14 6l-6 6 6 6 1.41-1.41L10.83 12l4.58-4.59Z"/></svg>
                </span>
            @else
                <a class="activity-pagination__control" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Previous page">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15.41 7.41 14 6l-6 6 6 6 1.41-1.41L10.83 12l4.58-4.59Z"/></svg>
                </a>
            @endif

            @if ($windowStart > 1)
                <a class="activity-pagination__page" href="{{ $paginator->url(1) }}">1</a>
                @if ($windowStart > 2)
                    <span class="activity-pagination__ellipsis" aria-hidden="true">...</span>
                @endif
            @endif

            @foreach ($pageUrls as $page => $url)
                @if ($page === $currentPage)
                    <span class="activity-pagination__page is-current" aria-current="page">{{ $page }}</span>
                @else
                    <a class="activity-pagination__page" href="{{ $url }}">{{ $page }}</a>
                @endif
            @endforeach

            @if ($windowEnd < $lastPage)
                @if ($windowEnd < $lastPage - 1)
                    <span class="activity-pagination__ellipsis" aria-hidden="true">...</span>
                @endif
                <a class="activity-pagination__page" href="{{ $paginator->url($lastPage) }}">{{ $lastPage }}</a>
            @endif

            @if ($paginator->hasMorePages())
                <a class="activity-pagination__control" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Next page">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8.59 16.59 13.17 12 8.59 7.41 10 6l6 6-6 6-1.41-1.41Z"/></svg>
                </a>
            @else
                <span class="activity-pagination__control is-disabled" aria-disabled="true" aria-label="Next page">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8.59 16.59 13.17 12 8.59 7.41 10 6l6 6-6 6-1.41-1.41Z"/></svg>
                </span>
            @endif
        </div>
    </nav>
@endif
