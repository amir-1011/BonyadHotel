@props(['min' => 0])

@php
    $wireKey = null;
    $wireProp = null;
    $wireLive = false;

    foreach ($attributes->getAttributes() as $key => $val) {
        if (! str_starts_with($key, 'wire:model')) {
            continue;
        }
        $wireKey = $key;
        $wireProp = $val;
        $wireLive = str_contains($key, '.live');
        break;
    }

    $passthrough = $wireKey
        ? $attributes->except($wireKey)
        : $attributes;

    $inputClass = $passthrough->get('class') ?: 'form-control';
@endphp

@if ($wireProp)
    <div
        x-data="moneyInputWire(@js($wireProp), @js($wireLive))"
        wire:ignore
    >
        <input
            type="text"
            inputmode="numeric"
            dir="ltr"
            autocomplete="off"
            {{ $passthrough->except('class')->merge(['class' => $inputClass]) }}
            x-model="display"
            @@input="onInput()"
            @@blur="onBlur()"
            @@focus="$event.target.select()"
        >
    </div>
@else
    <input
        type="text"
        inputmode="numeric"
        dir="ltr"
        autocomplete="off"
        {{ $passthrough->merge(['class' => trim('money-input ' . $inputClass)]) }}
    >
@endif
