// Stage (projector) screen: adds the reveal, confetti and the screen wake lock.
import { Livewire, Alpine } from '../../../vendor/livewire/livewire/dist/livewire.esm';
import { registerStage } from '../stage';

registerStage(Alpine, Livewire);

Livewire.start();
