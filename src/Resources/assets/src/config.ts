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
