@props([
    'id',
    'name',
    'label',
    'value' => '',
    'placeholder' => 'Search',
    'options' => [],
    'emptyText' => 'No matching results.',
    'noOptionsText' => 'No options available.',
    'allowFreeText' => false,
    'required' => false,
])

@php
    $currentValue = old($name, $value);
    $inputId = $id.'-search';
    $valueId = $id.'-value';
    $optionsId = $id.'-options';
    $optionList = collect($options)
        ->map(function ($option) {
            $optionValue = (string) data_get($option, 'value', '');
            $optionLabel = (string) data_get($option, 'label', $optionValue);
            $optionMeta = data_get($option, 'meta');
            $optionSearch = (string) data_get($option, 'search', trim($optionValue.' '.$optionLabel.' '.$optionMeta));

            return [
                'value' => $optionValue,
                'label' => $optionLabel,
                'meta' => $optionMeta,
                'search' => strtolower($optionSearch),
            ];
        })
        ->filter(fn (array $option) => $option['value'] !== '')
        ->values();
@endphp

<div
    {{ $attributes->merge(['class' => 'realtime-search']) }}
    data-realtime-search
    @if ($allowFreeText) data-realtime-search-free-text @endif
>
    <label class="realtime-search-label" for="{{ $inputId }}">{{ $label }}</label>
    <input
        id="{{ $inputId }}"
        class="realtime-search-input"
        type="search"
        value="{{ $currentValue }}"
        placeholder="{{ $placeholder }}"
        autocomplete="off"
        aria-controls="{{ $optionsId }}"
        aria-expanded="false"
        data-realtime-search-input
        @required($required)
    >
    <input
        id="{{ $valueId }}"
        name="{{ $name }}"
        type="hidden"
        value="{{ $currentValue }}"
        data-realtime-search-value
    >

    <div id="{{ $optionsId }}" class="realtime-search-options" role="listbox" data-realtime-search-options>
        @forelse ($optionList as $option)
            @php
                $isSelected = $currentValue === $option['value'];
            @endphp
            <button
                type="button"
                class="realtime-search-option"
                role="option"
                data-realtime-search-option
                data-value="{{ $option['value'] }}"
                data-label="{{ $option['label'] }}"
                data-search="{{ $option['search'] }}"
                aria-selected="{{ $isSelected ? 'true' : 'false' }}"
            >
                <strong>{{ $option['label'] }}</strong>
                @if ($option['meta'])
                    <small>{{ $option['meta'] }}</small>
                @endif
            </button>
        @empty
            <div class="realtime-search-empty">{{ $noOptionsText }}</div>
        @endforelse

        @if ($optionList->isNotEmpty())
            <div class="realtime-search-empty" data-realtime-search-empty hidden>{{ $emptyText }}</div>
        @endif
    </div>
</div>
