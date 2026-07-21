# Calendorama

Calendorama is a small file-backed calendar running on PHP 8.3 with Twig views.
It has no framework, database, frontend dependencies, or asset build step.

## Project structure

- `public/` is the only web-accessible directory. It contains the two PHP
  entry points, static assets, RSS, and calendar content in `public/blog/`.
- `src/` contains request validation, calendar calculations, content access,
  view-model construction, and Twig configuration.
- `templates/` contains all page and calendar markup as Twig templates.
- `tests/` contains the dependency-free regression suite.
- `docker/` and `deploy/` contain local and production Nginx configuration.

Calendar posts use `blog/{month}_{day}_thmb.jpg` and
`blog/{month}_{day}_full.jpg`. The optional title for a day is read from the
line beginning with that day number in `blog/{month}.txt`.

## Local development

Composer dependencies are locked in `composer.lock` and installed while the
PHP image is built. Validate and audit them without installing Composer on the
host:

```shell
docker run --rm --volume "$PWD:/app" --workdir /app composer:2 validate --strict
docker run --rm --volume "$PWD:/app" --workdir /app composer:2 audit --locked --no-dev
```

Build the image and run the test suite:

```shell
docker compose --profile test build
docker compose --profile test run --rm tests
```

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
required.
