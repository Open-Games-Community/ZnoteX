# Making a ZnoteX theme

A theme is one folder in `layouts/`. It holds **HTML, CSS, JS and images only** —
never database queries, never business logic. The root pages keep doing the work;
your theme decides how the result looks.

Nothing outside your folder needs to change. You never touch `engine/`,
the root `.php` files, or `config.php`.

---

## Quick start

1. Copy `layouts/_example/` and rename it, e.g. `layouts/oldhell/`.
   Folder names may only contain `a-z`, `0-9`, `-` and `_`.
2. Edit `theme.json`.
3. Open the admin panel → **Layout** → **Activate**.

That is the whole install. Your theme appears in the panel the moment the folder
exists; there is no registry to edit.

---

## What a theme folder can contain

```
layouts/yourtheme/
  theme.json           name, author, version, description       [recommended]
  screenshot.png       thumbnail in the admin panel             [optional]

  shells/
    default.php        the page frame                           [REQUIRED]
    wide.php           any other frame you want                 [optional]

  views/
    index.php          the middle block of index.php            [optional]
    highscores.php     the middle block of highscores.php       [optional]

  pages/
    wiki.php           a page YOUR theme adds to the site       [optional]

  assets/
    css/style.css      your stylesheet
    js/theme.js        your scripts
    img/...            your images

  menu.php             only if your shell calls theme_menu()    [optional]
  aside.php            only if your shell calls theme_sidebar() [optional]
  widgets/             only if your shell calls widget()        [optional]
```

**`shells/default.php` is the only required file.** Everything else falls back
to `layouts/default/`. A theme made of one shell and one stylesheet already
redresses the entire site.

---

## The shell

The frame every page renders inside. Plain HTML plus a few one-liners:

```php
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= theme_title() ?></title>
  <link rel="stylesheet" href="<?= theme_asset('css/style.css') ?>">
</head>
<body class="<?= theme_body_class() ?>">

  <header class="my-header">
    <nav>
      <a href="index.php">Home</a>
      <a href="highscores.php">Highscores</a>
    </nav>
  </header>

  <main>
    <?php theme_content(); ?>
  </main>

  <footer>&copy; <?= theme_title() ?></footer>
</body>
</html>
```

`theme_content()` is the only line you cannot remove — it is where the page goes.
Everything else is yours to move, delete or rewrite.

### Functions available in a shell, view or page

| Call | Does |
| --- | --- |
| `theme_content()` | prints the page body — **required, once, in the shell** |
| `theme_title()` | site title from `config.php`, already escaped |
| `theme_body_class()` | `"theme-yourtheme page_highscores"` |
| `theme_asset('css/style.css')` | URL of a file in your `assets/` |
| `theme_menu()` | includes your `menu.php` |
| `theme_sidebar()` | includes your `aside.php` |
| `widget('login')` | includes one file from your `widgets/` |
| `theme_shell('wide')` | render this page in `shells/wide.php` instead |
| `$config` | everything from `config.php` |
| `user_logged_in()`, `is_admin($user_data)` | session state |

Write your menu directly in the shell if you prefer — `theme_menu()` exists only
if you want it. Nothing is imposed.

---

## Child themes — building on another theme

Name a parent in `theme.json` and your theme only has to ship what it changes:

```json
{
  "name": "Exodus Dark",
  "parent": "default"
}
```

Files are then looked up **child → parent → default**. A stylesheet and two
views on top of a full parent is a complete, working theme — and a fix in the
parent reaches every child without touching them.

`layouts/_childexample/` is exactly that: one stylesheet, nothing else.

Parents can themselves have parents, up to 8 levels. A cycle or a missing
parent is ignored rather than fatal — the chain just falls through to
`default`, so a typo degrades the look instead of taking the site down.

### One rule that matters

Inside a shell, use **`theme_include()`**, never `theme_path()`:

```php
<?php theme_include('parts/head.php'); ?>                       // correct
<?php include theme_path() . '/parts/head.php'; ?>              // breaks children
```

`theme_path()` points at the active theme only. A child that does not ship
`parts/head.php` would include nothing and render without its frame.
`theme_include()` walks the chain. Pass variables as a second argument, since
an include from inside a function cannot see the caller's locals:

```php
<?php theme_include('parts/box.php', ['title' => $title]); ?>
```

---

## Views — restyling an existing page

A view is the middle block of one root page. The page's logic has already run,
so every variable it prepared is yours to use.

`layouts/yourtheme/views/highscores.php`:

```php
<div class="my-panel">
  <h1>Ranking for <?= skillName($type) ?></h1>

  <table class="table table-striped">
    <tr class="yellow"><td>#</td><td>Name</td><td>Level</td></tr>
    <?php foreach ($players as $player): ?>
      <tr>
        <td><?= (int)$player['rank'] ?></td>
        <td><a href="characterprofile.php?name=<?= urlencode($player['name']) ?>">
          <?= htmlspecialchars($player['name'], ENT_QUOTES, 'UTF-8') ?>
        </a></td>
        <td><?= (int)$player['level'] ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>
```

**Only write the views you actually want to change.** Any page without a view of
its own uses the default theme's markup, inside your shell, styled by your CSS.

To find out which variables a page gives you, open the root file — e.g.
`highscores.php` — and read the logic above `view('highscores')`.

---

## Pages — adding pages of your own

Drop a file in `pages/` and it is live. No registration.

`layouts/yourtheme/pages/wiki.php` → **`page.php?p=wiki`**

```php
<h1>Wiki</h1>
<p>Anything you want.</p>
```

It renders inside your shell like every other page, and gets the body class
`page_wiki` so you can target it from CSS.

Pretty URLs, if you want them, in `.htaccess`:

```apache
RewriteRule ^([a-z0-9_-]+)\.html$ page.php?p=$1 [L,QSA]
```

---

## Several frames in one theme

Some pages need a different structure — a landing page with no sidebar, a
full-width page. Add another shell and ask for it from the view or page:

```php
<?php theme_shell('wide'); ?>
<div class="hero">...</div>
```

`shells/wide.php` is a complete frame, just like `default.php`.

---

## The CSS contract

This is the part people miss.

The root pages emit some markup themselves, with class names your theme does not
control. **Style these or those pages render unstyled.** The full list:

| Class | Where |
| --- | --- |
| `table`, `table-striped`, `table-hover`, `tbl-hover` | every listing page |
| `tr.yellow` | table header rows — Znote does not use `<th>` |
| `znoteTable`, `ThreadTable` | forum and helpdesk |
| `btn`, `btn-primary`, `btn-success`, `btn-warning`, `btn-danger`, `btn-info` | every form |
| `form-control` | inputs |
| `special` | highlighted rows |
| `txt`, `zheadline`, `bighr` | text helpers |
| `outfitColumn` | outfit images in listings |
| `span12`, `show`, `wtf`, `nav_link` | odds and ends |

`layouts/_example/assets/css/style.css` styles all of them and is annotated —
copy that section as your starting point.

If your shell calls `theme_sidebar()` or `widget()`, you also need `.well`,
`.widget` and `.header`.

---

## Rules

- **No logic in a theme.** No `mysql_insert`, no `UPDATE`. Reading data for a
  page of your own is fine; that is what `pages/` is for.
- **Escape anything from the database**: `htmlspecialchars($x, ENT_QUOTES, 'UTF-8')`.
- **Never edit `layouts/_example/`.** It is the reference every theme is copied
  from. Copy it, do not modify it.
- **Never edit `layouts/default/`** either, unless you mean to change the fallback
  for every theme on the site.
- **Reference your files through `theme_asset()`**, not with a hardcoded path.
  It keeps working when your folder is renamed, and falls back to the default
  theme when a file is missing.

Shared vendor files that are not part of any theme live in `assets/`
(`assets/fontawesome/`, `assets/js/jquery.js`). The admin panel uses them too,
which is why they are not inside a theme.

---

## Troubleshooting

**The site is unstyled.** Your shell is probably not loading your CSS. Check
`theme_asset('css/style.css')` and that the file is at
`layouts/yourtheme/assets/css/style.css`.

**A page is blank.** Look in the PHP error log for `[ZnoteX theme]`. A view that
fails to resolve is logged there.

**A page renders but has no frame.** `shells/default.php` is missing or has a
parse error. The admin panel refuses to activate a theme without it, but it can
break after activation.

**My change does nothing.** Confirm which theme is active: admin panel →
Layout. The active one is marked. The setting lives in the `znote_config`
table, not in `config.php`.

**The admin panel looks unchanged.** That is intentional. The panel has its own
styling in `admin/assets/` and is identical for every theme.
