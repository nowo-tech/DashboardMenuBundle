import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

type StimulusStub = typeof import('../test-support/stimulus');

async function load(): Promise<{ stimulus: StimulusStub; live: { default: unknown } }> {
  vi.resetModules();
  const stimulus = await import('../test-support/stimulus');
  const live = await import('../test-support/live-controller');
  await import('./stimulus-live');
  return { stimulus, live };
}

describe('stimulus-live entry', () => {
  beforeEach(() => {
    vi.spyOn(console, 'log').mockImplementation(() => {});
    vi.spyOn(console, 'debug').mockImplementation(() => {});
  });

  afterEach(() => {
    delete (window as { Stimulus?: unknown }).Stimulus;
    delete (window as { $$stimulusApp$$?: unknown }).$$stimulusApp$$;
    delete (window as { __stimulusApp__?: unknown }).__stimulusApp__;
    vi.restoreAllMocks();
  });

  it('starts a new Stimulus application and exposes it when the page has none', async () => {
    const { stimulus, live } = await load();

    const app = stimulus.Application.started[0];
    expect(app).toBeDefined();
    expect(app.registered).toEqual([['live', live.default]]);
    expect(window.Stimulus).toBe(app);
    expect(window.$$stimulusApp$$).toBe(app);
  });

  it.each(['Stimulus', '$$stimulusApp$$', '__stimulusApp__'] as const)(
    'registers the live controller on the existing app found at window.%s',
    async (key) => {
      const existing = { register: vi.fn() };
      (window as unknown as Record<string, unknown>)[key] = existing;

      const { stimulus, live } = await load();

      expect(existing.register).toHaveBeenCalledWith('live', live.default);
      expect(stimulus.Application.started).toHaveLength(0);
    },
  );
});
