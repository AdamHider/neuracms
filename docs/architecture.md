# Architecture

FlatCMS is a small PHP application with no database. Content is a tree of
folders under `content/`; everything else is generated into `var/`.

## Boot sequence

`public/index.php` creates an `App` and calls `handle()`:

1. The constructor builds `Fs`, `Events`, `Config`, the session and registers a
   PSR-4 autoloader per module.
2. `boot()` runs every subsystem — including the core admin panel — and then
   loads the enabled modules. Each module registers routes, listens to events and
   adds its template folder.
3. `build()` (or the request itself) refreshes the content index and the cache.
4. `handle(Request)` asks the router to match. A match is either a handler, a
   redirect or a content item; the renderer turns the result into HTML.

## Core objects

| Object | Responsibility |
| --- | --- |
| `App` | Wires everything and owns the request/response cycle |
| `Core\Config` | Reads `config/site.yaml` and module configs, dot-key access |
| `Core\Events` | `on`, `emit` and `filter` hooks used by core and modules |
| `Core\Request` / `Core\Response` | Thin value objects |
| `Content\Storage` | Loads an item folder into an `Item` |
| `Content\Index` | Cached list of items with the fields that queries need |
| `Content\Query` | Fluent filtering and sorting over the index |
| `Content\Schema` | Field descriptions, relations, `kind` (page vs element) and `admin` menu settings per content type |
| `Content\TypeRepository` | Creates, updates and deletes content types (writes `config/schemas/`) |
| `Http\Router` | Pattern routes, redirects and content fallback |
| `Http\Renderer` | Template lookup, partials and the layout resolver |
| `Http\Assets` | Serves theme, core and module assets and item media |
| `Admin\AdminPanel` | Core admin subsystem: auth, content, media, users, tools |
| `Admin\Icons` | Semantic icon names mapped to Bootstrap Icons |
| `Admin\Docs` | Reads `docs/*.md` for the in-panel documentation viewer |
| `Support\Fs` | All file access, with path safety |

## Rendering

`Renderer` looks up a template in this order: the item `template` property, the
item `type` (`templates/<type>.php`), then `templates/page.php`. A theme's
`templates/` and `partials/` are searched first, then the core's own folders
(`src/Admin`), then each enabled module's `templates/`. Assets are served at
`/assets/<name>/...`: the theme wins, then a core subsystem, then a module.

Templates receive `$item` (or the current data), `$app` and `$this`, a
`TemplateContext` with `e()`, `url()`, `link()`, `asset()`, `partial()` and
`layout()`.

`$this->layout($item)` renders the item's builder layout (sections of columns of
blocks). Without the builder module the resolver is empty and the helper returns
an empty string, so themes can call it unconditionally.

The header and footer are ordinary sections on a page (there is no separate
"regions" subsystem); a theme's own `header`/`footer` partials render the site
chrome.

## Content index

The index is a PHP file in `var/cache/` holding one row per item with its `type`,
`kind`, `title`, `url`, `order`, `published`, parent and the fields listed in the
type schema's `indexed` key. `Index::isStale()` compares folder timestamps, and a
stamp file guards against a build racing another request. Queries read the index
only, so `indexed` must list any field used in `where()`. `Query::kind()` filters
rows by `kind`, which is how the admin separates pages from elements.

## Storage and safety

`Fs` resolves every path below the project root and refuses anything that escapes
it. `Storage` treats a folder name as the item id, so an id like `products/teapot`
maps to `content/products/teapot`. Writes go through the admin panel's
`ContentWriter`, which keeps a copy of every previous version under
`var/history/<type>__<id>/<timestamp>/`.

## Extension points

| Hook | Kind | Purpose |
| --- | --- | --- |
| `site.data` | filter | Add navigation entries and site-wide values |
| `item.loaded` | filter | Change an item right after it is read |
| `render.before` / `render.after` | emit | Observe template rendering |
| `route.matched` / `route.notfound` | emit | Observe routing |
| `builder.types` | emit | Register page-builder block types |
| `builder.block.before` | emit | Observe a block before it renders |
| `shop.providers` | emit | Register payment providers |
| `shop.order.created` | emit | React to a new order |
| `app.booted`, `request.received`, `response.before_send` | emit | Lifecycle |

## Request flow

```
Request -> Router.match
             |- redirect
             |- handler (module route)
             `- content item -> Renderer.render -> theme template -> Response
```
