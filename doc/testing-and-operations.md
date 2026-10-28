# Тестирование и эксплуатация

## Принцип воспроизводимости

Все обязательные проверки выполняются локально в Docker. Хосту не нужны PHP,
Composer, Node или браузеры нужных версий. Внешнего CI в проекте нет, поэтому
полная локальная матрица — обязательный gate перед выпуском.

## Docker targets и сервисы

| Target/service | Содержимое и назначение |
| --- | --- |
| `vendor-prod` | Composer runtime dependencies с `--no-dev`. |
| `vendor-dev` | Runtime и dev dependencies для PHP-проверок. |
| `runtime` / `php` | PHP 8.3 FPM и production vendor; без дополнительных PHP extensions и Node. |
| `test` / `tests` | PHPUnit, PHPStan, Composer и исходники тестов. |
| `rss` | Явно обновляет tracked `public/rss.xml` через writable bind mount. |
| `web` | Локальный Nginx перед PHP-FPM, с production-подобными ограничениями. |
| `smoke` | HTTP-матрица против здорового `web`. |
| `browser-test` / `e2e` | Зафиксированный Playwright с Chromium, Firefox и WebKit; только для тестов. |

Исходники монтируются read-only. Отдельный anonymous volume `/var/www/html/vendor`
не даёт host mount скрыть зависимости, собранные в PHP-образе. Healthcheck
Nginx блокирует smoke/e2e до готовности сайта.

## Полная локальная матрица

```shell
docker compose --profile test build
docker compose --profile test run --rm tests
docker compose --profile test run --rm smoke
docker compose --profile test run --rm e2e
docker compose --profile test run --rm tests composer validate --strict
docker compose --profile test run --rm tests composer check-platform-reqs
docker compose --profile test run --rm tests composer audit --locked
docker compose --profile test run --rm e2e npm audit
```

`composer check` внутри `tests` сначала проверяет актуальность RSS, затем
запускает PHPUnit с `E_ALL` и PHPStan level max. PHPUnit настроен завершаться
ошибкой при warning, notice, deprecation, risky test и других проблемах,
которые легко пропустить вручную.

Для локального просмотра:

```shell
docker compose up -d
```

По умолчанию сайт доступен на `http://127.0.0.1:8010`; порт меняется переменной
`CALENDORAMA_PORT`. После работы окружение останавливается командой
`docker compose down`.

## Обновление зависимостей

Composer lock-файл обновляется на PHP 8.3, чтобы новые версии runtime- и
dev-зависимостей оставались совместимы с окружением приложения. Аудит
`composer audit --locked` проверяет оба набора зависимостей; `npm audit`
проверяет зависимости browser-тестов.

Версия `@playwright/test` в `package.json` и `package-lock.json` должна точно
совпадать с версией образа `mcr.microsoft.com/playwright` в `Dockerfile`.
Пакет и образ обновляются вместе: образ содержит соответствующие браузеры и
их системные зависимости.

После изменения lock-файлов нужно пересобрать образы и пересоздать локальные
сервисы с новым anonymous volume для `vendor`, затем выполнить полную матрицу:

```shell
docker compose --profile test build --pull
docker compose up -d --force-recreate --renew-anon-volumes php web
```

Генерировать RSS заново при обновлении пакетов не требуется: `composer check`
проверяет актуальность tracked XML. Изменения screenshot baselines после
обновления браузеров требуют просмотра отличий по правилам ниже.

## Что защищают тесты

| Набор | Основные гарантии |
| --- | --- |
| PHPUnit domain/content | Параметры, календарная математика, manifest, publication dates, counts, image pairs, titles. |
| PHPUnit view/Application | Фиксированный снимок времени, array-shape данные представления, Twig strict/autoescape, DOM и точные HTTP ошибки. |
| RSS test | Детерминированная генерация, валидность, порядок, HTTPS-ссылки и стабильность legacy GUID. |
| PHPStan | Типы всего PHP-кода на level max без baseline. |
| HTTP smoke | 200/400/405/404, GET-only маршруты, единый front controller, CSP, cache headers, закрытые служебные пути. |
| Playwright | Hash/history, native popover/focus, tooltip, viewer, races, errors, reduced motion в трёх движках. |
| Screenshots | Геометрия календаря, about panel и viewer при viewport 908 px. |

Функциональные browser-сценарии выполняются в Chromium, Firefox и WebKit;
визуальные baselines намеренно проверяются только в Chromium. Screenshot
baselines находятся рядом с e2e-тестом. Обновлять их командой
`npx playwright test --update-snapshots` можно только после просмотра отличий и
подтверждения, что визуальное изменение намеренно. Прохождение теста после
слепого обновления baseline не является проверкой дизайна.

## Nginx и безопасность

`docker/nginx.conf` воспроизводит web-контракт в локальном окружении, а
`deploy/nginx.conf.example` служит переносимым примером его реализации. Пример
не готов к установке: окружение размещения должно подставить listen address,
server names, публичный root и FastCGI upstream. Обе конфигурации:

- используют `public/` как document root;
- выполняют единственный `public/index.php` только как внутренний front controller;
- возвращают 404 для всех прямых PHP URL и скрытых файлов;
- разрешают статические файлы только если они существуют;
- передают остальные пути front controller, который маршрутизирует только `/`
  и `/calendar`;
- отправляют same-origin CSP, `nosniff`, `DENY` для embedding и безопасную
  referrer policy.

Пример намеренно не задаёт TLS termination, сертификаты, HTTP-to-HTTPS
redirect, logging, управление сервисами и активацию конфигурации. Эти решения
принадлежат окружению размещения, а проверяемое внешнее поведение задаёт
[`DEPLOYMENT.md`](../DEPLOYMENT.md).

CSP соответствует текущей архитектуре: CSS, JS, изображения и fetch нужны
только с собственного origin; inline script/style и внешние CDN не нужны.
Если появится легитимный внешний ресурс, политику следует расширить минимально,
а не отключать целиком.

## Кеширование ассетов

`AssetUrlGenerator` добавляет к CSS и JS первые 12 hex-символов SHA-256 их
содержимого: `/style.css?v=...` и `/script.js?v=...`. Nginx выдаёт таким URL
`public, max-age=31536000, immutable`. Незаверсионированные CSS/JS получают
`no-cache`, чтобы старый URL всегда revalidate.

Главная HTML-страница и RSS получают `no-cache`: клиент может хранить ответ, но
должен revalidate его; RSS использует ETag/Last-Modified. Календарный fragment и
ошибки метода получают `no-store`. Favicon, фоновые изображения и архивные JPG
также immutable, поскольку их имена считаются стабильными для архивного
контента. Изменение содержимого immutable-файла без нового URL может оставлять
старую версию в кеше; для CSS/JS это решает content hash, а архивное изображение
при замене требует отдельного решения о versioning.

## Граница ответственности

Этот репозиторий определяет локальные проверки и декларативные требования
приложения к окружению размещения. Требования к runtime, состоянию, внешнему
поведению, проверке релиза и совместимости rollback собраны в
[`DEPLOYMENT.md`](../DEPLOYMENT.md).

Операционное окружение отвечает за inventory целевой инфраструктуры, доставку
и активацию релиза, фактический rollback и инфраструктурные проверки.
Конкретные серверы, пути, пользователи ОС, версии сервисов, deployed commits и
команды runbook в проектной документации не фиксируются.
