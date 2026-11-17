import { afterEach, describe, expect, it, vi } from 'vitest';

import './menu-collapse';

describe('menu-collapse boot entry', () => {
  afterEach(() => {
    document.body.innerHTML = '';
    vi.resetModules();
  });

  it('connects the collapse roots of the page when the entry is evaluated', async () => {
    document.body.innerHTML = `
      <ul id="menu" data-dashboard-menu-collapse="sidebar">
        <li><a href="/a">A</a>
          <button type="button" class="menu-nested-toggle" data-bs-toggle="collapse" data-bs-target="#p1" aria-controls="p1">t</button>
          <div id="p1" class="collapse"><ul><li><a href="/a1">A1</a></li></ul></div>
        </li>
      </ul>`;

    await import('./menu-collapse-boot');

    expect(window.NowoDashboardMenuCollapse).toBeDefined();
    expect(window.NowoDashboardMenuCollapse?.get(document.getElementById('menu') as HTMLElement)).toBeDefined();
  });
});
