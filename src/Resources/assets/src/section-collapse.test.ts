import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import {
  autoInitSections,
  initSectionCollapse,
  SECTION_TOGGLE_EVENT,
  setSectionCollapsed,
  toggleSection,
} from './section-collapse';

const KEY = 'app.sections';

function section(id: number, defaultState: 'collapsed' | 'expanded', extra = '', storageKey = KEY): string {
  return `
    <li>
      <button type="button" class="menu-section-toggle" aria-expanded="${defaultState === 'collapsed' ? 'false' : 'true'}"
        aria-controls="p${id}" data-dashboard-menu-section="sidebar:${id}" data-dashboard-menu-section-default="${defaultState}"
        data-dashboard-menu-section-storage-key="${storageKey}"${extra}>Section ${id}</button>
      <ul id="p${id}"${defaultState === 'collapsed' ? ' hidden' : ''}><li><a href="/x${id}">x</a></li></ul>
    </li>`;
}

const btn = (id: number): HTMLElement => document.querySelector<HTMLElement>(`[data-dashboard-menu-section="sidebar:${id}"]`) as HTMLElement;
const panel = (id: number): HTMLElement => document.getElementById(`p${id}`) as HTMLElement;

describe('section collapse', () => {
  beforeEach(() => {
    localStorage.clear();
  });
  afterEach(() => {
    document.body.innerHTML = '';
    vi.restoreAllMocks();
  });

  it('keeps the configured default when nothing is remembered', () => {
    document.body.innerHTML = section(1, 'collapsed') + section(2, 'expanded');
    initSectionCollapse();

    expect(btn(1).getAttribute('aria-expanded')).toBe('false');
    expect(panel(1).hidden).toBe(true);
    expect(btn(1).classList.contains('is-collapsed')).toBe(true);
    expect(btn(2).getAttribute('aria-expanded')).toBe('true');
    expect(panel(2).hidden).toBe(false);
  });

  it('applies the remembered choice over the default', () => {
    localStorage.setItem(KEY, JSON.stringify({ 'sidebar:1': false, 'sidebar:2': true, 'sidebar:3': 'junk' }));
    document.body.innerHTML = section(1, 'collapsed') + section(2, 'expanded') + section(3, 'collapsed');
    initSectionCollapse();

    expect(panel(1).hidden).toBe(false);
    expect(panel(2).hidden).toBe(true);
    // A non-boolean entry is ignored.
    expect(panel(3).hidden).toBe(true);
  });

  it('never collapses the section holding the current item on load', () => {
    localStorage.setItem(KEY, JSON.stringify({ 'sidebar:1': true }));
    document.body.innerHTML = section(1, 'expanded', ' data-dashboard-menu-section-current');
    initSectionCollapse();

    expect(panel(1).hidden).toBe(false);
    expect(btn(1).getAttribute('aria-expanded')).toBe('true');
  });

  it.each([
    ['corrupt JSON', 'not json'],
    ['an array', '[1]'],
    ['null', 'null'],
  ])('falls back to the default on %s', (_name, raw) => {
    localStorage.setItem(KEY, raw);
    document.body.innerHTML = section(1, 'collapsed');
    initSectionCollapse();

    expect(panel(1).hidden).toBe(true);
  });

  it('falls back to the default when storage throws', () => {
    vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
      throw new Error('denied');
    });
    document.body.innerHTML = section(1, 'collapsed');
    initSectionCollapse();

    expect(panel(1).hidden).toBe(true);
  });

  it('does not persist without a storage key', () => {
    document.body.innerHTML = section(1, 'collapsed', '', '');
    initSectionCollapse();
    expect(toggleSection(btn(1))).toBe(false);

    expect(panel(1).hidden).toBe(false);
    expect(localStorage.length).toBe(0);
  });

  it('toggles, remembers the choice per section and announces it', () => {
    document.body.innerHTML = section(1, 'expanded') + section(2, 'expanded');
    initSectionCollapse();
    const events: unknown[] = [];
    document.addEventListener(SECTION_TOGGLE_EVENT, (event) => events.push((event as CustomEvent).detail));

    expect(toggleSection(btn(1))).toBe(true);
    expect(panel(1).hidden).toBe(true);
    expect(toggleSection(btn(2))).toBe(true);
    expect(toggleSection(btn(2))).toBe(false);

    expect(JSON.parse(localStorage.getItem(KEY) as string)).toEqual({ 'sidebar:1': true, 'sidebar:2': false });
    expect(events).toEqual([
      { key: 'sidebar:1', collapsed: true },
      { key: 'sidebar:2', collapsed: true },
      { key: 'sidebar:2', collapsed: false },
    ]);
  });

  it('still toggles when storage cannot be written', () => {
    document.body.innerHTML = section(1, 'expanded');
    vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
      throw new Error('quota');
    });

    expect(toggleSection(btn(1))).toBe(true);
    expect(panel(1).hidden).toBe(true);
  });

  it('tolerates a button without a panel or attributes', () => {
    document.body.innerHTML = '<button type="button" data-dashboard-menu-section aria-controls="missing">x</button><button data-dashboard-menu-section>y</button>';
    initSectionCollapse();
    const bare = document.querySelectorAll<HTMLElement>('[data-dashboard-menu-section]');

    expect(toggleSection(bare[0])).toBe(true);
    expect(toggleSection(bare[1])).toBe(true);
    setSectionCollapsed(bare[1], false);
    expect(bare[1].getAttribute('aria-expanded')).toBe('true');
  });

  it('wires one delegated click handler, applies the state and re-applies it after Turbo visits', () => {
    document.body.innerHTML = section(1, 'collapsed') + '<p id="other">z</p>';
    const win = window;
    autoInitSections(document, win);
    autoInitSections(document, win);

    expect(panel(1).hidden).toBe(true);
    (btn(1)).click();
    expect(panel(1).hidden).toBe(false);
    // Clicking elsewhere (also a non-Element target) does nothing.
    (document.getElementById('other') as HTMLElement).click();
    document.dispatchEvent(new Event('click'));
    expect(panel(1).hidden).toBe(false);

    document.body.innerHTML = section(5, 'collapsed');
    document.dispatchEvent(new Event('turbo:load'));
    expect(panel(5).hidden).toBe(true);
    localStorage.setItem(KEY, JSON.stringify({ 'sidebar:5': false }));
    document.dispatchEvent(new Event('turbo:frame-load'));
    expect(panel(5).hidden).toBe(false);
  });

  it('waits for DOMContentLoaded while the document is loading', () => {
    const doc = document.implementation.createHTMLDocument('t');
    doc.body.innerHTML = section(1, 'collapsed');
    Object.defineProperty(doc, 'readyState', { value: 'loading', configurable: true });
    localStorage.setItem(KEY, JSON.stringify({ 'sidebar:1': false }));

    autoInitSections(doc, window);
    const button = doc.querySelector('[data-dashboard-menu-section]') as HTMLElement;
    expect(button.getAttribute('aria-expanded')).toBe('false');
    doc.dispatchEvent(new Event('DOMContentLoaded'));
    expect(button.getAttribute('aria-expanded')).toBe('true');
  });
});
