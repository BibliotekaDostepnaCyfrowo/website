# Biblioteka Dostępna Cyfrowo on WordPress

An example website for a public library, on WordPress. This folder holds
everything that is ours: the theme, a setup script and the local development
environment. Content (posts, pages, media) lives in WordPress, not in git; the
setup script only seeds example content for a fictional library, which staff
then replace.

Everything runs in Docker. Nothing needs to be installed on your machine.

```
wordpress/
├── compose.yml                           local environment (WordPress, MariaDB, WP-CLI, screenshots)
├── theme/biblioteka-dostepna-cyfrowo/    the block theme: theme.json, templates, parts, patterns
├── setup/setup.php                       idempotent site setup (settings, pages, editable blocks, example content)
├── setup/clean-content.php               idempotent cleanup of imported content
├── mu-plugins/smtp.php                   sends mail through SMTP_* env (no-op when unset)
├── Dockerfile, docker/                   the production image
├── tools/                                screenshots, block validation, accessibility check
└── import/                               WordPress export files (gitignored, contains site content)
```

## Local environment

Requires Docker. The site runs at http://localhost:8088 (login `admin` / `admin`, local only).

```bash
cd wordpress
docker compose up -d                       # start WordPress + database
docker compose run --rm cli wp <command>   # run any WP-CLI command
docker compose down                        # stop (data is kept)
docker compose down -v                     # stop and delete all local data
```

The theme folder is mounted into the container, so edits show up on reload.

### Fresh example site

```bash
docker compose up -d
docker compose run --rm cli wp core install --url=http://localhost:8088 \
  --title=Biblioteka --admin_user=admin --admin_password=admin \
  --admin_email=admin@example.test --skip-email
docker compose run --rm cli wp post delete 1 2 3 --force   # sample content of a fresh install
docker compose run --rm cli wp language core install pl_PL --activate
docker compose run --rm cli wp theme activate biblioteka-dostepna-cyfrowo
docker compose run --rm cli wp eval-file /setup/setup.php
```

`setup.php` seeds a fictional library: its name and logo, three branches at
example addresses (`example.org` emails, `+48 00 …` phones), opening hours,
numbers, pages (O nas, Kontakt, Katalog online, Deklaracja dostępności) and,
on a site without posts, three example news posts. The data is at the top of
the script; everything it creates is ordinary content, edited in the admin.

### Copy of an existing site

1. In the existing site's admin: Narzędzia → Eksport → Cała zawartość → Pobierz plik eksportu.
2. Put the file in `wordpress/import/export.xml`.
3. Put your library's name and branches in the data at the top of `setup/setup.php`;
   otherwise the import gets the example library's name, branches and pages.
4. Import it and run the setup (the import downloads all media from the old site, which can take an hour):

```bash
docker compose up -d
docker compose run --rm cli wp core install --url=http://localhost:8088 \
  --title=Biblioteka --admin_user=admin --admin_password=admin \
  --admin_email=admin@example.test --skip-email
docker compose run --rm cli wp post delete 1 2 3 --force   # sample content of a fresh install
docker compose run --rm cli wp language core install pl_PL --activate
docker compose run --rm cli wp plugin install wordpress-importer --activate
docker compose run --rm cli wp import /import/export.xml --authors=skip
docker compose run --rm cli wp theme activate biblioteka-dostepna-cyfrowo
docker compose run --rm cli wp eval-file /setup/setup.php
docker compose run --rm cli wp eval-file /setup/clean-content.php
```

`clean-content.php` replaces emoji images hotlinked from Facebook (copied along
with Facebook posts) with real emoji characters and strips stray forms from posts.

## How the theme works

- **Design tokens** (colours, Montserrat, font sizes, spacing, 1280px width) are in
  `theme.json`. Staff can only pick from this palette and these sizes.
- **Templates** (`templates/`) define the home page, news list, article, page,
  search and 404. Header, footer and the "Dziś jesteśmy otwarci" bar are in `parts/`.
- **Content staff change** lives in synced patterns, which Editors can edit under
  **Stałe treści** in the admin menu:
  - *Godziny otwarcia*: one table, shown in every branch and in the "today" bar
    (a branch can have its own table instead, see below)
  - *Filie (adresy i mapa)*: branch addresses, contacts and one map of all branches
  - *Partnerzy*: partner logos
  - *Stopka: dane kontaktowe* and *Stopka: dokumenty i linki*

  Templates include them with `[bdc_wzorzec slug="…"]`, so theme files never depend
  on database IDs. `setup/setup.php` creates them once and never overwrites them.
- **Today's opening hours** are worked out in `functions.php` when the page is
  rendered (no JavaScript): the "Dziś jesteśmy otwarci" bar on the home page
  (`[bdc_dzisiaj]`) shows each branch's hours for today, every branch shows a
  "Dziś: …" line above its *Godziny otwarcia* toggle, and today's row in the
  table is highlighted. They are read from each branch's table in *Filie*, whose
  first column must be the Polish day name; a missing day or hours without a digit
  mean closed. To give one branch different hours (holiday, stocktaking), open
  *Filie*, select that branch's *Godziny otwarcia* block, choose "Odłącz" (detach)
  and edit its copy; the other branches keep the shared table.
  Pages are rendered fresh on every request. If a page cache is ever added, it
  must not keep pages across midnight, or the bar shows yesterday's hours.
- **Fonts** are self-hosted (no requests to Google) and preloaded, so text doesn't
  reflow when they arrive.
- **Branches map** (home page and /kontakt/): the *Filie* pattern holds a list of
  branches with their coordinates, which `assets/js/branches-map.js` turns into one
  map with a pin per branch. Each pin links to its branch section by id
  (`#banino`, …), and its popup copies that section's address and contacts, so staff
  edit them in one place. The map uses Leaflet (bundled in `assets/vendor/leaflet/`,
  loaded only on pages with the map) and OpenStreetMap tiles.
- **Catalogs**: every branch has its own catalog on szukamksiążki.pl. Each branch
  section in *Filie* has a "Katalog online" link to it, and the /katalog-online/
  page (in the main menu) lists all of them. Both are content, edited in the admin.
- **Search** in the header: a slim field on wide screens, a magnifier that opens
  the field over the bar on narrower ones (core's search block, no plugin).
- **News list view**: /aktualnosci/ and search results have a grid / list switch
  (pattern `news-view-switch`, for any query block with the `bdc-news` class). The choice is kept in
  the visitor's browser (localStorage) and applied in `<head>` before the page
  paints (`functions.php`, `assets/js/news-view.js`), so cached pages work.
- **Theme version**: bump `Version` in `style.css` when adding a pattern file.
  WordPress caches the list of theme patterns per version, so a new pattern
  renders empty until the version changes. The version also busts the CSS/JS cache.
- **Page width**: pages and articles line up with the 1280px grid like the news
  list. Running text is capped at 768px for readability; columns, groups and
  galleries use the full width.
- **No plugins** are needed to run the site. The image ships two inactive tools:
  the importer and Duplicator (a full-site backup, `installer.php` plus archive,
  for moving the site to another host).

## Checks

```bash
# Blocks the editor would flag as "unexpected or invalid content"
docker compose run --rm browser validate-blocks.mjs /theme

# Automated WCAG 2.2 A/AA check (axe-core) at desktop and 320px widths
docker compose run --rm browser a11y.mjs home=http://localhost:8088/ news=http://localhost:8088/aktualnosci/

# Desktop and mobile screenshots, saved to .context/shots/<folder>/
docker compose run --rm browser screenshot.mjs /shots/<folder> home=http://localhost:8088/
```

## Production image

`Dockerfile` builds the image the cluster runs. Everything except uploads and the
database comes from it: core, the theme, Polish translations, WP-CLI, the
(inactive) importer, Duplicator and `smtp.php`. Setup scripts are in `/opt/setup`.
Every version and download is pinned; bump them in the Dockerfile together with
`compose.yml`.

GitHub Actions builds every push that touches `wordpress/` and pushes
`ghcr.io/bibliotekadostepnacyfrowo/wordpress:<git sha>`. To deploy, set `image.tag`
to that SHA in the german-lab repo (`gitops/apps/staging/biblioteka-staging.yaml`);
Argo CD rolls it out. Plugins and core can't be updated from the admin
(`DISALLOW_FILE_MODS`): updates mean rebuilding the image.

Run WP-CLI in the running pod. `wp` runs as `www-data` even from a root shell:

```bash
kubectl -n biblioteka-staging exec deploy/biblioteka-staging -- wp <command>
```

Runtime settings come from the environment: database and salts from a Secret,
`WP_HOME`, `DISABLE_WP_CRON` and the rest through `WORDPRESS_CONFIG_EXTRA`,
`NOINDEX=1` for the `X-Robots-Tag: noindex, nofollow` header on staging (the old
`BZ_NOINDEX=1` still works), and
`SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASSWORD`, `SMTP_FROM` for mail.
