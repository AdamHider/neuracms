# FlatCMS

A tiny flat-file CMS in PHP. No database: every page, product or news item is a
folder with an `item.yaml` (properties) and an optional `content.md` (body).

The same core plus a few modules assembles three kinds of site: a business card
site, a corporate site and a small shop.

## Requirements

- PHP 8.2 or newer (developed on 8.4) with `json`, `mbstring` and `fileinfo`
- Composer, to install dependencies

## Install

```bash
composer install
cp profiles/visiting-card/site.yaml config/site.yaml   # optional starting point
php bin/flatcms index:build
php -S localhost:8000 -t public
```

Open <http://localhost:8000>. The admin area lives at `/admin`.

### Install a ready-made site

Three profiles ship with the project: `visiting-card`, `corporate` and `shop`.
A profile is a `site.yaml` plus a `content/` tree.

```bash
php bin/flatcms site:install shop --force
php bin/flatcms index:build
```

`--force` overwrites the current `content/` folder. Without it the command
refuses to touch a non-empty content tree.

## Layout of the project

```
config/       site.yaml, redirects.yaml, per-type schemas and theme regions
content/      the site content: one folder per item
docs/         project documentation, readable in the admin panel
modules/      self-contained modules (builder, shop, contact-form)
profiles/     ready-made sites (config + content + regions)
src/          the CMS core
tests/        PHPUnit tests
themes/       themes; each has templates/, partials/ and assets/
var/          generated data: cache, history, orders, users, logs
public/       the web root; the only folder that must be reachable
```

## Content model

An item is a folder. Its properties are free-form: `item.yaml` may hold any keys
you like, and a schema is only used to describe them for the editor.

```
content/products/teapot/item.yaml   # properties
content/products/teapot/content.md  # body
content/products/teapot/photo.jpg   # attachment, served at /media/products/teapot/photo.jpg
```

Reserved keys are `type`, `title`, `slug`, `order`, `published`, `template`,
`date`, `inherit` and `layout`. Everything else is a custom property.

### Content types and relations

A **content type** (`config/schemas/<type>.yaml`) describes the fields of items
of that type, which of them the index stores, and relations to other types. Types
are created and edited in the admin panel under **Типы контента**, which writes
the same files. `page`, `section` and `menu-item` are built in and cannot be
deleted; everything else is custom.

A type can also ask for its own entry in the admin sidebar (an `admin:` block
with `menu`, `label`, `icon` and `order`), so a type like "Статьи" gets a
dedicated menu item that opens its filtered list:

```yaml
type: article
kind: element
label: Статья
admin: {menu: true, label: Статьи, icon: docs, order: 5}
```

A field of type `relation` attaches one content type to another — a product to a
category, for example:

```yaml
type: product
fields:
  title:    { type: string, required: true, label: Название }
  price:    { type: float, required: true, label: Цена }
  category: { type: relation, to: category, label: Категория }
  related:  { type: relation, to: product, multiple: true, label: Похожие }
indexed: [price, category]
```

In the item editor a relation is a picker over the target type's items. See
[docs/content.md](docs/content.md) for the full field list and semantics.

## Modules

The admin panel is part of the core (not a module) and is on for every site; see
[docs/admin.md](docs/admin.md). The remaining modules are:

| Module | What it adds |
| --- | --- |
| `builder` | Block-based page builder and the `/admin/builder` editor |
| `shop` | Catalog, cart, checkout, orders and payment providers |
| `contact-form` | A contact form and message storage |

Modules are enabled per site in `config/site.yaml`:

```yaml
modules:
  builder: { enabled: true }
  shop: { enabled: false }
```

A module lives in `modules/<name>/` with a `module.yaml`, a `src/` folder
(namespace auto-registered) and optional `templates/` and `assets/`.

## Themes

A theme lives in `themes/<name>/`. Templates are resolved item template first,
then type template, then `page.php`. A theme can provide `partials/layout.php`
and assets under `assets/`, served at `/assets/<theme>/...`.

## Page builder

A page can describe its body as a layout of sections and columns instead of
Markdown (a Gantry 5 style section > column > block model):

```yaml
layout:
  sections:
    - columns:
        - width: 8
          blocks:
            - type: heading
              settings: {text: 'Привет', level: h1}
        - width: 4
          blocks:
            - type: products
              settings: {title: 'Хиты', limit: 3}
```

Core block types are `heading`, `text`, `image`, `button`, `quote`, `divider`,
`spacer`, `columns`, `products`, `menu`, `elements` and `html`. Modules can add
more through the `builder.types` filter. Edit a page in the admin under
"Конструктор" on any content item: drag particles from the library into columns,
rearrange them, and tune section and block settings in the side panel.

"Добавить секцию" offers ready-made sections — one to four columns, a sidebar,
and **Шапка сайта** / **Подвал сайта** presets that arrive with their blocks (a
heading plus a `menu`) already in place.

The same editor builds the **site chrome**. A theme region — the header and
footer, plus any extra region a theme declares — is a builder layout stored under
`config/regions/`, so navigation and the logo are edited as content rather than
hard-coded in a theme. The `menu` particle feeds navigation from `menu-item`
elements, the pages, or a hand-typed list. Edit regions under "Области сайта".

A header/footer section preset adds a section to **one page**; a region renders
on **every page**. Use the presets for a per-page header or footer and a region
for the site-wide chrome.

## CLI

```bash
php bin/flatcms user:add <login> <password> [admin|editor]
php bin/flatcms user:list
php bin/flatcms index:build
php bin/flatcms site:install <profile> [--force]
```

## Tests

```bash
vendor/bin/phpunit
```

## Documentation

See [docs/](docs/) for the architecture, the content format, the builder, the
theme regions, the admin panel and the site profiles.
