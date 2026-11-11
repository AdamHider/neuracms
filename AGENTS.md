# AGENTS.md — FlatCMS

Flat-file CMS in PHP (namespace `FlatCms`). No database: content is folders under
`content/`, one `item.yaml` (properties) plus optional `content.md` (body) each.

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

## Content types & relations (added feature)

- `Content\Schema` reads `config/schemas/<type>.yaml`: `label`, `builtin`,
  `fields`, `indexed`, plus `relations()`, `referencedBy()`, `names()`, `all()`.
- `Content\TypeRepository` creates/updates/deletes type files; validates names and
  field names; builtins (`page`, `section`, `menu-item`) cannot be deleted.
- A field of type `relation` has `to: <type>` and optional `multiple: true`; the
  editor renders a picker over target items and stores referenced item ids.
  Relation targets are validated against `Storage` on save.
- Admin routes: `/admin/types` (list), `/admin/types/new`, `/admin/types/edit`,
  `/admin/types/delete`.
- Only fields listed in `indexed` are queryable (including relation fields).

## Gotchas

- Local `flatcms` branch tracks `origin/flatcms`; `master` is untouched, no PRs.
- `var/users/` is gitignored — admin credentials are never committed.
