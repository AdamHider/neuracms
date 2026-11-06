# Page builder

The builder module lets a page describe its body as a list of blocks instead of
Markdown. Blocks live in the reserved `layout` property of an item, so a page
with blocks and a page with Markdown are the same kind of item.

## Data shape

```yaml
layout:
  grid:            # optional; column spans per row, in twelfths
    - [6, 6]
    - [4, 8]
  blocks:
    - type: heading
      id: 7a2d776c        # assigned on save
      settings:
        text: Привет
        level: h1
```

`blocks` is the only required key. `grid` is optional: without it the blocks
render one after another; with it, each block is wrapped in a column and rows
break where `grid` says.

## Block types

| Type | Settings | Renders |
| --- | --- | --- |
| `heading` | `text`, `level` (`h1`/`h2`/`h3`) | A heading |
| `text` | `body` (Markdown) | A paragraph of Markdown |
| `image` | `src`, `alt`, `width` | An image |
| `button` | `text`, `url`, `style` (`primary`/`outline`) | A link styled as a button |
| `columns` | `ratio`, `left[]`, `right[]` | Two columns of nested blocks |
| `products` | `title`, `limit` | A grid of products from the shop |
| `html` | `html` | Raw HTML, for the rare case nothing else fits |

`columns` nests blocks, so a column can hold another `columns` block.

## Rendering

The module registers a layout resolver with the renderer:

```php
$app->renderer->setLayoutResolver(fn (mixed $item): string => $this->renderer()->renderLayout(
    Layout::fromValue($item instanceof Item ? $item->get('layout') : $item)
));
```

Themes call it through the template context:

```php
<?= $this->layout($item) ?>
```

`LayoutRenderer::renderLayout()` returns the block HTML, or an empty string when
the item has no blocks, so a theme can fall back to `$item->html()`:

```php
<?= $layout !== '' ? $layout : $item->html() ?>
```

## Adding a block type

A block type is a small class extending `BlockType` that returns its name, title,
settings fields and a template name:

```php
final class QuoteType extends BlockType
{
    public function name(): string { return 'quote'; }
    public function title(): string { return 'Цитата'; }
    public function category(): string { return 'Текст'; }
    public function fields(): array
    {
        return ['text' => ['type' => 'text', 'label' => 'Текст', 'default' => '']];
    }
    public function template(): string { return 'builder/blocks/quote'; }
}
```

Register it on the `builder.types` event and drop a `templates/blocks/quote.php`
in the module. The fields list drives both the admin form and the defaults applied
before rendering.

## Editor

`/admin/builder?id=<item id>` opens the editor. It has two synced panes: a block
list with add, move, remove and per-block fields, and the raw JSON. Without
JavaScript the JSON pane still works. Saving posts to `/admin/builder/save`,
which:

1. Parses the JSON.
2. Drops blocks whose type is not registered.
3. Drops setting keys that the type does not declare.
4. Normalises `grid` against the configured column count.
5. Writes `item.yaml` through `ContentWriter`, keeping a history snapshot.

`GET /admin/builder/types` returns the registry as JSON for the editor.
