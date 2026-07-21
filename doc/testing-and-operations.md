# Тестирование и эксплуатация

## Принцип воспроизводимости

Все обязательные проверки выполняются локально в Docker. Хосту не нужны PHP,
Composer, Node или браузеры нужных версий. Внешнего CI в проекте нет, поэтому
полная локальная матрица — обязательный gate перед выпуском.

Production не изменяется автоматически после commit, push или merge. Любое
развёртывание возможно только после отдельной явной просьбы пользователя.
Изменяемые сведения о конкретном сервере, сертификате и deployed commit не
считаются частью базы знаний: их нужно заново проверять перед каждой
production-операцией.

## Docker targets и сервисы

| Target/service | Содержимое и назначение |
| --- | --- |
| `vendor-prod` | Composer runtime dependencies с `--no-dev`. |
| `vendor-dev` | Runtime и dev dependencies для PHP-проверок. |
| `runtime` / `php` | PHP 8.3 FPM и production vendor; без дополнительных PHP extensions и Node. |
| `test` / `tests` | PHPUnit, PHPStan, Composer и исходники тестов. |
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
docker compose --profile test run --rm tests composer audit --locked --no-dev
```

`composer check` внутри `tests` запускает PHPUnit с `E_ALL`, затем PHPStan level
max. PHPUnit настроен завершаться ошибкой при warning, notice, deprecation,
risky test и других проблемах, которые легко пропустить вручную.

Для локального просмотра:

```shell
docker compose up -d
```

По умолчанию сайт доступен на `http://127.0.0.1:8010`; порт меняется переменной
`CALENDORAMA_PORT`. После работы окружение останавливается командой
`docker compose down`.

## Что защищают тесты

| Набор | Основные гарантии |
| --- | --- |
| PHPUnit domain/content | Параметры, календарная математика, manifest, counts, image pairs, titles. |
| PHPUnit view/Application | Фиксированный снимок времени, readonly view-model, Twig strict/autoescape, DOM и точные HTTP ошибки. |
| RSS test | Валидность, HTTPS-ссылки и стабильность legacy GUID. |
| PHPStan | Типы всего PHP-кода на level max без baseline. |
| HTTP smoke | 200/400/404, CSP, cache headers, закрытые служебные пути. |
| Playwright | Hash/history, native popover/focus, tooltip, viewer, races, errors, reduced motion в трёх движках. |
| Screenshots | Геометрия календаря, about panel и viewer при viewport 908 px. |

Функциональные browser-сценарии выполняются в Chromium, Firefox и WebKit;
визуальные baselines намеренно проверяются только в Chromium. Screenshot
baselines находятся рядом с e2e-тестом. Обновлять их командой
`npx playwright test --update-snapshots` можно только после просмотра отличий и
подтверждения, что визуальное изменение намеренно. Прохождение теста после
слепого обновления baseline не является проверкой дизайна.

## Nginx и безопасность

`docker/nginx.conf` воспроизводит ключевые production-ограничения, а
`deploy/calendorama.dgroza.ru.conf` является шаблоном production virtual host.
Обе конфигурации:

- используют `public/` как document root;
- выполняют только `/index.php` и `/handler.php`;
- возвращают 404 для других PHP и скрытых файлов;
- разрешают статические файлы только если они существуют;
- отправляют same-origin CSP, `nosniff`, `DENY` для embedding и безопасную
  referrer policy.

CSP соответствует текущей архитектуре: CSS, JS, изображения и fetch нужны
только с собственного origin; inline script/style и внешние CDN не нужны.
Если появится легитимный внешний ресурс, политику следует расширить минимально,
а не отключать целиком.

## Кеширование ассетов

`AssetUrlGenerator` добавляет к CSS и JS первые 12 hex-символов SHA-256 их
содержимого: `/style.css?v=...` и `/script.js?v=...`. Nginx выдаёт таким URL
`public, max-age=31536000, immutable`. Незаверсионированные CSS/JS получают
`no-cache`, чтобы старый URL всегда revalidate.

Favicon, фоновые изображения и архивные JPG также immutable, поскольку их
имена считаются стабильными для архивного контента. HTML, handler и RSS не
получают immutable caching. Изменение содержимого immutable-файла без нового
URL может оставлять старую версию в кеше; для CSS/JS это решает content hash,
а архивное изображение при замене требует отдельного решения о versioning.

## Перед выпуском

До любой production-команды должны быть известны:

- точный проверенный и опубликованный commit;
- результаты полной Docker-матрицы;
- характер изменений в Composer lock, manifest, public assets и Nginx;
- текущий production commit и способ rollback.

После явного разрешения на выпуск используются fast-forward checkout,
`composer install --no-dev`, platform check, при необходимости `nginx -t`,
reload PHP-FPM/Nginx и внешняя HTTPS/HTTP/log-проверка. Перед изменением нужно
зафиксировать фактические пути, версии, активную конфигурацию и rollback point
на целевом сервере. Нельзя переносить dev vendor, Node или незакоммиченный
worktree.
