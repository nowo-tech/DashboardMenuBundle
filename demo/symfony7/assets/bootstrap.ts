import { startStimulusApp, registerControllers } from 'vite-plugin-symfony/stimulus/helpers';
import AutocompleteController from '@symfony/ux-autocomplete';
import { createBundleLogger } from '@nowo-dashboard-menu/logger';

const log = createBundleLogger('demo-symfony7-bootstrap');
log.scriptLoaded();

const app = startStimulusApp();
registerControllers(app, import.meta.glob('./controllers/*_controller.ts', {
  query: '?stimulus',
  eager: true,
}) as Record<string, any>);
app.register('symfony--ux-autocomplete--autocomplete', AutocompleteController);

// Expose Stimulus app so dashboard menu bundle can connect Live Component when modal content is injected via fetch.
if (typeof window !== 'undefined') {
  const w = window as unknown as { $$stimulusApp$$: typeof app; Stimulus: typeof app };
  w.$$stimulusApp$$ = app;
  w.Stimulus = app;
  console.log('[demo symfony7] bootstrap.ts: Stimulus exported to window', {
    hasStimulusApp: !!(w as Window & { $$stimulusApp$$?: unknown }).$$stimulusApp$$,
    hasStimulus: !!(w as Window & { Stimulus?: unknown }).Stimulus,
  });
}
