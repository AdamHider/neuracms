# Shop module

The shop module adds a catalog, a cart, checkout and orders. It works with the
same items as the rest of the CMS: a product is just an item of type `product`
with a `price` and a `sku`.

## Routes

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/cart` | Show the cart |
| POST | `/cart/add` | Add a product by `sku` and `qty` |
| POST | `/cart/update` | Set quantities |
| POST | `/cart/remove` | Remove a line |
| GET | `/checkout` | Checkout form |
| POST | `/checkout` | Place the order |
| GET | `/order/{id}` | Order confirmation |

`/cart/add` answers with JSON when the request is AJAX, so a theme can update the
cart count without a reload.

## Products

```yaml
type: product
title: Чайник заварочный
price: 1200
sku: TEAPOT-1
stock: 5
tags: [tea, kitchen]
```

`price` and `sku` drive the catalog and the cart. Products are found by `sku`
through the index, so `sku` must be listed in `config/schemas/product.yaml`
under `indexed`.

## Cart

The cart lives in the session as lines of sku, item id, title, price and quantity.
It is cleared after a successful order.

## Orders

An order is one JSON file under `var/orders/<year>/<month>/<id>.json`, with the
lines, the customer details, the total and the payment provider. Order ids are
`<prefix><date>-<time>-<random>`, where the prefix comes from
`shop.order_prefix` in the module config.

## Payment providers

Providers implement a small interface with `name()`, `enabled()`, `start()` and
`autoConfirm()`. `start()` returns the URL to redirect to after the order is
stored; `autoConfirm()` says whether the order is paid immediately. Two providers
ship with the module, both manual:

```yaml
providers:
  manual:
    enabled: true
    title: Оплата при получении
  cash:
    enabled: true
    title: Наличные курьеру
```

Add your own by listening to the `shop.providers` event and registering a
provider on the registry. The `shop.order.created` event fires once an order is
stored, which is the place to send a confirmation email or push to an external
system.

## Settings

```yaml
shop:
  currency: RUB
  currency_symbol: "₽"
  provider: manual       # default provider
  catalog_url: /products
  order_prefix: ORD
```
