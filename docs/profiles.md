# Site profiles

A profile is a ready-made site: a `site.yaml` and a `content/` tree that
demonstrates one of the three kinds of site FlatCMS targets.

```
profiles/
  visiting-card/   site.yaml, profile.yaml, content/
  corporate/       site.yaml, profile.yaml, content/
  shop/            site.yaml, profile.yaml, content/
```

`profile.yaml` describes the profile for the CLI; `site.yaml` becomes
`config/site.yaml` on install.

## Installing

```bash
php bin/flatcms site:install <profile> [--force]
php bin/flatcms index:build
```

The command copies `profiles/<name>/content` over `content/` and copies
`site.yaml` over `config/site.yaml`. It refuses to run when `content/` is not
empty unless `--force` is given. Run `index:build` afterwards so queries see the
new tree.

## The three profiles

### visiting-card

A small site for a studio. One page of builder blocks, plus about, services and a
contact page. Modules: `builder`, `contact-form`, `admin`.

### corporate

A multi-page company site. A section of services whose children are product-like
items with prices, a team page and contacts. Modules: `builder`, `contact-form`,
`admin`.

### shop

A storefront. A catalog section with three products, a cart, checkout and order
pages. Modules: `builder`, `shop`, `contact-form`, `admin`.

## Writing your own profile

Copy an existing one and edit it:

```bash
cp -r profiles/visiting-card profiles/my-site
```

Then change `profile.yaml`, `site.yaml` and the files under `content/`. Keep
`profile.yaml`'s `modules` in sync with the `modules` block in `site.yaml`: a
module is only active when `config/site.yaml` enables it.
