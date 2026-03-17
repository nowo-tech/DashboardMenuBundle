import { startStimulusApp, registerControllers } from 'vite-plugin-symfony/stimulus/helpers';

const app = startStimulusApp();
registerControllers(app, import.meta.glob('./controllers/*_controller.ts', {
  query: '?stimulus',
  eager: true,
}));

if (import.meta.hot) {
  (window as unknown as { $$stimulusApp$$: typeof app }).$$stimulusApp$$ = app;
}
