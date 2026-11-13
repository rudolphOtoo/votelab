// Sign-in, password confirmation and invitation pages: adds passkeys.
import { Livewire, Alpine } from '../../../vendor/livewire/livewire/dist/livewire.esm';
import { preloadPasskeys, registerPasskeys } from '../passkeys';

registerPasskeys(Alpine);
preloadPasskeys(); // sign-in and confirmation pages offer a passkey at once: fetch the client now

Livewire.start();
