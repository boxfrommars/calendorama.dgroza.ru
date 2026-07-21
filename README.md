# Calendorama

Calendorama is a small read-only calendar archive built with PHP 8.3, Twig and
vanilla JavaScript. It has no database or frontend build step.

## Documentation

Start with the [project knowledge base](doc/README.md). It links to the detailed
documentation for:

- [application architecture](doc/architecture.md);
- [frontend behavior and DOM contracts](doc/frontend.md);
- [content and public contracts](doc/content-and-public-contracts.md);
- [testing and operations](doc/testing-and-operations.md).

## Local development

Docker is the only local prerequisite. Start the application with:

```shell
docker compose up -d
```

The site is available at <http://127.0.0.1:8010>. Use
`CALENDORAMA_PORT` to select another loopback port, and stop the environment
with:

```shell
docker compose down
```

## Checks

Build the test images and run the PHP, HTTP and browser suites:

```shell
docker compose --profile test build
docker compose --profile test run --rm tests
docker compose --profile test run --rm smoke
docker compose --profile test run --rm e2e
```

See [testing and operations](doc/testing-and-operations.md) for dependency
validation, auditing, screenshot updates and release checks.
