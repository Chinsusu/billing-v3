@props([
    'label',
    'value',
    'meta' => null,
    'tone' => 'primary',
    'href' => null,
])

@if ($href)
    <a {{ $attributes->merge(['class' => "stat-card stat-card--{$tone}", 'href' => $href]) }}>
        <span class="stat-card__value">{{ $value }}</span>
        <span class="stat-card__label">{{ $label }}</span>
        @if ($meta)
            <span class="stat-card__meta">{{ $meta }}</span>
        @endif
    </a>
@else
    <div {{ $attributes->merge(['class' => "stat-card stat-card--{$tone}"]) }}>
        <span class="stat-card__value">{{ $value }}</span>
        <span class="stat-card__label">{{ $label }}</span>
        @if ($meta)
            <span class="stat-card__meta">{{ $meta }}</span>
        @endif
    </div>
@endif
