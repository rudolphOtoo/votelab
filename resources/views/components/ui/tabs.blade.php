{{--
  Accessible tabs (ARIA tablist; arrow keys, Home/End). :tabs="['a' => 'Label']"; put <x-ui.tab-panel name="a">
  in the slot. For tabs that are links to other pages, use <x-ui.nav-tabs> instead.
--}}
@props(['tabs', 'selected' => null, 'label' => 'Sections'])
@php $first = $selected ?? array_key_first($tabs); @endphp
<div x-data="{ tab: @js($first), keys: @js(array_keys($tabs)), move(d) { const i = (this.keys.indexOf(this.tab) + d + this.keys.length) % this.keys.length; this.tab = this.keys[i]; this.$nextTick(() => this.$refs['tab-' + this.tab].focus()); } }" {{ $attributes }}>
    <div role="tablist" aria-label="{{ $label }}" class="-mx-4 flex gap-1 overflow-x-auto border-b border-hairline px-4 sm:mx-0 sm:px-0"
         x-on:keydown.right.prevent="move(1)" x-on:keydown.left.prevent="move(-1)" x-on:keydown.home.prevent="tab = keys[0]" x-on:keydown.end.prevent="tab = keys[keys.length - 1]">
        @foreach ($tabs as $key => $tabLabel)
            <button type="button" role="tab" id="tab-{{ $key }}" x-ref="tab-{{ $key }}" aria-controls="panel-{{ $key }}"
                    :aria-selected="(tab === @js($key)).toString()" aria-selected="{{ $key === $first ? 'true' : 'false' }}" :tabindex="tab === @js($key) ? 0 : -1" tabindex="{{ $key === $first ? 0 : -1 }}"
                    x-on:click="tab = @js($key)"
                    class="min-h-11 shrink-0 border-b-2 border-transparent px-3 text-sm font-medium text-ink-secondary hover:text-ink aria-selected:border-primary aria-selected:text-ink">{{ $tabLabel }}</button>
        @endforeach
    </div>
    <div class="pt-4">{{ $slot }}</div>
</div>
