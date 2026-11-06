# Content format

## An item is a folder

```
content/
  index/                  -> /
  about/                  -> /about
    item.yaml
    content.md
  products/
    item.yaml             -> /products
    teapot/
      item.yaml           -> /products/teapot
      content.md
      photo.jpg           -> /media/products/teapot/photo.jpg
```

`item.yaml` holds the properties, `content.md` holds the body. Either file may be
missing. Any other file in the folder is an attachment.

## Reserved properties

| Key | Meaning |
| --- | --- |
| `type` | Content type; picks the schema and the `templates/<type>.php` |
| `title` | Title, used in navigation and as the default heading |
| `slug` | URL fragment when it differs from the folder name |
| `order` | Sort position among siblings |
| `published` | `false` hides the item from the site and the index |
| `template` | Force a specific template |
| `date` | Date for news and blog items |
| `inherit` | Inherit the parent's properties |
| `layout` | Page-builder blocks (see [builder.md](builder.md)) |

Every other key is a free-form property. The CMS never requires a schema; a
schema only describes fields for the admin editor.

## Schemas

A schema in `config/schemas/<type>.yaml` lists fields and which of them the index
stores:

```yaml
type: product
fields:
  title: { type: string, required: true, label: Название }
  price: { type: float, required: true, label: Цена }
  sku:   { type: string, label: Артикул }
  tags:  { type: list, label: Теги }
indexed: [price, sku, tags]
```

Field types are `string`, `text`, `int`, `float`, `bool`, `date`, `select` and
`list`. If a schema has no `fields`, the editor falls back to a generic list.

## Querying

```php
$app->query()->type('product')->where('price', '>', 1000)->sort('price', 'desc')->find();
$app->query()->children('products')->find();
$app->query()->type('page')->search('доставка')->find();
$app->storage->load('products/teapot');
```

Only fields listed in the schema's `indexed` key can be filtered or sorted
reliably, because queries read the cached index rather than every file.

## Routing

A request path is matched in this order:

1. A canonical redirect from `config/redirects.yaml` or a slug change.
2. A registered route pattern such as `/order/{id}`.
3. A content item whose id or slug equals the path.

Unpublished items are invisible to all three. A missing path renders the theme's
`404.php` with status 404.
