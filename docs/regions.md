# Theme regions

A **region** is a named slot of the site chrome — the header, the footer, and any
other area a theme chooses to expose. Its contents are a builder layout, exactly
like a page's `layout` property, so the header and footer are edited in the same
Gantry-style editor as a page instead of being hard-coded in a theme.

Region layouts live in `config/regions/<name>.yaml`, apart from the content tree,
so resetting `content/` leaves the chrome untouched.

```
config/regions/
  header.yaml
  footer.yaml
```

```yaml
# config/regions/header.yaml
label: Шапка сайта
layout:
  sections:
    - columns:
        - width: 4
          blocks:
            - type: media
              settings: {src: '/media/_regions/header/logo.svg', alt: 'Логотип'}
        - width: 8
          blocks:
            - type: menu
              settings: {source: menu, class: 'site-nav'}
```

`label` is the human name shown in the admin; a region without a file still
exists as long as it is one of the built-ins (`header`, `footer`). Any file under
`config/regions/` adds a region a theme may render.

## Rendering in a theme

The `Regions` subsystem is exposed through the template context as `region()`:

```php
<?php $header = $this->region('header'); ?>
<?php if ($header !== ''): ?>
    <?= $header ?>
<?php else: ?>
    <?= $this->partial('header', ['site' => $site]) ?>
<?php endif; ?>
```

`region()` returns the rendered layout HTML, or an empty string when the region
has no blocks — so a theme can always fall back to its own default partial. The
default theme does exactly this for the header and footer in
`themes/default/partials/layout.php`.

## Editing

Two ways in:

- **Области сайта** (`/admin/regions`) lists every region with a link to edit it.
- **Конструктор** (`/admin/builder?region=header`) opens the builder for a region.

Region media (logos and images used in the header) is uploaded to a hidden
content folder, `content/_regions/<name>/`, served at
`/media/_regions/<name>/<file>`. Folders whose name begins with `_` are skipped
by the content scanner, so region uploads never appear as content items.

## The menu particle

The `menu` block renders navigation. It has three sources:

| `source` | Renders |
| --- | --- |
| `menu` | the `menu-item` elements, ordered by `order` |
| `pages` | the routable pages, ordered by `order` |
| `custom` | a hand-typed list of `Заголовок \| /адрес` lines |

Menu items nest through their `parent` property: a `menu-item` whose `parent` is
the id (`menu/services`) or short name (`services`) of another item becomes its
child. `depth` limits how many levels are printed. Links are normalised — a bare
`about` becomes `/about`, while `https://…`, `//…`, `#anchor` and `/absolute`
paths are kept as-is.

A `menu-item` element looks like this:

```yaml
# content/menu/services/item.yaml
type: menu-item
title: Услуги
link: /services
order: 1
published: true
```

Edit the entries under **Контент** → the `menu-item` type; the header and footer
navigation follows automatically.
