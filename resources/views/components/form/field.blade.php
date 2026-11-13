{{-- Labelled input; its error (or hint) is linked with aria-describedby and marks it aria-invalid. --}}
@props(['name', 'label', 'type' => 'text', 'id' => null, 'hint' => null, 'errorBag' => 'default'])

@php
    $id ??= 'field-'.str_replace(['.', '_'], '-', $name);
    $error = ($errors ?? new \Illuminate\Support\ViewErrorBag())->getBag($errorBag)->first($name);
    $describedBy = trim(($error ? "{$id}-error " : '').($hint ? "{$id}-hint" : ''));
@endphp

<div {{ $attributes->only('class')->merge(['class' => 'mt-4 first:mt-0']) }}>
    <label for="{{ $id }}" class="block text-sm font-medium text-ink">{{ $label }}</label>
    @if ($hint)
        <p id="{{ $id }}-hint" class="mt-1 text-sm text-ink-secondary">{{ $hint }}</p>
    @endif
    <input
        id="{{ $id }}"
        name="{{ $name }}"
        type="{{ $type }}"
        @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
        @if ($error) aria-invalid="true" @endif
        {{ $attributes->except('class')->merge(['class' => \App\Support\Design\ControlClasses::BASE.' mt-2 min-h-12']) }}
    >
    @if ($error)
        <p id="{{ $id }}-error" class="mt-2 flex items-start gap-1.5 text-sm font-medium text-danger-fg"><x-ui.icon name="alert" class="mt-0.5 size-4" /> {{ $error }}</p>
    @endif
</div>
