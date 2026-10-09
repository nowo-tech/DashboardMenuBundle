/**
 * CSP-safe config reader for the dashboard menu bundle.
 *
 * Reads the JSON island `<script type="application/json" id="nowo-dashboard-menu-config">`
 * and falls back to the legacy `window.__nowoDashboardMenuConfig` global (deprecated since 2.2.0,
 * removed in a future minor).
 */

export const CONFIG_ISLAND_ID = 'nowo-dashboard-menu-config';

/**
 * Parse the JSON island, returning null when it is missing, empty or invalid.
 */
export function readConfigIsland(doc: Document): Record<string, unknown> | null {
  const el = doc.getElementById(CONFIG_ISLAND_ID);
  const raw = el?.textContent?.trim();
  if (!raw) return null;
  try {
    const parsed: unknown = JSON.parse(raw);
    return parsed !== null && typeof parsed === 'object' && !Array.isArray(parsed)
      ? (parsed as Record<string, unknown>)
      : null;
  } catch {
    return null;
  }
}

/**
 * Resolve the dashboard config: island values win over the legacy global.
 */
export function readDashboardMenuConfig<T extends object>(
  doc: Document | undefined,
  legacy: T | undefined,
): T | undefined {
  const island = doc ? readConfigIsland(doc) : null;
  if (island === null) return legacy;
  return { ...(legacy ?? {}), ...island } as T;
}

/**
 * CSP nonce to copy onto `<script>` / `<style>` elements injected at runtime, so they load under
 * `script-src 'nonce-…'` / `style-src-elem 'nonce-…'`. Prefers the executing script's nonce
 * (`document.currentScript`, only available while the bundle evaluates), then any nonce'd element.
 * Browsers hide the nonce attribute value, so the `nonce` property is read instead.
 */
export function resolveCspNonce(doc: Document | undefined, current?: Element | null): string {
  const fromCurrent = (current as HTMLElement | null | undefined)?.nonce;
  if (fromCurrent) return fromCurrent;
  const el = doc?.querySelector<HTMLElement>('script[nonce], style[nonce]');
  return el?.nonce || el?.getAttribute('nonce') || '';
}
