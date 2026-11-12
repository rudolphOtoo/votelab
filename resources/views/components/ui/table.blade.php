{{--
  A data table that becomes stacked cards below 640 px. :columns="['key' => 'Label']", :rows="[[...], ...]".
  numeric: list of column keys aligned right with tabular numbers. Cells are escaped text.
--}}
@props(['columns', 'rows', 'caption' => null, 'numeric' => [], 'empty' => 'Nothing to show yet.'])
@if ($rows === [] || (is_countable($rows) && count($rows) === 0))
    <p class="rounded-[var(--radius-md)] border border-dashed border-hairline p-4 text-sm text-ink-secondary">{{ $empty }}</p>
@else
    <div {{ $attributes }}>
        <ul class="space-y-2 sm:hidden" @if ($caption) aria-label="{{ $caption }}" @endif>
            @foreach ($rows as $row)
                <li class="rounded-[var(--radius-md)] border border-hairline bg-surface p-3">
                    <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm">
                        @foreach ($columns as $key => $label)
                            <dt class="text-ink-secondary">{{ $label }}</dt>
                            <dd class="text-right text-ink {{ in_array($key, $numeric, true) ? 'tabular-nums' : '' }}">{{ $row[$key] ?? '' }}</dd>
                        @endforeach
                    </dl>
                </li>
            @endforeach
        </ul>
        <div class="hidden overflow-x-auto rounded-[var(--radius-md)] border border-hairline bg-surface sm:block">
            <table class="w-full text-left text-sm">
                @if ($caption)<caption class="sr-only">{{ $caption }}</caption>@endif
                <thead class="bg-subtle text-ink-secondary">
                    <tr>@foreach ($columns as $key => $label)<th scope="col" class="px-4 py-2.5 font-medium {{ in_array($key, $numeric, true) ? 'text-right' : '' }}">{{ $label }}</th>@endforeach</tr>
                </thead>
                <tbody class="divide-y divide-hairline">
                    @foreach ($rows as $row)
                        <tr>@foreach ($columns as $key => $label)<td class="px-4 py-2.5 text-ink {{ in_array($key, $numeric, true) ? 'text-right tabular-nums' : '' }}">{{ $row[$key] ?? '' }}</td>@endforeach</tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
