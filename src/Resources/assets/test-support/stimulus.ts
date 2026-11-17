/** Test double of `https://esm.sh/@hotwired/stimulus@3` (aliased in vitest.config.ts). */
export class Application {
  static started: Application[] = [];
  readonly registered: Array<[string, unknown]> = [];

  static start(): Application {
    const app = new Application();
    Application.started.push(app);
    return app;
  }

  register(identifier: string, controller: unknown): void {
    this.registered.push([identifier, controller]);
  }
}
