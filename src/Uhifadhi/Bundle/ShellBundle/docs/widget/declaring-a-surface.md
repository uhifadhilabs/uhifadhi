# Declaring a surface

```php
// src/Widget/SightingsSurface.php (your module)
namespace YourVendor\Sightings\Widget;

use Uhifadhi\Bundle\ShellBundle\Widget\Model\Widget;
use Uhifadhi\Bundle\ShellBundle\Widget\Model\WidgetCatalog;
use Uhifadhi\Bundle\ShellBundle\Widget\Model\WidgetGroup;
use Uhifadhi\Bundle\ShellBundle\Widget\Model\WidgetPreset;
use Uhifadhi\Bundle\ShellBundle\Widget\Registry\WidgetSurfaceInterface;

final class SightingsSurface implements WidgetSurfaceInterface
{
    public function catalog(): WidgetCatalog
    {
        return new WidgetCatalog(
            'sightings',                       // what every stored row is keyed by
            [new WidgetGroup('counts', 'Counts', 'How much was seen, and when.')],
            [
                new Widget('total', 'Total sightings', 'counts', cols: 6, spans: [12, 6, 3]),
                new Widget('map', 'Where they were', 'counts', cols: 12, spans: [12], on: false),
            ],
            [new WidgetPreset('wide', 'Wide', 'One thing at a time.', ['total' => 12])],
        );
    }
}
```

```php
// config/services.php (your module) — a reusable bundle is not autoconfigured
$services->set('sightings.widget_surface', SightingsSurface::class)
    ->tag(WidgetSurfaceInterface::TAG);
```

**The surface string is stable across releases**, because it is what every
stored row is keyed by. Two modules may both ship a widget called `map` and
neither ever sees the other's rows; two modules claiming the same *surface* is a
refusal at boot.

## Contents

- [The model: one active preset](#the-model-one-active-preset)
- [Rendering the library](#rendering-the-library)

## The model: one active preset

**There is no anonymous layout.** A dashboard renders exactly one preset — one
the surface ships, or one the person saved — so:

- the strip always has exactly one card wearing **Active**, and somebody new
  starts on the surface's default;
- **built-ins are immutable.** The composition controls are not offered while one
  is active; the toolbar offers *Make a copy to customize*, and the copy is what
  gets edited. Nothing forks behind anyone's back;
- **editing one of your own writes through to it**, in place. There is no
  separate "save my layout" step;
- **absence is the default.** Somebody who never opened the library has no row at
  all, which is why *reset* is a delete.

**A stored row is untrusted input** — a browser wrote it, and a release may have
retired a widget since. Reading one can never throw, and writing one can never
store an id or a span the catalogue does not offer.

## Rendering the library

The page renders server-side and every action is a plain form post, so the screen
works with no JavaScript at all. The script adds preview — re-composing the
canvas client-side for a preset you have not applied — and the picker's live
filtering.

```twig
{# your module's widgets page #}
<link rel="stylesheet" href="{{ asset(constant('Uhifadhi\\Bundle\\ShellBundle\\Widget\\ShellBundle::STYLESHEET')) }}">

{{ include('@Shell/widget/_library.html.twig', {
    catalog: catalog,
    builtins: catalog.builtins,
    customPresets: customPresets,
    active: active,
    widgets: widgets,
    partial: 'sightings/_w_%s.html.twig',
    widgetContext: widgetContext,
    urls: urls,
    csrfToken: csrfToken,
}) }}
```

The stylesheet is written in the **shell's tokens** and defines none of its own,
so an installation that restyles the shell restyles this with it.

### The script, and the two lines that load it

The script is registered under the AssetMapper namespace
`@uhifadhi/shell-bundle`, and an installation names it in its own
`importmap.php` — the one file a bundle may not write. **The Flex recipe writes
both lines for you**; they are here so you can recognise them, and so you can
add them by hand if your project's files have moved from the skeleton's layout.

In `importmap.php`:

```php
'@uhifadhi/shell-bundle/widgets' => ['path' => '@uhifadhi/shell-bundle/widgets.js'],
```

In `assets/app.js`:

```js
import '@uhifadhi/shell-bundle/widgets';
```

**Importing it is the whole contract.** The module arms itself: it looks for a
library root when the document is ready, and a page that carries none costs one
`querySelector`. There is no init call to write and no controller to register —
a module bundle must not make somebody install a Stimulus controller before they
can arrange their own dashboard.

Without these two lines the library still *renders*: the strips, the toolbar and
the canvas are all server-side, and every action on them is a plain form post.
What you lose is everything the script adds — **previewing a preset**, the
picker's live filtering, and optimistic editing. That is the shape of the
failure to look for: cards that draw correctly and do nothing when clicked.

`WidgetDom` is the attribute contract between the templates and the script. Both
sides read it from there, and a test reads `widgets.js` as text and proves they
spell every name identically — a mismatch there fails only in a browser.
