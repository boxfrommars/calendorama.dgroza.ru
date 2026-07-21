# Calendorama

Calendorama is a small file-backed calendar running on PHP 8.3 with Twig views.
It has no framework, database, production frontend dependencies, or asset build
step. Playwright and Node are used only by the optional browser-test container.

## Project structure

- `public/` is the only web-accessible directory. It contains the two PHP
  entry points, static assets, RSS, and calendar content in `public/blog/`.
- `src/` contains the application composition root, HTTP controllers, request
  validation, calendar calculations, content access, typed view models, and
  Twig configuration.
- `templates/` contains all page and calendar markup as Twig templates.
- `tests/` contains the PHPUnit, HTTP smoke, and Playwright regression suites.
  PHPStan checks all project PHP at its strictest rule level.
- `docker/` and `deploy/` contain local and production Nginx configuration.

The public entry points create a Symfony HttpFoundation request where needed,
obtain the explicitly wired application from `ApplicationFactory`, and send a
response. `CalendarArchive` indexes canonical file-backed posts,
`IndexViewFactory` and `CalendarViewFactory` build readonly Twig models, and
`TemplateRenderer` only renders templates. There is no router,
dependency-injection container, or framework kernel. The current date comes
from one PSR-20 clock so server-rendered links and the initial client route
cannot disagree.

Calendar posts use `blog/{month}_{day}_thmb.jpg` and
`blog/{month}_{day}_full.jpg`. The optional title for a day is read from the
line beginning with that day number in `blog/{month}.txt`.
Additional image variants such as `3_28_thmb_1.jpg` are retained as files but
are not counted as separate calendar posts.

## Local development

Composer dependencies are locked in `composer.lock` and installed while the
PHP image is built. Validate and audit them without installing Composer on the
host:

```shell
docker run --rm --volume "$PWD:/app" --workdir /app composer:2 validate --strict
docker run --rm --volume "$PWD:/app" --workdir /app composer:2 audit --locked
```

Build the test images and run the PHP checks:

```shell
docker compose --profile test build
docker compose --profile test run --rm tests
```

The test service runs the Composer `check` script: PHPUnit followed by PHPStan.
Run either check separately with `composer test` or `composer analyse` inside
the test container. Its Docker target contains development dependencies; the
PHP-FPM runtime target is built with `--no-dev`.

Run the Nginx HTTP matrix and the browser scenarios locally:

```shell
docker compose --profile test run --rm smoke
docker compose --profile test run --rm e2e
```

The `web` healthcheck starts PHP-FPM and Nginx before the smoke or browser
container runs. Playwright covers navigation, dialogs, focus, request races,
reduced motion, the image viewer, tooltips, and fixed 908 px visual snapshots.
To intentionally refresh those snapshots after reviewing a visual change:

```shell
docker compose --profile test run --rm e2e npx playwright test --update-snapshots
```

There is no external CI configuration; these checks are deliberately run in
local Docker.

Start the local PHP-FPM and Nginx services:

```shell
docker compose up -d
```

The site is available at <http://127.0.0.1:8010>. Set `CALENDORAMA_PORT`
before starting Compose to use another loopback port. Stop it with:

```shell
docker compose down
```

## Production deployment

Production runs from `/var/www/calendorama` with Nginx serving only
`/var/www/calendorama/public`. Deploy a published commit, run `composer install
--no-dev --prefer-dist --no-interaction --optimize-autoloader`, verify PHP
syntax and tests, and run `sudo nginx -t` before reloading Nginx. The virtual
host preserves the existing Let's Encrypt certificate and redirects HTTP to
HTTPS. Twig uses no filesystem cache, so no writable application directory is
required. CSS and JavaScript URLs contain a content hash. Nginx gives immutable
cache headers only to versioned assets and archive images, while HTML and RSS
do not receive immutable caching. A single URI/version map selects the cache
policy, and security headers are declared once at the server level. The
checked-in virtual-host template also contains the site Content Security
Policy; changing it still requires `nginx -t` before a reload.
