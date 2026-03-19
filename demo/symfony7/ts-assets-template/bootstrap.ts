import { startStimulusApp, registerControllers } from 'vite-plugin-symfony/stimulus/helpers';

const app = startStimulusApp();
registerControllers(app, import.meta.glob('./controllers/*_controller.ts', {
  query: '?stimulus',
  eager: true,
}));

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
