@props([
    'label',
    'value',
    'meta' => null,
    'tone' => 'primary',
    'href' => null,
    'icon' => null,
])

@php
    $icons = [
        'revenue' => '<rect width="20" height="12" x="2" y="6" rx="2"></rect><circle cx="12" cy="12" r="2"></circle><path d="M6 12h.01M18 12h.01"></path>',
        'services' => '<rect width="20" height="8" x="2" y="3" rx="2"></rect><rect width="20" height="8" x="2" y="13" rx="2"></rect><path d="M6 7h.01M6 17h.01"></path>',
        'provisioning' => '<rect width="8" height="6" x="2" y="9" rx="2"></rect><rect width="8" height="6" x="14" y="3" rx="2"></rect><rect width="8" height="6" x="14" y="15" rx="2"></rect><path d="M10 12h2a2 2 0 0 0 2-2V6"></path><path d="M10 12h2a2 2 0 0 1 2 2v4"></path>',
        'queue-risk' => '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><path d="M12 9v4"></path><path d="M12 17h.01"></path>',
        'support' => '<path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"></path><path d="M8 9h8"></path><path d="M8 13h5"></path>',
    ];
    $iconMarkup = $icon ? ($icons[$icon] ?? null) : null;
    $baseClass = trim("stat-card stat-card--{$tone} ".($iconMarkup ? 'stat-card--has-icon' : ''));
@endphp

@if ($href)
    <a {{ $attributes->merge(['class' => $baseClass, 'href' => $href]) }}>
        @if ($iconMarkup)
            <span class="stat-card__icon" data-stat-icon="{{ $icon }}" aria-hidden="true">
                <svg viewBox="0 0 24 24" focusable="false">
                    {!! $iconMarkup !!}
                </svg>
            </span>
        @endif
        <span class="stat-card__value">{{ $value }}</span>
        <span class="stat-card__label">{{ $label }}</span>
        @if ($meta)
            <span class="stat-card__meta">{{ $meta }}</span>
        @endif
    </a>
@else
    <div {{ $attributes->merge(['class' => $baseClass]) }}>
        @if ($iconMarkup)
            <span class="stat-card__icon" data-stat-icon="{{ $icon }}" aria-hidden="true">
                <svg viewBox="0 0 24 24" focusable="false">
                    {!! $iconMarkup !!}
                </svg>
            </span>
        @endif
        <span class="stat-card__value">{{ $value }}</span>
        <span class="stat-card__label">{{ $label }}</span>
        @if ($meta)
            <span class="stat-card__meta">{{ $meta }}</span>
        @endif
    </div>
@endif
