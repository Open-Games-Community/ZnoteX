<div align="center">

# ZnoteX

**A complete website for your Open Tibia server.**

Version 2.0.0 · Maintained by [Open Games Community](https://opengamescommunity.com)

[Website](https://opengamescommunity.com) · [Source & releases](https://github.com/Open-Games-Community/ZnoteX) · [Templates & plugins](https://otland.net/forums/website-applications.118/)

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
| **Optional extensions** | `curl` (PayPal, reCaptcha, e-mail) · `openssl` (reCaptcha) · `gd` (guild images) |

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

**1. Upload the files**

Extract ZnoteX into your web directory (for example `C:\UniServer\www\` or `/var/www/html/`).

**2. Create the database**

Import your OT server's own schema first, then import
`engine/database/znote_schema.sql` into the same database.

**3. Configure `config.php`**

```php
$config['ServerEngine']     = 'TFS_10';   // see "Supported servers" above
$config['page_admin_access'] = array('YourAccountName');

$config['sqlHost']     = '127.0.0.1';
$config['sqlUser']     = 'your_db_user';
$config['sqlPassword'] = 'your_db_password';
$config['sqlDatabase'] = 'your_db_name';
```

**4. Open the site**

Visit your site in a browser. If anything is misconfigured, the page tells you what to fix.

**5. Existing server?**

If you already have an active OT server with players, open `/special/` to convert the existing
database for ZnoteX.

**6. Permissions**

Make sure `engine/cache/` is writable by the web server.

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

- Shop offers: items, premium days, gender change, name change, outfits, mounts, and custom types
- Item market: buy and sell listings, item search, price comparison and transaction history
- Payment gateways: **PayPal**, **PagSeguro** and **PayGol** (SMS)

</details>

<details>
<summary><b>Administration</b></summary>

- Delete characters, ban characters and accounts
- Change account passwords, grant in-game positions
- Give shop points, edit player level and skills
- Teleport one player or everyone to a town or position
- Review in-game bug reports and forum feedback
- Shop transaction overview
- Moderate gallery uploads, post news and changelogs

</details>

<details>
<summary><b>Performance</b></summary>

- Built-in cache system that serves treated data from flat files instead of hitting MySQL on
  every page load

</details>

---

## Contributing

Issues and pull requests are welcome at
[github.com/Open-Games-Community/ZnoteX](https://github.com/Open-Games-Community/ZnoteX).

## License

See [LICENSE](LICENSE). Original ZnoteX by Znote; layout by Blackwolf (Snavy).
