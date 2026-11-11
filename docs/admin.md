# Admin panel

The admin panel is part of the **core**, not a module. It ships authentication,
content management, media, history, users and maintenance tools under `/admin`,
and its classes live in `src/Admin/` (`FlatCms\Admin`).

Because it is core, every site gets it without listing it under `modules:`. It is
switched on by default and can be turned off in `config/site.yaml`:

```yaml
admin:
  enabled: false
```

With the panel disabled, the `/admin` routes are not registered and return 404.

## Access

Every admin route except the login form requires a session. An anonymous request
is redirected to `/admin/login`; a logged-in user without the needed role is
redirected to `/admin`. Two roles exist:

| Role | Can do |
| --- | --- |
| `admin` | Everything, including users and tools |
| `editor` | Content, media and the page builder |

There is no default account. Create the first user from the command line:

```bash
php bin/flatcms user:add admin "a long password" admin
```

Passwords are stored with `password_hash()`. Users live in `var/users/`.

## Routes

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/admin` | Dashboard: counts, recent changes, recent orders |
| GET, POST | `/admin/login` | Sign in |
| POST | `/admin/logout` | Sign out |
| GET | `/admin/content` | List items, filter by type |
| GET, POST | `/admin/content/new` | Create an item |
| GET, POST | `/admin/content/edit` | Edit properties, body and media |
| POST | `/admin/content/delete` | Delete (to `var/trash/`) |
| POST | `/admin/content/move` | Rename or move an item |
| GET | `/admin/content/history` | Versions of an item |
| GET | `/admin/types` | List content types |
| GET, POST | `/admin/types/new` | Create a type |
| GET, POST | `/admin/types/edit` | Edit a type's fields and relations |
| POST | `/admin/types/delete` | Delete a custom type (admin only) |
| POST | `/admin/media/upload` | Upload an attachment |
| POST | `/admin/media/delete` | Remove an attachment |
| GET | `/admin/users` | List and create users (admin only) |
| GET | `/admin/tools` | Rebuild index, clear cache (admin only) |

## Content types

The **Типы контента** page (`/admin/types`) manages the files under
`config/schemas/`. A type has a name (used as the file name and as the `type`
value of its items), a human label, and a list of fields. Each field has a name,
a type, an optional label, a `required` flag and an `indexed` flag (whether the
index stores it for querying). Types marked `builtin` (`page`, `section`,
`menu-item`) can be edited but not deleted.

The item editor renders a control per field: text and number inputs, checkboxes,
selects, textareas and list inputs. A field of type **relation** is rendered as a
picker over the items of the target type, which is how one content type is
attached to another (a product to a category, for example). See
[content.md](content.md) for the schema format and relations.

`TypeRepository` (`FlatCms\Content\TypeRepository`) writes the type files and
validates names, field names and relation targets. Editing a type triggers an
index rebuild so new `indexed` fields take effect immediately.


## Writing content

`ContentWriter` saves `item.yaml` and `content.md`. Before it writes, it copies
the previous version to `var/history/<type>__<id>/<timestamp>/`, so every save is
recoverable from the history page.

## Media

`MediaLibrary` checks the file extension against a whitelist and then the real
MIME type with `finfo`, and rejects anything larger than 5 MB. Accepted files are
stored next to the item and served at `/media/<item id>/<file>`, so the `content/`
folder never has to be web reachable. Upload failures set `media_error`, which the
edit page displays.

## Tools

- Rebuild index — regenerates `var/cache/index.php` from the content tree.
- Clear cache — removes rendered page cache.

## Command line

```bash
php bin/flatcms user:add <login> <password> [admin|editor]
php bin/flatcms user:list
php bin/flatcms index:build
php bin/flatcms site:install <profile> [--force]
```
