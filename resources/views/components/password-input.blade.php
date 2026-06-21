@props([
    'label' => '',
])

<div {{ $attributes->only('class')->merge(['class' => '']) }} x-data="{ show: false }">
    @if($label)
        <label class="form-label small text-muted">{{ $label }}</label>
    @endif
    <div class="input-group">
        <input
            {{ $attributes->except('class')->class(['form-control'])->merge(['autocomplete' => 'off']) }}
            :type="show ? 'text' : 'password'"
        >
        <button
            type="button"
            class="btn btn-outline-secondary"
            tabindex="-1"
            @click="show = !show"
            :title="show ? 'مخفی کردن' : 'نمایش'"
            :aria-label="show ? 'مخفی کردن رمز' : 'نمایش رمز'"
        >
            <i class="bi" :class="show ? 'bi-eye-slash' : 'bi-eye'"></i>
        </button>
    </div>
</div>
