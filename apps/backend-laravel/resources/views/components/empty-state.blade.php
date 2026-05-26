@props([
    'title' => 'No records yet.',
    'message' => null,
])

<div {{ $attributes->merge(['class' => 'empty-state']) }}>
    <strong>{{ $title }}</strong>
    @if ($message)
        <div>{{ $message }}</div>
    @endif
    @isset($actions)
        <div class="action-row" style="justify-content:center; margin-top: 14px;">
            {{ $actions }}
        </div>
    @endisset
</div>
