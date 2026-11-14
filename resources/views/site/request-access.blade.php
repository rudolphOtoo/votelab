{{-- Request access (plan/SITE.md decisions 2 and 8): a plain form. Honeypot ("website", hidden from people) and a
     signed start time catch bots; limits per device and per network (AccessRequestController). --}}
<x-site.layout :scope="$scope" title="Request access" :canonical="route('site.request-access')"
    description="Tell us about your awards and we'll get you set up on VoteLab.">
    <x-site.band eyebrow="For organizers" title="Request access" lead="Tell us about your awards and we'll reply by email or phone." />

    <div class="mx-auto max-w-2xl px-4 py-10 sm:px-6 sm:py-14">
        @if ($sent)
            <div role="status" class="rounded-[var(--radius-lg)] border border-hairline bg-surface p-6">
                <x-ui.icon name="success" class="size-8 text-success" />
                <h2 class="mt-3 text-xl font-semibold text-ink">Thank you, we've got your request.</h2>
                <p class="mt-2 text-ink-secondary">We'll be in touch soon. If it's urgent, <a class="font-semibold text-primary-text underline" href="{{ route('site.contact') }}">contact us</a>.</p>
            </div>
        @else
            @if ($errors->any())
                <div role="alert" class="mb-6 rounded-[var(--radius-md)] bg-danger-bg p-4 text-danger-fg">
                    <p class="font-semibold">Please check the form.</p>
                    <ul class="mt-1 list-disc pl-5 text-sm">
                        @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif
            <form method="post" action="{{ route('site.request-access.store') }}" class="grid gap-5" novalidate>
                @csrf
                <input type="hidden" name="started" value="{{ $started }}">
                {{-- Hidden from people (and screen readers); bots fill it in. --}}
                <div class="absolute -left-[10000px] h-px w-px overflow-hidden" aria-hidden="true">
                    <label for="website">Website</label>
                    <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
                </div>

                @foreach ([
                    ['name', 'Your name', 'text', 'name', true],
                    ['organization', 'Organization or event name', 'text', 'organization', true],
                    ['email', 'Email', 'email', 'email', true],
                    ['phone', 'Phone or WhatsApp', 'tel', 'tel', true],
                ] as [$field, $label, $type, $autocomplete, $required])
                    <div>
                        <label for="{{ $field }}" class="block text-sm font-medium text-ink">{{ $label }}</label>
                        <x-ui.input :id="$field" :name="$field" :type="$type" :value="old($field)" :autocomplete="$autocomplete"
                            :required="$required" :invalid="$errors->has($field)" class="mt-2"
                            :aria-describedby="$errors->has($field) ? $field.'-error' : null" />
                        @error($field)<p id="{{ $field }}-error" class="mt-1 text-sm text-danger-fg">{{ $message }}</p>@enderror
                    </div>
                @endforeach

                <div>
                    <label for="event_type" class="block text-sm font-medium text-ink">Type of event</label>
                    <x-ui.select id="event_type" name="event_type" class="mt-2" required :invalid="$errors->has('event_type')" :aria-describedby="$errors->has('event_type') ? 'event_type-error' : null">
                        <option value="">Choose one</option>
                        @foreach ($eventTypes as $value => $label)
                            <option value="{{ $value }}" @selected(old('event_type') === $value)>{{ $label }}</option>
                        @endforeach
                    </x-ui.select>
                    @error('event_type')<p id="event_type-error" class="mt-1 text-sm text-danger-fg">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="expected_date" class="block text-sm font-medium text-ink">When does voting start? <span class="font-normal text-ink-secondary">(optional)</span></label>
                    <x-ui.input id="expected_date" name="expected_date" type="date" :value="old('expected_date')" :invalid="$errors->has('expected_date')" class="mt-2"
                        :aria-describedby="$errors->has('expected_date') ? 'expected_date-error' : null" />
                    @error('expected_date')<p id="expected_date-error" class="mt-1 text-sm text-danger-fg">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="message" class="block text-sm font-medium text-ink">Anything else? <span class="font-normal text-ink-secondary">(optional)</span></label>
                    <x-ui.textarea id="message" name="message" rows="4" class="mt-2" maxlength="2000">{{ old('message') }}</x-ui.textarea>
                    @error('message')<p class="mt-1 text-sm text-danger-fg">{{ $message }}</p>@enderror
                </div>

                <p class="text-sm text-ink-secondary">We use these details only to reply about your awards, and delete them {{ config('site.access_request_retention_days') }} days after you send them. See our <a class="underline" href="{{ route('site.privacy') }}">privacy notice</a>.</p>
                <button type="submit" class="inline-flex min-h-12 items-center justify-center rounded-[var(--radius-md)] bg-primary px-5 font-semibold text-primary-fg hover:bg-primary-hover sm:justify-self-start">Send request</button>
            </form>
        @endif
    </div>
</x-site.layout>
