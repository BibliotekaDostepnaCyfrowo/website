# Biblioteka Dostępna Cyfrowo

A WordPress block theme for Polish public libraries, built to be accessible
(WCAG 2.2 AA) and easy for library staff to keep up to date. It is the theme
used by libraries in the [Biblioteka Dostępna Cyfrowo](https://dostepnacyfrowo.pl)
programme, and it is free for any library to use.

What a library gets:

- Home page, news (grid or list), articles, pages, search and 404 templates
- Branches with addresses, contacts, an online catalogue link and one shared map
- Opening hours kept in one table, with today's hours shown on the home page and
  in every branch, worked out on the server (no JavaScript)
- Content staff edit in one place (hours, branches, partners, footer) under
  **Stałe treści** in the admin, not in theme files
- No plugins needed, self-hosted fonts (no requests to Google), Polish interface

## What's in this repo

Everything lives in [`wordpress/`](wordpress/):

- [`theme/biblioteka-dostepna-cyfrowo/`](wordpress/theme/biblioteka-dostepna-cyfrowo/): the block theme
- [`setup/`](wordpress/setup/): a setup script that prepares a site for the theme,
  either with a fictional example library or with a real library's details
  (`library.json`), plus a cleanup script for imported content
- `compose.yml`: the local Docker environment
- `Dockerfile`, `docker/`: the production image, built by GitHub Actions to
  `ghcr.io/bibliotekadostepnacyfrowo/wordpress`
- `tools/`: block validation, accessibility check and screenshots

Start with [`wordpress/README.md`](wordpress/README.md): it covers running the site
locally, moving an existing library's site onto the theme, how the theme works,
the checks and the production image.

## Using the theme on your own WordPress

Requires WordPress 6.6+ and PHP 8.1+. Copy
`wordpress/theme/biblioteka-dostepna-cyfrowo/` into `wp-content/themes/`, activate
it, then run the setup script once with WP-CLI:

```bash
wp eval-file wordpress/setup/setup.php
```

The setup creates the pages and the editable blocks (opening hours, branches,
partners, footer) that the templates show; without it those sections stay empty.
It is safe to re-run and never overwrites what staff have edited. See
[Copy of an existing site](wordpress/README.md#copy-of-an-existing-site) for
filling it with your library's own name, branches and hours.

## History

The project started as the website of the Gminna Biblioteka Publiczna w Żukowie
and was then generalised into a theme for any library. The earlier Next.js +
Contentful frontend has been retired; it is in git history up to commit `36d0036`.

## Credits

- Theme: [Feature Forest sp. z o.o.](https://featureforest.eu)
- Design: [Radosław Śmigielski](https://www.linkedin.com/in/rados%C5%82aw-%C5%9Bmigielski-895a73302/)
