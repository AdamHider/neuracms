# Page builder

The builder module lets a page describe its body as a layout instead of
Markdown. The layout lives in the reserved `layout` property of an item, so a
page with a layout and a page with Markdown are the same kind of item.

The structure follows Gantry 5: a page is a list of **sections**, a section is a
row of **columns**, and a column holds a stack of **blocks** (the equivalent of
particles).

```
page
└── sections[]
    └── columns[]
        └── blocks[]
```

## Data shape

```yaml
layout:
  sections:
    - id: hero                 # stable id, assigned by the editor
      settings:                # section styling
        width: boxed           # boxed (default) or full
        variant: muted         # '', muted, dark, accent
        background: '#f4f6fb'  # optional raw CSS background
        padding: '3rem 0'      # optional raw CSS padding
      columns:
        - width: 8             # grid span in twelfths (0 = auto/full)
          blocks:
            - type: heading
              id: 7a2d776c
              settings:
                text: Привет
                level: h1
        - width: 4
          blocks:
            - type: button
              settings:
                label: Купить
                href: /products
                style: primary
```

Only `sections` is required. A section with a flat `blocks:` list (no `columns:`)
is accepted and wrapped in a single full-width column.

## Legacy layouts

Layouts written before sections existed still render. `Layout::fromValue()`
upgrades them on read:

- `{blocks: [...], grid: [[6, 6]]}` becomes one section per grid row, one column
  per block, using the grid spans as column widths.
- a bare `[{type: ...}]` list becomes one full-width section per block.

Saving a page from the editor always writes the new `sections` shape.

## Block types

| Type | Settings | Renders |
| --- | --- | --- |
| `heading` | `text`, `level` (`h1`/`h2`/`h3`) | A heading |
| `text` | `body` (Markdown) | A paragraph of Markdown |
| `image` | `src`, `alt`, `width` | An image |
| `button` | `label`, `href`, `style` (`primary`/`outline`) | A link styled as a button |
| `quote` | `text`, `author` | A blockquote |
| `divider` | `style` (`solid`/`dashed`/`dotted`) | A horizontal rule |
| `spacer` | `size` (`sm`/`md`/`lg`/`xl`) | Vertical space |
| `columns` | `ratio`, `left[]`, `right[]` | Two columns of nested blocks |
| `products` | `title`, `limit` | A grid of products from the shop |
| `html` | `html` | Raw HTML, for the rare case nothing else fits |

Each type also carries a `category` (used to group the particle picker) and an
`icon` (a Bootstrap Icons name).

## Rendering

The module registers a layout resolver with the renderer:

```php
$app->renderer->setLayoutResolver(fn (mixed $item): string => $this->renderer()->render($item));
```

Themes call it through the template context:

```php
<?= $this->layout($item) ?>
```

`LayoutRenderer::render()` returns the section HTML, or an empty string when the
item has no blocks, so a theme can fall back to `$item->html()`:

```php
<?= $layout !== '' ? $layout : $item->html() ?>
```

Sections render as:

```html
<section class="g-section g-section--muted">
  <div class="g-container">
    <div class="g-row">
      <div class="g-col" style="--g-span:8">…blocks…</div>
      <div class="g-col" style="--g-span:4">…blocks…</div>
    </div>
  </div>
</section>
```

The theme styles `.g-section`, `.g-container`, `.g-row` and `.g-col`; the column
span arrives as the `--g-span` custom property.

## Adding a block type

A block type is a small class extending `BlockType`:

```php
final class QuoteType extends BlockType
{
    public function __construct()
    {
        parent::__construct('quote', 'Цитата', [
            'text' => ['type' => 'text', 'label' => 'Текст', 'default' => ''],
            'author' => ['type' => 'string', 'label' => 'Автор', 'default' => ''],
        ], null, 'Текст', 'quote');
    }
}
```

The constructor arguments are name, title, fields, template (null = the default
`blocks/<name>`), category and icon. Drop a `templates/blocks/quote.php` in the
module and register the type on the `builder.types` event.

## Editor

`/admin/builder?id=<item id>` opens a Gantry-style three-pane editor:

- **Left**: the particle library, grouped by category, with a search box. Drag a
  particle into a column to add it.
- **Middle**: the canvas. Drag the handle on a block to reorder or move it between
  columns; drag a section header to reorder sections. Use "Добавить секцию" to
  add a section from a preset (one to four columns, sidebar, …). Select a section
  or block to open its settings.
- **Right**: settings for the current selection (section styling, or the block's
  declared fields).

The raw JSON stays in sync and can be toggled with the "JSON" button; without
JavaScript the JSON textarea is still editable by hand. Saving posts to
`/admin/builder/save`, which:

1. Parses the JSON.
2. Rebuilds sections and columns, dropping blocks whose type is not registered.
3. Drops setting keys that are not simple scalars.
4. Clamps column widths to the configured column count.
5. Writes `item.yaml` through `ContentWriter`, keeping a history snapshot.

`GET /admin/builder/types` returns the registry, categories, presets and grid
size as JSON for the editor.
