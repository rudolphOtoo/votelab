// Public pages (voters): Livewire + Alpine only. Budget: 100 KB gzipped (scripts/perf/bundle-budget.mjs, D-124).
import { Livewire } from '../../../vendor/livewire/livewire/dist/livewire.esm';
import { refreshAfterBackForwardCache } from '../public';

refreshAfterBackForwardCache(Livewire);

Livewire.start();
