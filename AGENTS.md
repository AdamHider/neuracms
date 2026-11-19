# AGENTS.md — FlatCMS

Flat-file CMS in PHP (namespace `FlatCms`). No database: content is folders under
`content/`, one `item.yaml` (properties) each. Pages get their look from the
builder (their `layout`); an element may also carry an optional `content.md` body.

## Commands

```bash
vendor/bin/phpunit            # full suite (PHP 8.4; composer binary is not on PATH)
php bin/flatcms user:add <login> <password> [admin|editor]
php bin/flatcms user:list
php bin/flatcms index:build
php -S localhost:8000 -t public   # dev server
```

## Layout

- `src/` core subsystems (`App`, `Content`, `Admin`, `Http`, `Core`, `Support`)
- `src/Admin/` the admin panel — a **core subsystem** (`Subsystem`), not a module
- `modules/` self-contained modules (builder, shop, contact-form); PSR-4 auto-registered
- `config/schemas/<type>.yaml` content type definitions
- `themes/default/` templates; lookup order: item template → type → `page.php`
- `var/` generated data: cache, history, orders, users, logs (gitignored)

## Conventions

- Every file write goes through `Support\Fs` (atomic temp+rename, path safety).
- Subsystems implement `FlatCms\Contracts\Subsystem` (`boot`/`build`); modules
  implement `Module`. `App::boot()` builds core subsystems then loads modules.
- Admin templates/partials/CSS live in `src/Admin/` and are served at
  `/assets/admin/...`; register via `$app->addCoreTemplates()` / `addCoreAssets()`.
- Tests: `FlatCmsTestCase` gives an isolated root; add project folders with
  `copyFromProject()`. No mocks — real code paths only.

## Pages vs elements (added feature)

- Every content type has a `kind`: `page` (core, routable, look set in the
  builder) or `element` (structured data with no look of its own).
- `Schema::kind()/isPage()/isElement()/pages()/elements()`. Defaults: `page` and
  `section` are pages; every other type is an element unless its schema sets
  `kind:` explicitly (for example `category` is an element, set in its schema
  file). `TypeRepository::save()` persists `kind`.
- `Index` stores `kind` per row; `Query::kind($kind)` filters by it.
- Admin has two lists: **Страницы** (`/admin/pages`) and **Элементы**
  (`/admin/elements`); `/admin/content` still exists as an unfiltered list. The
  nav highlights `pages`/`elements`. Page rows link to the builder; element rows
  do not, and deleting returns to the matching list.
- The builder is page-only: `/admin/builder` and `/admin/builder/save` redirect
  element items to `/admin/content/edit`. Page appearance is the builder layout;
  pages carry no Markdown body at all (the editor has none, `ContentWriter` drops
  an empty `content.md`, and `page.php`/`section.php` render only the layout).

## Builder particles (added feature)

- `media` particle (`Types/MediaType.php`): an uploaded image. Its `src` is a
  `file` field; the editor uploads to `POST /admin/builder/media`, which stores
  the file via the core `MediaLibrary` and returns `/media/<page id>/<file>`.
- `elements` particle (`Types/ElementsType.php`): lists a chosen element type as
  cards. `map_*` settings map element properties onto card fields
  (title/description/price/link/image); `element_type`, `limit`, `sort`,
  `direction`, `columns`. The editor builds the mapping dropdowns from the
  `elements` map in the builder payload (`BuilderModule::elementTypes()`).
- The builder payload also carries `media`, `uploadUrl` and `pageId` for the
  media particle.
- `menu` particle (`Types/MenuType.php`): navigation from content items or a
  hand-typed list, with property filters (see **Menus & navigation**). The editor
  renders the filter builder from the `filters` field.

## Content types & relations (added feature)

- `Content\Schema` reads `config/schemas/<type>.yaml`: `label`, `builtin`,
  `fields`, `indexed`, plus `relations()`, `referencedBy()`, `names()`, `all()`.
- `Content\TypeRepository` creates/updates/deletes type files; validates names and
  field names; builtins (`page`, `section`) cannot be deleted.
- A field of type `relation` has `to: <type>` and optional `multiple: true`; the
  editor renders a picker over target items and stores referenced item ids.
  Relation targets are validated against `Storage` on save.
- Admin routes: `/admin/types` (list), `/admin/types/new`, `/admin/types/edit`,
  `/admin/types/delete`.
- A type may ask for its own admin sidebar entry with an `admin:` block
  (`menu`, `label`, `icon`, `order`); `Schema::adminMenu()` returns them sorted,
  `TypeRepository::save()` persists them, and the type editor has the checkbox +
  label/icon/order fields. `layout.php` appends them to `$nav`; `content-list.php`
  highlights `type:<name>` when filtered.
- Only fields listed in `indexed` are queryable (including relation fields).

## Admin UI (Bootstrap redesign)

- The panel is Bootstrap 5 + Bootstrap Icons, vendored under
  `src/Admin/assets/vendor/` (offline, no CDN) and served at `/assets/admin/...`.
- `Admin\Icons` maps semantic keys to Bootstrap Icons names; templates use
  `$this->icon('edit')`. Add new meanings to the map, not glyphs to templates.
- `src/Admin/partials/admin/layout.php` is the shell; pages pass `title`,
  `active` (sidebar highlight) and optionally `head` (extra `<link>` tags).
- `src/Admin/assets/css/admin.css` is a thin theme layer over Bootstrap.
- Content editor (`templates/admin/content-edit.php`): a single flat list of the
  chosen type's fields — no "Поля типа" heading, no Markdown/Media/Move sections.
  The item's type is fixed once picked (a "Товар" opens the product form). All
  action buttons sit in a top bar (`.editor-bar`, OpenCart/Joomla style), and the
  form warns with `confirm`/`beforeunload` when leaving unsaved changes.
- Editor pages use a two-column `.editor-grid` (form on the left, meta/move on
  the right, sticky side column); `.form-grid` lays fields out in two columns with
  `.form-field--full` for wide ones. `types-edit.php` and `users.php` share it.
- The `markdown` field type was **removed** from `Schema::FIELD_TYPES` and the
  type editor; a page has no Markdown body, and the type editor label is plain
  "Название" (the old "(для людей)" suffix is gone).

## Page builder (Gantry-style sections)

- Model: `Layout` → `Section` → `Column` → `Block` (was a flat block list + grid).
- `Layout::fromValue()` upgrades legacy `{blocks, grid}` and bare block lists to
  sections on read, so old content keeps rendering. Saving writes `sections:`.
- `LayoutRenderer` emits `.g-section` > `.g-container` > `.g-row` > `.g-col`
  (`--g-span` custom property); theme styles these in `themes/default/.../main.css`.
- Block types carry `category` and `icon`; registry exposes categories and
  section presets. New types: `quote`, `divider`, `spacer`.
- `BuilderModule::presets()` lists ready-made sections for "Добавить секцию": the
  plain ones carry `widths`, and the **Шапка сайта** / **Подвал сайта** presets
  also carry `blocks` (a list of block lists, one per column) so the editor drops
  in a working section. `builder.js` `addSection(preset)` accepts a preset object
  or a bare widths array and fills each particle's field defaults.
- Editor `modules/builder/templates/admin/builder.php` + `assets/js/builder.js`
  is a three-pane drag & drop UI; JSON textarea stays in sync (works without JS).
- Save sanitizes sections/columns, drops unknown block types and non-scalar
  settings, clamps widths to `builder.grid_columns`.

## Menus & navigation (added feature)

- There is no separate "regions" subsystem and no `menu-item` type. Header and
  footer are ordinary **sections** on a page (the **Шапка сайта** / **Подвал
  сайта** presets), and the theme's own `header`/`footer` partials render the
  site chrome.
- `menu` particle (`Types/MenuType.php`, `templates/blocks/menu.php`) renders
  navigation from content items. `source: pages` (default) lists items of a
  chosen `content_type`, ordered by `order`, optionally narrowed by any number of
  **property filters** (`filters: [{field, op, value}]`, e.g. a custom
  "Показывать в меню" bool added to the page type). `source: custom` renders
  hand-typed `Title | /url` lines.
- Filters compare loaded item properties in PHP (not only indexed fields), so any
  schema property works. Operators: `=`, `!=`, `>`, `>=`, `<`, `<=`, `contains`.
  Items nest via their parent id; `depth` limits levels; `$this->link()` keeps
  absolute URLs/anchors and prefixes bare paths.
- Core types are `page` and `section` (both pages); `category`, `product`,
  `service` are elements.
- Admin: **Документация** (`/admin/docs`) renders `docs/*.md` via `Admin\Docs`.

## Global sections & blocks (added feature)

- `GlobalLibrary` (`modules/builder/src/GlobalLibrary.php`) keeps reusable
  sections/blocks **outside** the content tree, at
  `content/_global/<kind>/<name>/item.yaml` (`kind` is `section` or `block`).
  `content/_global` is skipped by indexing, so a global never shows up as a page
  or element. Each file holds `title`, `kind`, `order`, `role` and either
  `section:` (one section payload) or `block:` (one block payload).
- A page references a global two ways: a section with
  `settings: {global: <name>}` (section kind) or a block of type `global` with
  `settings: {name: <name>}` (block kind). `LayoutRenderer::resolveSection()` /
  `resolveBlock()` swap in the stored payload at render time, so editing a
  global updates every page that uses it.
- Admin: `/admin/globals` lists both kinds with a reference count and a role
  badge; the editor reuses the page canvas via
  `/admin/builder?global=section:<name>` or `?global=block:<name>`;
  `POST /admin/builder/global` handles promote/save/delete. "Promote" copies a
  section or block off a page into the library.
- `role` on a section (`header` / `footer`) makes it a chrome band rather than
  body content: `LayoutRenderer::pageShell()` renders it as `<header>`/`<footer>`
  next to the body, and `renderLayout()` skips role sections in the body. A page
  without a band falls back to the theme `header`/`footer` partials; an item-less
  page (cart, contact form) borrows the chrome from the home page.

## Gotchas

- Local `flatcms` branch tracks `origin/flatcms`; `master` is untouched, no PRs.
- `var/users/` is gitignored — admin credentials are never committed.
