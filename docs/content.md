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

## Pages and elements

Content is split into two logical groups, both stored the same way under
`content/`:

- **Pages** are the core: the routable, navigable content of the site. A page's
  appearance is defined **only** in the page builder (its `layout` property), so
  the admin panel shows pages in their own list (**Страницы**, `/admin/pages`)
  and links each one to the builder.
- **Elements** are everything else: structured data with no appearance of its own
  (products, categories, services, …). They live in the **Элементы** list
  (`/admin/elements`) and are edited as plain properties. Pages display them with
  the builder's `elements` particle.

A type's group is its `kind`, set in the schema file:

```yaml
type: service
label: Услуга
kind: element      # "page" or "element"
```

`kind` defaults to `page` for the core page-like types (`page`, `section`) and to
`element` for every other type, so existing sites keep working without changes.
The admin type editor writes it back as a "Назначение" selector. In code:

```php
$app->schema->isPage('page');       // true
$app->schema->isElement('product'); // true
$app->schema->pages();              // page-like types
$app->schema->elements();           // element types
$app->query()->kind('element')->find();
```

## Content types

A content type is a file in `config/schemas/<type>.yaml`. It describes the fields
of items of that type, which of them the index stores, and any relations to other
types. Types are created and edited from the admin panel under **Типы контента**
(`/admin/types`), which writes the same files.

```yaml
type: product
label: Товар
fields:
  title: { type: string, required: true, label: Название }
  price: { type: float, required: true, label: Цена }
  sku:   { type: string, label: Артикул }
  tags:  { type: list, label: Теги }
indexed: [price, sku, tags]
```

Field types are `string`, `text`, `markdown`, `int`, `float`, `bool`, `date`,
`select`, `list`, `file` and `relation`. A field may also set `required`,
`default` and `label`. The editor renders exactly the fields the type describes;
a type with no `fields` shows only the common properties (title, slug, order,
published).

### Built-in types

`page` and `section` ship with the core and are marked `builtin: true`. They can
be edited but not deleted from the admin panel, so a site always keeps the types
the core itself relies on. Any other type is custom and can be removed.

### Relations between types

A field of type `relation` points at items of another type. It carries `to` (the
target type) and an optional `multiple` flag. This is how one content type is
attached to another:

```yaml
type: product
fields:
  title:    { type: string, required: true, label: Название }
  price:    { type: float, required: true, label: Цена }
  category: { type: relation, to: category, label: Категория }          # one
  related:  { type: relation, to: product, multiple: true, label: Похожие } # many
indexed: [price, category]
```

In the item editor a single relation renders as a dropdown of the target type's
items, and a `multiple` relation as a multi-select. Saving stores the referenced
item **ids** (paths such as `categories/tea`). The relation target must exist and
match the declared type, otherwise validation fails with an error.

A type can also relate to itself (`to: product` above, or a category tree via
`to: category`). Relation fields are only queryable when listed under `indexed`,
like any other field.

```php
$app->query()->type('product')->where('category', '=', 'categories/tea')->find();
$app->schema->relations('product');       // ['category' => ['to' => 'category', ...]]
$app->schema->referencedBy('category');   // ['product']
```


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
