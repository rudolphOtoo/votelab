{{-- Overrides the --brand-* tokens with an organization's accessible palette (App\Support\Design\Branding). --}}
@props(['organization' => null])
@php $palette = \App\Support\Design\Branding::for($organization); @endphp
@if ($palette->custom)
    <style>:root { {{ $palette->declarations() }} }</style>
@endif
