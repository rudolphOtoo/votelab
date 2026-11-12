import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
import { registerPasskeys } from './passkeys';

// Register Alpine components (Alpine.data) and plugins before Livewire starts Alpine.
registerPasskeys(Alpine);

Livewire.start();
