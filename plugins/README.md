# ZnoteX plugins

A plugin is a folder in `plugins/`. It can add public pages, admin pages,
database tables and behaviour **without editing a single ZnoteX file** — which
is the whole point: the next update replaces ZnoteX and leaves your work alone.

Install, update, enable and disable them in **Admin Panel → Plugins**.

**Installing one:** download it, unzip the folder into `plugins/`, reload the
panel, press *Install*. That runs its `install.sql` and switches it on. ZnoteX
never downloads a plugin by itself — a plugin is PHP that runs on every page of
your site, so putting the files there stays a deliberate act.

**Updating one:** replace the folder with the newer version. If its
`plugin.json` carries a higher `version` than the one recorded at install time,
an *Update* button appears and re-runs `install.sql`. That is why the file has
to be idempotent — see below.

---

## The folder

```
plugins/my_plugin/
  plugin.json        name, version, author, description   [required]
  plugin.php         registers hooks and helpers          [optional]
  pages/<page>.php   public page at page.php?plugin=my_plugin&p=<page>
  admin/<mod>.php    admin page, listed in the sidebar
  install.sql        tables, created when the plugin is enabled
  assets/            css, js, images
```

Only `plugin.json` is required. A plugin that just reacts to a hook is a
`plugin.json` and a `plugin.php`, nothing else.

The folder name is the plugin's identity: lowercase letters, digits, `-` and
`_`. A folder starting with `_` is ignored, which is how you park one.

### plugin.json

```json
{
  "name": "My Plugin",
  "version": "1.0.0",
  "author": "You",
  "description": "One or two sentences shown in the admin panel.",
  "url": "https://example.com",
  "requires": "2.0.0"
}
```

---

## plugin.php

Loaded on **every request** while the plugin is enabled, right after the
database and the settings are up. So:

- register hooks and declare functions here — that is all it is for;
- never print anything;
- keep it cheap. A query here is a query on every page of the site. Do the work
  inside the hook, where it only runs when it is needed.

A `plugin.php` that throws is skipped and logged. One broken plugin does not
take the site down.

---

## Pages

Drop `pages/shop.php` into your plugin and it is live at
`page.php?plugin=my_plugin&p=shop`. Nothing to register.

The file is a **fragment**: `page.php` has already run `engine/init.php` and
opened the theme, so `$config`, `$user_data` and the `mysql_*` helpers are all
there, and the active theme wraps whatever you print. Do not include
`init.php`, and do not print a header or a footer.

`?p=` is matched against the files that actually exist, so it can never reach
anything outside `pages/`. A page of a plugin that is not installed and enabled
returns 404.

`plugins/.htaccess` blocks direct requests to anything but `assets/`, so nobody
can run one of your files outside `page.php` or read your `install.sql`. Guard
your pages anyway — someone will run ZnoteX on a server that ignores
`.htaccess`:

```php
if (!isset($config)) { http_response_code(403); die('Direct access denied.'); }
```

Link to one with `znote_plugin_url('my_plugin', 'shop')`, and to a file in
`assets/` with `znote_plugin_asset('my_plugin', 'style.css')`.

The `<body>` gets a `page_my_plugin_shop` class, so a theme can style your page
from CSS alone.

---

## Admin pages

Drop `admin/orders.php` into your plugin and it appears in the admin sidebar.
It is written exactly like a built-in module — see `admin/modules/_template.php`
— with the same docblock header and the same `acp_*` helpers:

```php
<?php
/**
 * Title: Orders
 * Icon: fa-shopping-cart
 * Group: Economy
 * Order: 60
 * Description: One line under the page title.
 */
```

Its key is namespaced `my_plugin__orders`, so a plugin can never shadow a core
module by picking the same filename. Use that key with `acp_redirect()`.

---

## install.sql

Run on *Install* and again on every *Update*, statement by statement.

**Every statement must be idempotent** — `CREATE TABLE IF NOT EXISTS` and the
like. ZnoteX does not track which statements already ran, so an update simply
runs the whole file again: whatever the new version added gets created, and what
was already there is left alone with its data intact.

Neither *Disable* nor *Uninstall* **ever drops a table**. Losing a player's data
because someone clicked a button would be the wrong default. Removing a plugin
for good is deleting its folder and dropping its tables yourself.

### Versioning

The version in `plugin.json` is what the update check compares, with PHP's
`version_compare()`. Raise it whenever you ship a change that needs
`install.sql` re-run, and keep it plain: `1.0.0`, `1.1.0`, `2.0.0`.

---

## Hooks

```php
// React to something. Return value ignored.
znote_hook_register('shop.purchased', function (array $data) { ... });

// Change a value. Gets the current value, returns the new one.
znote_hook_register('shop.price', function ($price, array $data) { return $price - 5; });

// Add markup. Whatever you return is inserted into the page.
znote_hook_register('page.footer', function () { return '<div>...</div>'; });
```

An optional third argument is the priority, default `10`, lowest first.

A callback that throws is caught and logged, and the site carries on. That is
the difference between an extension point and a landmine.

### The hooks ZnoteX fires

| Hook | Kind | When | `$data` |
|---|---|---|---|
| `plugins.loaded` | notify | every plugin is loaded | — |
| `page.head` | collect | before `</head>` | — |
| `page.footer` | collect | before `</body>` | — |
| `shop.price` | filter | before a purchase is priced | `account_id`, `offer_id`, `offer` |
| `shop.purchased` | notify | after the points are taken | `account_id`, `offer_id`, `type`, `itemid`, `count`, `points` |
| `account.registered` | notify | after an account is created | `name`, `email` |
| `character.created` | notify | after a character is created | `name`, `account_id`, `vocation` |

`shop.price` is the one to reach for when you want to change what something
costs. Its result is used for all three of the affordability check, the points
actually deducted and the shop log, so they cannot disagree.

`page.head` and `page.footer` are injected into the theme's own output, so they
work with themes written long before your plugin existed — including ones that
never call a plugin function.

### Your own hooks

Publish one and other plugins can extend yours:

```php
znote_hook('coupon.redeemed', array('code' => $code, 'account_id' => $id));
```

### Need a hook that isn't there?

Adding one is two lines in the core file, and hooks with no listeners cost
almost nothing. Open an issue rather than forking.

---

## The example

`plugins/shop_coupons/` is a working plugin that uses every one of these:
a public page, an admin page, its own tables, a filter hook that discounts a
purchase, a notify hook that consumes the discount afterwards, and a collect
hook that puts a banner in the footer. Read it top to bottom — it is commented
as a tutorial rather than as production code.

---

## Checklist

- [ ] Folder name is lowercase, unique, and matches nothing in ZnoteX.
- [ ] `plugin.json` is valid JSON.
- [ ] `plugin.php` prints nothing and runs no queries at load.
- [ ] `install.sql` is idempotent.
- [ ] Tables and functions are prefixed with the plugin name.
- [ ] Every form has `<?= acp_csrf_field() ?>` (admin) or `Token::create()` (public).
- [ ] Every value that reaches SQL goes through `esc()` or `(int)`.
- [ ] Every value that reaches the page goes through `h()` or `htmlspecialchars()`.
- [ ] It still works when it is disabled — that is, nothing else references it.
