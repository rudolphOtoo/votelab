// Organizer dashboard: adds passkeys (security page) and the photo crop editor.
import { Livewire, Alpine } from '../../../vendor/livewire/livewire/dist/livewire.esm';
import { registerPasskeys } from '../passkeys';
import { registerCropEditor } from '../crop';

// Register Alpine components (Alpine.data) and plugins before Livewire starts Alpine.
registerPasskeys(Alpine);
registerCropEditor(Alpine);

Livewire.start();
