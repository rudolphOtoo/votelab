{{-- Labelled input; its error (or hint) is linked with aria-describedby and marks it aria-invalid. --}}
@props(['name', 'label', 'type' => 'text', 'id' => null, 'hint' => null, 'errorBag' => 'default'])

@php
    $id ??= 'field-'.str_replace(['.', '_'], '-', $name);
    $error = $errors->getBag($errorBag)->first($name);
    $describedBy = trim(($error ? "{$id}-error " : '').($hint ? "{$id}-hint" : ''));
@endphp

<div {{ $attributes->only('class')->merge(['class' => 'mt-4 first:mt-0']) }}>
    <label for="{{ $id }}" class="block text-sm font-medium text-ink">{{ $label }}</label>
    @if ($hint)
        <p id="{{ $id }}-hint" class="mt-1 text-xs text-ink-secondary">{{ $hint }}</p>
    @endif
    <input
        id="{{ $id }}"
        name="{{ $name }}"
        type="{{ $type }}"
        @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
        @if ($error) aria-invalid="true" @endif
        {{ $attributes->except('class')->merge(['class' => 'mt-2 h-12 w-full rounded-[var(--radius-md)] border border-hairline bg-surface px-3 text-base text-ink focus:border-focused focus:outline-none aria-invalid:border-danger']) }}
    >
    @if ($error)
        <p id="{{ $id }}-error" class="mt-2 text-sm text-danger">{{ $error }}</p>
    @endif
</div>
