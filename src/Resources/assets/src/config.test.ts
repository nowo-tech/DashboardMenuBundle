/**
 * Unit tests for the JSON config island reader (CSP-safe config, 2.2.0).
 */

import { afterEach, describe, expect, it } from 'vitest';
import { CONFIG_ISLAND_ID, readConfigIsland, readDashboardMenuConfig, resolveCspNonce } from './config';

function setIsland(content: string): void {
  const s = document.createElement('script');
  s.type = 'application/json';
  s.id = CONFIG_ISLAND_ID;
  s.textContent = content;
  document.body.appendChild(s);
}

afterEach(() => {
  document.body.innerHTML = '';
});

describe('readConfigIsland', () => {
  it('returns null when the island is missing', () => {
    expect(readConfigIsland(document)).toBeNull();
  });

  it('returns null when the island is empty', () => {
    setIsland('   ');
    expect(readConfigIsland(document)).toBeNull();
  });

  it('returns null for invalid JSON', () => {
    setIsland('{not json');
    expect(readConfigIsland(document)).toBeNull();
  });

  it('returns null for non-object JSON', () => {
    setIsland('[1,2]');
    expect(readConfigIsland(document)).toBeNull();
    document.body.innerHTML = '';
    setIsland('null');
    expect(readConfigIsland(document)).toBeNull();
  });

  it('parses a valid island', () => {
    setIsland('{"cssFramework":"tailwind","menuId":3,"debug":true}');
    expect(readConfigIsland(document)).toEqual({ cssFramework: 'tailwind', menuId: 3, debug: true });
  });
});

describe('readDashboardMenuConfig', () => {
  it('returns the legacy global when there is no island', () => {
    const legacy = { cssFramework: 'bootstrap5' };
    expect(readDashboardMenuConfig(document, legacy)).toBe(legacy);
  });

  it('returns undefined when neither island nor legacy exist', () => {
    expect(readDashboardMenuConfig(document, undefined)).toBeUndefined();
    expect(readDashboardMenuConfig(undefined, undefined)).toBeUndefined();
  });

  it('prefers island values over the legacy global', () => {
    setIsland('{"cssFramework":"tailwind","menuId":7}');
    expect(readDashboardMenuConfig(document, { cssFramework: 'bootstrap5', debug: true })).toEqual({
      cssFramework: 'tailwind',
      menuId: 7,
      debug: true,
    });
  });

  it('uses the island alone when no legacy global is set', () => {
    setIsland('{"dashboardBase":"/dashboard"}');
    expect(readDashboardMenuConfig(document, undefined)).toEqual({ dashboardBase: '/dashboard' });
  });
});

describe('resolveCspNonce', () => {
  it('returns an empty string when nothing carries a nonce', () => {
    expect(resolveCspNonce(document, null)).toBe('');
    expect(resolveCspNonce(undefined)).toBe('');
  });

  it('prefers the current script nonce', () => {
    const current = document.createElement('script');
    current.nonce = 'from-current';
    const other = document.createElement('style');
    other.setAttribute('nonce', 'from-dom');
    document.body.appendChild(other);
    expect(resolveCspNonce(document, current)).toBe('from-current');
  });

  it('falls back to a nonce-bearing element in the document', () => {
    const style = document.createElement('style');
    style.setAttribute('nonce', 'from-dom');
    document.body.appendChild(style);
    expect(resolveCspNonce(document, null)).toBe('from-dom');
  });
});
