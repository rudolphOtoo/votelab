// Sign-in, password confirmation and invitation pages: adds passkeys.
import { Livewire, Alpine } from '../../../vendor/livewire/livewire/dist/livewire.esm';
import { registerPasskeys } from '../passkeys';

registerPasskeys(Alpine);

Livewire.start();
