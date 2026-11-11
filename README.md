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
config/       site.yaml, redirects.yaml and per-type schemas
content/      the site content: one folder per item
modules/      self-contained modules (builder, shop, contact-form)
profiles/     ready-made sites (config + content)
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

A page can describe its body as blocks instead of Markdown:

```yaml
layout:
  blocks:
    - type: heading
      settings: {text: 'Привет', level: h1}
    - type: products
      settings: {title: 'Хиты', limit: 3}
```

Core block types are `heading`, `text`, `image`, `button`, `columns`,
`products` and `html`. Modules can add more through the `builder.types` filter.
Edit blocks in the admin under "Конструктор" on any content item.

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
admin panel and the site profiles.
