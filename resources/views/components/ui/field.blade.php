{{--
  A labelled control with hint and error. The error (from the given error bag) is announced through
  aria-describedby and marks the control aria-invalid. as: input | select | textarea.
  Other attributes (wire:model, autocomplete, inputmode, …) pass through to the control.
--}}
@props(['name', 'label', 'as' => 'input', 'type' => 'text', 'id' => null, 'hint' => null, 'errorBag' => 'default', 'required' => false, 'optional' => false])

@php
    $id ??= 'field-'.str_replace(['.', '_', '[', ']'], '-', $name);
    $error = ($errors ?? new \Illuminate\Support\ViewErrorBag())->getBag($errorBag)->first($name);
    $describedBy = trim(($hint ? "{$id}-hint " : '').($error ? "{$id}-error" : ''));
    $control = $attributes->except('class')->merge(['id' => $id, 'name' => $name, 'aria-describedby' => $describedBy !== '' ? $describedBy : null, 'required' => $required]);
@endphp

<div {{ $attributes->only('class')->merge(['class' => 'space-y-2']) }}>
    <label for="{{ $id }}" class="block text-sm font-medium text-ink">
        {{ $label }}
        @if ($optional)<span class="font-normal text-ink-secondary">(optional)</span>@endif
    </label>
    @if ($hint)
        <p id="{{ $id }}-hint" class="text-sm text-ink-secondary">{{ $hint }}</p>
    @endif
    @if ($as === 'select')
        <x-ui.select :attributes="$control" :invalid="(bool) $error">{{ $slot }}</x-ui.select>
    @elseif ($as === 'textarea')
        <x-ui.textarea :attributes="$control" :invalid="(bool) $error">{{ $slot }}</x-ui.textarea>
    @else
        <x-ui.input :attributes="$control" :type="$type" :invalid="(bool) $error" />
    @endif
    @if ($error)
        <p id="{{ $id }}-error" class="flex items-start gap-1.5 text-sm font-medium text-danger-fg">
            <x-ui.icon name="alert" class="mt-0.5 size-4" /> {{ $error }}
        </p>
    @endif
</div>
