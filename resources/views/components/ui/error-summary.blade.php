{{-- Lists every error with a link to its field. Takes focus on load so screen readers announce it. --}}
@props(['bag' => 'default', 'title' => 'Please fix the following'])
@php $messages = ($errors ?? new \Illuminate\Support\ViewErrorBag())->getBag($bag)->messages(); @endphp
@if ($messages !== [])
    <div role="alert" tabindex="-1" autofocus data-error-summary {{ $attributes->merge(['class' => 'rounded-[var(--radius-md)] border border-danger/30 bg-danger-bg p-4 text-sm text-danger-fg']) }}>
        <p class="flex items-center gap-2 font-semibold"><x-ui.icon name="alert" class="size-5" /> {{ $title }}</p>
        <ul class="mt-2 list-disc space-y-1 pl-7">
            @foreach ($messages as $field => $fieldMessages)
                <li><a href="#field-{{ str_replace(['.', '_', '[', ']'], '-', $field) }}" class="underline underline-offset-2">{{ $fieldMessages[0] }}</a></li>
            @endforeach
        </ul>
    </div>
@endif
