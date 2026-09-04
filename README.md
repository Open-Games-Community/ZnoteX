<div align="center">

# ZnoteX

<img width="114" height="30" alt="index_f089e12e" src="https://github.com/user-attachments/assets/8a521795-fb9b-48c3-877b-977bea4ca716" />


**A complete website for your Open Tibia server.**

Version 2.0.0 · Maintained by [Open Games Community](https://opengamescommunity.com)

[Website](https://opengamescommunity.com) · [Source & releases](https://github.com/Open-Games-Community/ZnoteX) · [Themes](layouts/README.md) · [Plugins](plugins/README.md)

</div>

---

## About

ZnoteX is a full automatic account creator (AAC) and website for Open Tibia servers — account
registration, character management, highscores, guilds, houses, a forum, a shop and an admin panel,
all in one package. It is written in PHP with a simple procedural framework, so it is easy to read
and easy to modify.

The original ZnoteX went unmaintained for roughly five years. This repository picks the project
back up rather than starting over — we think it is the strongest foundation among the available
Open Tibia AAC projects, and we intend to keep building on it.

---

## Requirements

| | |
| --- | --- |
| **PHP** | 8.1 or newer — 8.1, 8.2, 8.3, 8.4 and 8.5 all supported |
| **Database** | MySQL or MariaDB |
| **Required extension** | `mysqli` |
| **Optional extensions** | `curl` (PayPal, reCaptcha, e-mail) · `openssl` (reCaptcha) · `gd` (guild images) · `apcu` (memory cache) |

> PHP 8.0 and older are **not** supported and will be refused at startup.

**Optional:** for e-mail verification and account recovery, download
[PHPMailer 6.x](https://github.com/PHPMailer/PHPMailer/releases) and extract it into the ZnoteX
directory as a folder named `PHPMailer`.

---

## Supported servers

Set `$config['ServerEngine']` in `config.php` to match your server:

| Server | `ServerEngine` |
| --- | --- |
| TFS 1.6 | `TFS_16` |
| TFS 1.1 – 1.4.2 | `TFS_10` |
| Canary / OTServBR-Global | `CANARY` |
| TFS 0.3.6+ / 0.4 / OTX | `TFS_03` |
| TFS 0.2.13+ | `TFS_02` |
| OTHire | `OTHIRE` |

TFS 1.0 is not supported.

**Canary notes** — two-factor authentication is unavailable (Canary's account table has nowhere to
store it), and the shop uses Znote's own points system rather than Canary coins.

---

## Web server stacks

You need Apache (or nginx) + PHP 8.1+ + MySQL/MariaDB. On Windows, any of these bundles work — just
make sure you grab a build that ships **PHP 8.1 or newer**.

| Stack | Download | Why pick it |
| --- | --- | --- |
| **Uniform Server** (UniServerZ) | [uniformserver.com](https://www.uniformserver.com/) · [SourceForge](https://sourceforge.net/projects/miniserver/) | Portable and very light on resources. No installer — unzip and run, easy to move or back up. A great default for a home-hosted server. |
| **XAMPP** | [apachefriends.org](https://www.apachefriends.org/) | The most popular and the easiest to set up. Includes phpMyAdmin. Changing PHP version means installing a different XAMPP build. |
| **WampServer** | [wampserver.com](https://www.wampserver.com/) | The fastest of the three, and you can switch PHP/MySQL versions from the tray icon. **Heavy on RAM** — MySQL has been seen using 5 GB+. Only worth it if the machine has memory to spare. |

On a Linux VPS or shared hosting you do not need any of these. Just set the hosting panel to PHP 8.1
or newer (8.3 / 8.4 recommended).

---

## Installation

### The installer

Extract ZnoteX into your web directory and open **`/install/`** in a browser. Six steps:

| | |
| --- | --- |
| **1. Requirements** | PHP version, `mysqli`, and whether `engine/cache/` is writable |
| **2. Database** | Credentials, and a check that your **OT server's own schema is already imported** |
| **3. Server** | Which engine this site sits in front of, the site name and its URL |
| **4. Schema** | Imports `SQL/znote_schema.sql` — only the `znote_*` tables |
| **5. Administrator** | Creates an account and a character, and remembers the name |
| **6. Finish** | Writes `config.local.php` and locks the installer |

**Import your OT server's schema first.** ZnoteX reads `accounts` and `players`; it has never
created them and will not pretend to. Step 2 refuses to continue until they exist — importing
TFS/Canary's own `schema.sql` afterwards would overwrite what the installer is about to write.

Step 5 creates a real, working administrator: the account, a character on it, and the password
hashed the way `login.php` expects on your engine. Step 6 puts that **account name** in
`page_admin_access`, so you can reach `/admin/` the moment the installer finishes. It writes to
**`config.local.php`**, not `config.php` — see below — though a checkbox on the last step will
write the admin name into `config.php` instead if you prefer.

When it is done, **delete the `install/` folder**. It refuses to run again on its own (step 6
leaves a lock file), but there is no reason to leave it on a public server.

### config.php and config.local.php

`config.php` holds every default and every comment. `config.local.php` holds only what is
specific to *this* install — database credentials, engine, site name, admin names — and is
included last, so it wins.

That split is what makes updating painless: a new ZnoteX release can ship a new `config.php`
without touching your settings. **Keep `config.local.php` out of version control.**

Most other settings are editable from **Admin Panel → Settings** without opening a file at all.

### Installing by hand

If you would rather not use the installer, or it cannot write the config file:

1. Import your OT server's schema, then `SQL/znote_schema.sql`, into the same database.
2. Create `config.local.php` next to `config.php`:

```php
<?php
$config['sqlHost']     = '127.0.0.1';
$config['sqlUser']     = 'your_db_user';
$config['sqlPassword'] = 'your_db_password';
$config['sqlDatabase'] = 'your_db_name';

$config['ServerEngine'] = 'TFS_10';   // see "Supported servers" above
$config['site_title']   = 'My Server';
$config['site_url']     = 'https://example.com/';
$config['page_admin_access'] = array('YourAccountName');
```

3. Make `engine/cache/` writable by the web server.
4. Open the site. If anything is misconfigured, the page tells you what to fix.

### Memory cache (APCu)

ZnoteX caches highscores, news and similar pages. It can keep that cache in files under
`engine/cache/`, or in RAM via the **APCu** extension.

`config.php` ships with `'memory' => true`, so a fresh install without APCu stops on every cached
page with *"Configuration error! APCu is not enabled."* If you see that, you have two choices —
install APCu, or switch to the file cache by putting this in `config.local.php`:

```php
$config['cache']['memory'] = false;
```

The file cache needs no extension, works everywhere, and only requires `engine/cache/` to be
writable.

**APCu is optional.** It saves a few disk reads per request. On a local or low-traffic server you
will not notice the difference — it is worth installing once you have real player traffic.

#### Installing APCu on Windows

Download from **[pecl.php.net/package/APCu/5.1.28](https://pecl.php.net/package/APCu/5.1.28)** and
click the **DLL** link. The build must match your PHP exactly — check yours with `php -i` or
`phpinfo()`:

| Filename part | Comes from |
| --- | --- |
| `8.3` | your PHP version |
| `ts` / `nts` | *Thread Safety* — `enabled` means **ts** |
| `vs16` / `vs17` | *Compiler* — Visual C++ 2019 is `vs16`, 2022 is `vs17` |
| `x64` / `x86` | *Architecture* |

Uniform Server is thread-safe, so with PHP 8.3 it needs
`php_apcu-5.1.28-8.3-ts-vs16-x64.zip`. Most Windows guides say `nts` because that is what other
stacks use — picking the wrong one means the DLL is ignored with no error.

1. Copy `php_apcu.dll` from the zip into your PHP `extensions` (or `ext`) folder — the path in
   `extension_dir`.
2. Add to your `php.ini`:
   ```ini
   extension=apcu
   apc.enabled=1
   ```
   Uniform Server has no single `php.ini`: the web server reads `php_production.ini` or
   `php_development.ini` from `core/php83/` depending on the mode it is running in.
3. Restart Apache, then set `memory` to `true`.

On Linux, `pecl install apcu` or your distribution's `php-apcu` package.

### Already have players?

Open **`/special/`** to convert an existing OT database for ZnoteX.

### Upgrading

Replace everything **except** `config.local.php`, `layouts/`, `plugins/` and `engine/cache/`.
Then apply any new file in `SQL/migrations/`, and check **Admin Panel → Plugins** in case a
plugin has an update waiting.

---

## Features

<details open>
<summary><b>Accounts &amp; characters</b></summary>

- Account registration, password and e-mail changes
- E-mail verification and lost-account recovery
- Two-factor authentication
- reCaptcha anti-spam
- Character creation with custom vocations, starting skills and towns
- Starting items via the included Lua script
- Soft character deletion, and hiding characters from the public list
- Support helpdesk with tickets

</details>

<details>
<summary><b>Community</b></summary>

- **Forum** — custom boards, guild boards, admin-only feedback board, level restrictions,
  outfit avatars, player positions, sticky / closed / hidden threads, and search
- **Guilds** — create and disband, invites, ranks, nicknames, guild images and descriptions,
  war declarations and ongoing war tracking
- **Character profiles** — vocation, level, guild, skills, full outfit and equipment display,
  achievements, deaths, quest progression and player comments

</details>

<details>
<summary><b>Server information</b></summary>

- Highscores with vocation and skill filters
- Latest deaths and latest kills
- Server info page with PvP settings, rates and experience stages (reads `config.lua` and `stages.xml`)
- Spells list with vocation filters (reads `spells.xml`)
- Item list (reads `items.xml`)
- Houses list with town filters, house bidding, and direct purchase with shop points
- Downloads page with client links and a connection guide

</details>

<details>
<summary><b>Shop &amp; payments</b></summary>

- Database shop offers managed from the admin panel: items, premium days, gender change, name change, outfits, mounts, and custom types
- Item market: buy and sell listings, item search, price comparison and transaction history
- Payment gateways: **PayPal**, **PagSeguro** and **PayGol** (SMS)

</details>

<details>
<summary><b>Administration</b></summary>

- New built-in admin control panel available at `/admin/`
- Responsive sidebar layout with day/night theme switch
- Dashboard with accounts, characters, online players, guilds, houses, shop points and moderation queues
- Delete characters, ban characters and accounts
- Change account passwords, grant in-game positions
- Give shop points, edit player level and skills
- Teleport one player or everyone to a town or position
- Review in-game bug reports, helpdesk tickets and forum feedback
- Shop Manager for adding, previewing, hiding and removing database shop offers
- Shop Pending / History page for pending deliveries and completed orders
- Moderate gallery uploads, post news and changelogs

</details>

<details>
<summary><b>Setup, themes &amp; extensions</b></summary>

- Six-step web installer at `/install/` that checks requirements, verifies your OT schema is
  present, imports the ZnoteX tables, creates the first administrator and writes the config
- Theme system: every theme is a folder of plain HTML and CSS under `layouts/`, switchable from
  the admin panel, with per-theme options, child themes and one-click install from a repository
- Plugin system: add pages, admin pages, tables and behaviour from `plugins/` with no core edit,
  install and update from the admin panel
- Settings editor for most of `config.php`, and a menu builder for the site navigation
- Maintenance mode that keeps administrators and the login page reachable

</details>

<details>
<summary><b>Performance</b></summary>

- Built-in cache system that serves treated data from flat files instead of hitting MySQL on
  every page load

</details>

---

## Admin Control Panel

ZnoteX now includes a modern admin control panel at `/admin/`. Access is controlled by
`$config['page_admin_access']` in `config.php`.

Included modules:

- **Dashboard** - server overview, environment details, recent accounts, recent characters, top point balances and open queues
- **Player Tools** - account lookup, character lookup, password changes, points, access, positions and teleport actions
- **Character Skills** - edit level, magic level, health, mana and skills from one admin page
- **News** - create and manage website news and changelog posts
- **Gallery** - review and moderate uploaded gallery images
- **Bug Reports** - review in-game bug reports
- **Helpdesk** - manage support tickets
- **Feedback Board** - quick access to admin feedback threads
- **Shop Manager** - add, preview, hide and delete database shop offers
- **Shop Pending / History** - view pending shop deliveries and completed purchases
- **Character Auctions** - manage character auction settings and activity
- **Layouts** - switch theme, edit its options, browse and install themes from a repository
- **Menus** - build the site navigation without touching a template
- **Settings** - edit most of `config.php` from the browser
- **Plugins** - install, update, enable and disable what is in `plugins/`
- **Accounts** - browse and inspect accounts
- **Visitors** - who has been on the site

Shop offers are no longer configured through `$config['shop_offers']`. They are stored in
`znote_shop_offers` and managed from **Admin Panel > Shop Manager**.

---

## Themes

Every theme is a folder under `layouts/`. A theme is **plain HTML and CSS** — the PHP stays in
ZnoteX, so editing one is editing markup, not untangling a template engine. `layouts/default/`
is the theme that ships; `layouts/_example/` is a documented skeleton to copy.

Everything below is in **Admin Panel → Layouts**.

**Switching.** Every installed theme is listed with a screenshot, 12 per page. Click one to make
it active. It applies to the public site only — the admin panel never changes.

**Options.** A theme can declare its own settings in `theme.json` — social links, a tagline, a
colour. They then appear under *Options* on that theme's card and are stored in the database, so
you change them from the panel instead of editing the theme's files.

**Installing one.** Two ways:

- *Manually* — unzip the theme folder into `layouts/`. It appears on the next page load.
- *From a repository* — press **Browse themes** to list themes hosted elsewhere and install one
  with a button.

The repository is configured by `$config['layout_repository']` in `config.php`, and points at
this project's `layouts` branch by default. Downloads are refused unless the URL is **https** and
its host is on `allowed_hosts` — a theme is code that runs on your server, so only point it at a
repository you trust. The tooling that packages themes and regenerates a catalogue lives on the
`layouts` branch, alongside the archives themselves.

**Child themes.** A theme can name another as its `parent` and override only the files it wants.
The rest falls through to the parent, so a colour change is one stylesheet rather than a fork —
and the parent can still be updated underneath it.

See [layouts/README.md](layouts/README.md) for the full contract.

---

## Plugins

A plugin is a folder under `plugins/` that adds public pages, admin pages, database tables and
behaviour **without editing a single ZnoteX file** — so an update never costs you your work.

```
plugins/my_plugin/
  plugin.json        name, version, author, description   [required]
  plugin.php         registers hooks
  pages/<page>.php   public page at page.php?plugin=my_plugin&p=<page>
  admin/<mod>.php    admin page, listed in the sidebar
  install.sql        tables, created on install
  assets/            css, js, images
```

Everything below is in **Admin Panel → Plugins**.

**Installing one.** Download it, unzip the folder into `plugins/`, reload the panel, press
**Install**. That runs its `install.sql` and switches it on. ZnoteX **never downloads a plugin by
itself**: a plugin is PHP that runs on every page of your site, so putting the files there stays
a deliberate act rather than a button.

**Updating one.** Replace the folder with the newer version. If its `plugin.json` carries a
higher version than the one recorded at install time, an **Update** button appears and applies
whatever the new version needs.

**Removing one.** *Disable* stops a plugin; *Uninstall* also forgets its version. Neither ever
drops a table — removing a plugin for good means deleting its folder and dropping its tables
yourself.

> A plugin runs with the same privileges as the rest of the site, and nothing sandboxes it.
> Install plugins whose author you know or whose code you have read.

`plugins/shop_coupons/` is a working example — redeemable codes that either credit shop points or
take a percentage off the next purchase — and is commented as a tutorial. See
[plugins/README.md](plugins/README.md) for the contract and the list of hooks.

---

## Contributing

Issues and pull requests are welcome at
[github.com/Open-Games-Community/ZnoteX](https://github.com/Open-Games-Community/ZnoteX).

## License

See [LICENSE](LICENSE). Original ZnoteAAC modified by Alex renamed to ZnoteX; layout by Blackwolf (Snavy).
