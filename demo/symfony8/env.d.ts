/// <reference types="vite/client" />
/// <reference types="vite-plugin-symfony/stimulus/env" />

declare module '@nowo-dashboard-menu/logger' {
  export function createBundleLogger(name: string, options?: { buildTime?: string }): {
    scriptLoaded: () => void;
    announce: (message: string, ...args: unknown[]) => void;
    setDebug: (enabled: boolean) => void;
    debug: (...args: unknown[]) => void;
    info: (...args: unknown[]) => void;
    warn: (...args: unknown[]) => void;
    error: (...args: unknown[]) => void;
  };
}
