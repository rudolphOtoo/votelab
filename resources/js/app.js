import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
import { registerPasskeys } from './passkeys';
import { registerCropEditor } from './crop';
import { registerStage } from './stage';

// Register Alpine components (Alpine.data) and plugins before Livewire starts Alpine.
registerPasskeys(Alpine);
registerCropEditor(Alpine);
registerStage(Alpine, Livewire);

Livewire.start();
