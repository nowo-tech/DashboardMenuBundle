/**
 * Bootstrap Stimulus + Symfony UX Live controller for the dashboard menu bundle.
 * If the page already has a Stimulus app (window.Stimulus / $$stimulusApp$$), only registers the 'live'
 * controller on it. Otherwise creates a new Application, registers 'live', and exposes it on window.
 * Loaded as type="module"; dependencies are resolved via esm.sh CDN (bundled at build time).
 */
import { createBundleLogger } from './logger';
import { Application } from 'https://esm.sh/@hotwired/stimulus@3';
import LiveController from 'https://esm.sh/@symfony/ux-live-component@2';

declare const __STIMULUS_LIVE_BUILD_TIME__: string | undefined;

const log = createBundleLogger('dashboard-menu-stimulus-live', {
  buildTime: typeof __STIMULUS_LIVE_BUILD_TIME__ !== 'undefined' ? __STIMULUS_LIVE_BUILD_TIME__ : undefined,
});

declare global {
  interface Window {
    Stimulus?: Application;
    $$stimulusApp$$?: Application;
    __stimulusApp__?: Application;
  }
}

function getExistingStimulusApp(): Application | null {
  if (typeof window === 'undefined') return null;
  const w = window as Window;
  return (w.Stimulus ?? w.$$stimulusApp$$ ?? w.__stimulusApp__ ?? null) as Application | null;
}

const existingApp = getExistingStimulusApp();

if (existingApp) {
  existingApp.register('live', LiveController);
  log.debug('registered "live" controller on existing Stimulus app');
} else {
  const app = Application.start();
  app.register('live', LiveController);
  if (typeof window !== 'undefined') {
    (window as Window).Stimulus = app;
    (window as Window).$$stimulusApp$$ = app;
  }
  log.debug('created new Stimulus app and registered "live" controller');
}

log.scriptLoaded();
