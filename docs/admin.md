# Admin module

The admin module adds authentication, content management, media, history, users
and maintenance tools under `/admin`.

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
| POST | `/admin/media/upload` | Upload an attachment |
| POST | `/admin/media/delete` | Remove an attachment |
| GET | `/admin/users` | List and create users (admin only) |
| GET | `/admin/tools` | Rebuild index, clear cache (admin only) |

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
