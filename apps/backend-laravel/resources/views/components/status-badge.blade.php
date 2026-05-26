@props([
    'tone' => 'neutral',
])

<span {{ $attributes->merge(['class' => "status-badge status-badge--{$tone}"]) }}>
    {{ $slot }}
</span>
