// Bootstrap (Axios, CSRF etc.)
import './bootstrap';

// ✅ AlpineJS korrekt importieren und starten
import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.start();

// Kein Import von "livewire" nötig!
