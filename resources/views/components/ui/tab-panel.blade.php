@props(['name'])
<div role="tabpanel" id="panel-{{ $name }}" aria-labelledby="tab-{{ $name }}" tabindex="0" x-show="tab === @js($name)" {{ $attributes }}>{{ $slot }}</div>
