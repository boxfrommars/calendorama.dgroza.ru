# Calendorama

The legacy calendar site runs on PHP 8.3 without application dependencies.

## Local development

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
