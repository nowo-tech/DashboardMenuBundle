/**
 * =============================================================================
 * BUNDLE LOGGER - Reusable across bundles
 * =============================================================================
 *
 * USAGE (English):
 *
 * 1. Create a logger for your bundle (typically at the top of your entry file):
 *
 *    import { createBundleLogger } from './logger';
 *    declare const __MY_BUNDLE_BUILD_TIME__: string;  // optional, injected by build define
 *
 *    const log = createBundleLogger('my-bundle', {
 *      buildTime: typeof __MY_BUNDLE_BUILD_TIME__ !== 'undefined' ? __MY_BUNDLE_BUILD_TIME__ : undefined,
 *    });
 *
 * 2. Log that the script loaded (call once at startup; includes build time if provided):
 *
 *    log.scriptLoaded();
 *
 * 2b. Optional one-line notices that must appear even when debug is off:
 *
 *    log.announce('Feature X enabled', { count: 3 });
 *
 * 3. Call setDebug(true) when your bundle's debug mode is on (e.g. from config or data attribute).
 *    When debug is false, only scriptLoaded() output is shown; info/debug/warn/error are no-ops.
 *
 * 4. Use level methods for the rest of the bundle:
 *
 *    log.debug('detailed info', { foo: 1 });
 *    log.info('something happened');
 *    log.warn('unexpected but handled', error);
 *    log.error('failure', error);
 *
 * 5. To inject build time with esbuild, add to the build command:
 *
 *    --define:__MY_BUNDLE_BUILD_TIME__="\"$(date -u +%Y-%m-%dT%H:%M:%SZ)\""
 *
 * All methods support multiple arguments (like console.log). Objects are stringified
 * for readability in debug/info/warn/error. The prefix is [name] and each level
 * has a distinct style and emoji when the console supports it.
 * =============================================================================
 */

export type BundleLoggerOptions = {
  /** If set, scriptLoaded() will include this (e.g. build/compilation time). */
  buildTime?: string;
};

export type BundleLogger = {
  /** Call once at startup. Logs "script loaded" and optional build time. Always shown. */
  scriptLoaded: () => void;
  /**
   * Short notice always printed (ignores setDebug). Use for feature activation lines in the console.
   */
  announce: (message: string, ...args: unknown[]) => void;
  /** When false, debug/info/warn/error are no-ops (only scriptLoaded is shown). */
  setDebug: (enabled: boolean) => void;
  debug: (...args: unknown[]) => void;
  info: (...args: unknown[]) => void;
  warn: (...args: unknown[]) => void;
  error: (...args: unknown[]) => void;
};

const STYLES = {
  script: 'color:#0ea5e9;font-weight:bold',
  debug: 'color:#6b7280',
  info: 'color:#2563eb',
  warn: 'color:#d97706',
  error: 'color:#dc2626;font-weight:bold',
} as const;

const EMOJI = {
  script: '📦',
  debug: '🔍',
  info: 'ℹ️',
  warn: '⚠️',
  error: '❌',
} as const;

function formatArgs(args: unknown[]): unknown[] {
  return args.map((a) =>
    typeof a === 'object' && a !== null && !(a instanceof Error) ? JSON.stringify(a) : a,
  );
}

/**
 * Creates a logger for a bundle. Use the same logger instance across the bundle.
 *
 * @param name - Short name for log prefix (e.g. 'dashboard-menu').
 * @param options - Optional buildTime for scriptLoaded().
 */
export function createBundleLogger(name: string, options: BundleLoggerOptions = {}): BundleLogger {
  const prefix = `[${name}]`;
  const { buildTime } = options;
  let debugEnabled = false;

  return {
    scriptLoaded(): void {
      if (buildTime !== undefined && buildTime !== '') {
        console.log(
          `%c${EMOJI.script} ${prefix} script loaded, build time: %c${buildTime}`,
          STYLES.script,
          'color:#059669',
        );
      } else {
        console.log(`%c${EMOJI.script} ${prefix} script loaded`, STYLES.script);
      }
    },

    announce(message: string, ...args: unknown[]): void {
      if (args.length > 0) {
        console.log(`%c${EMOJI.info} ${prefix}%c ${message}`, STYLES.info, 'color:inherit', ...formatArgs(args));
      } else {
        console.log(`%c${EMOJI.info} ${prefix}%c ${message}`, STYLES.info, 'color:inherit');
      }
    },

    setDebug(enabled: boolean): void {
      debugEnabled = enabled;
    },

    debug(...args: unknown[]): void {
      if (!debugEnabled) return;
      if (args.length > 0) {
        console.debug(`%c${EMOJI.debug} ${prefix}`, STYLES.debug, ...formatArgs(args));
      } else {
        console.debug(`%c${EMOJI.debug} ${prefix}`, STYLES.debug);
      }
    },

    info(...args: unknown[]): void {
      if (!debugEnabled) return;
      if (args.length > 0) {
        console.info(`%c${EMOJI.info} ${prefix}`, STYLES.info, ...formatArgs(args));
      } else {
        console.info(`%c${EMOJI.info} ${prefix}`, STYLES.info);
      }
    },

    warn(...args: unknown[]): void {
      if (!debugEnabled) return;
      if (args.length > 0) {
        console.warn(`%c${EMOJI.warn} ${prefix}`, STYLES.warn, ...formatArgs(args));
      } else {
        console.warn(`%c${EMOJI.warn} ${prefix}`, STYLES.warn);
      }
    },

    error(...args: unknown[]): void {
      if (!debugEnabled) return;
      if (args.length > 0) {
        console.error(`%c${EMOJI.error} ${prefix}`, STYLES.error, ...formatArgs(args));
      } else {
        console.error(`%c${EMOJI.error} ${prefix}`, STYLES.error);
      }
    },
  };
}
