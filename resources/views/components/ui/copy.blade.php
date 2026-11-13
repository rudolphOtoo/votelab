{{-- Copy a value to the clipboard; "Copied" is announced. Falls back to selecting text where the API is missing. --}}
@props(['value', 'label' => 'Copy', 'copiedLabel' => 'Copied', 'variant' => 'secondary', 'size' => 'sm'])
<span x-data="{ copied: false, async copy() { try { await navigator.clipboard.writeText(@js($value)); } catch { const t = document.createElement('textarea'); t.value = @js($value); document.body.append(t); t.select(); document.execCommand('copy'); t.remove(); } this.copied = true; setTimeout(() => this.copied = false, 2000); } }" class="inline-flex">
    <x-ui.button type="button" :variant="$variant" :size="$size" x-on:click="copy()" {{ $attributes }}>
        <x-ui.icon name="copy" class="size-4" x-show="!copied" /><x-ui.icon name="check" class="size-4" x-show="copied" x-cloak />
        <span x-text="copied ? @js($copiedLabel) : @js($label)">{{ $label }}</span>
    </x-ui.button>
    <span class="sr-only" aria-live="polite" x-text="copied ? @js($copiedLabel) : ''"></span>
</span>
