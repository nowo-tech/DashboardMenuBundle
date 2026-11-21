# Third-party notices

The files below are built into the bundle's own assets (`src/Resources/public/js/`) from the exact versions pinned in
`package.json` / `pnpm-lock.yaml`. Nothing is loaded from a CDN at runtime.

| Package | Version | License | Built into | Purpose |
| ------- | ------- | ------- | ---------- | ------- |
| `@symfony/ux-autocomplete` | 2.36.2 | MIT | `js/autocomplete.js` | The `symfony--ux-autocomplete--autocomplete` Stimulus controller. |
| `tom-select` | 2.7.0 | Apache-2.0 | `js/autocomplete.js` | Autocomplete widget used by that controller. |
| `@hotwired/stimulus` | 3.2.2 | MIT | `js/autocomplete.js`, `js/stimulus-live.js` | Stimulus runtime. |
| `@symfony/ux-live-component` | 2.36.2 | MIT | `js/stimulus-live.js` | The `live` Stimulus controller. |
| `sortablejs` | see `package.json` | MIT | `js/dashboard.js` | Drag-and-drop tree. |

The Tom Select stylesheets are not shipped by this bundle: they are served from UiKit's asset package
(`css/vendor/tom-select.default.min.css`, recorded in UiKit's own third-party notices).

## License texts

- MIT (`@symfony/ux-autocomplete`, `@symfony/ux-live-component`, `@hotwired/stimulus`, `sortablejs`): permission is
  granted, free of charge, to use, copy, modify, merge, publish, distribute, sublicense and sell copies of the
  software, provided the copyright notice and permission notice are included in all copies or substantial portions.
  The software is provided "as is", without warranty of any kind.
- Apache License 2.0 (`tom-select`): <https://www.apache.org/licenses/LICENSE-2.0>. Copyright Brian Reavis and
  contributors; Tom Select's own `NOTICE` and license ship in its npm package.
