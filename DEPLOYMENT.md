# Deployment requirements: Calendorama

Этот документ описывает требования приложения и проверяемый результат. Он не
описывает конкретный хост, абсолютные пути, пользователей ОС, права файлов,
процесс передачи релиза или инструменты, которыми эти требования реализуются.

Не добавлять сюда значения секретов, содержимое production-конфигурации,
приватные ключи, сертификаты, базы данных или резервные копии.

## Приложение

| Параметр | Значение |
| --- | --- |
| Название | Calendorama |
| Репозиторий | <https://github.com/boxfrommars/calendorama.dgroza.ru> |
| Production-ветка | `master` |
| Канонический URL | <https://calendorama.dgroza.ru/> |
| Тип процесса | HTTP request |
| Runtime | PHP 8.3 |
| База данных | отсутствует |

## Runtime-интерфейс

- Package manager: Composer
- Установка production-зависимостей:
  `composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader`
- Проверка platform requirements: `composer check-platform-reqs --no-dev`
- Относительный публичный каталог: `public/`
- Entry point: `public/index.php`, только как внутренний front controller
- Frontend-сборка: отсутствует
- Требуемые системные возможности: PHP 8.3 и `ext-iconv` в соответствии с
  `composer.lock`; дополнительных расширений для приложения не требуется
- Ограничения процесса: request-scoped и stateless; фоновых и долгоживущих
  процессов нет; файлы приложения во время HTTP-запросов доступны только для
  чтения

Node, npm и Playwright используются только browser-тестами и не являются
production-зависимостями. Все пути в этом документе задаются относительно
корня приложения. Способ запуска PHP и интеграцию с HTTP-сервером выбирает
окружение размещения.

## Конфигурация

Production-переменные окружения отсутствуют. `CALENDORAMA_PORT`,
`CALENDORAMA_BASE_URL` и `PLAYWRIGHT_BASE_URL` относятся только к локальному и
тестовому Docker-окружению и не являются конфигурацией runtime.

## Состояние и запись

- Writable-пути runtime относительно корня: отсутствуют
- Генерируемые артефакты: tracked-файл `public/rss.xml`, детерминированно
  создаваемый командой `composer rss` до выпуска
- Постоянные данные: отсутствуют вне release; `content/archive.json` и
  `public/blog/` являются tracked-контентом release
- Пользовательские загрузки: отсутствуют
- Требование к согласованной backup-копии: отсутствует
- Проверка целостности и восстановления: `composer rss:check`, PHPUnit-проверки
  manifest, RSS и соответствия canonical image pairs

Генерация RSS не является runtime-записью или обязательным deployment hook.
Готовый `public/rss.xml` входит в release и раздаётся как статический файл.

## Lifecycle hooks

Отсутствуют. Установка Composer-зависимостей является частью подготовки
runtime, а актуальность tracked RSS подтверждается до выпуска.

## Изменения данных

- Миграции: отсутствуют
- Предусловия: отсутствуют
- Обратимость: not applicable
- Ожидаемая недоступность: отсутствует как требование приложения
- Совместимость старой и новой версии с данными: not applicable

## Внешнее поведение

- Канонический URL: <https://calendorama.dgroza.ru/>
- Дополнительные имена: отсутствуют
- Redirects: HTTP-запросы к каноническому имени перенаправляются на HTTPS с
  кодом 301 и сохранением path и query
- Health endpoint: `GET /`, ожидается HTTP 200 и HTML-страница
- Поведение неизвестного URL: HTTP 404, `text/plain`, тело `Not Found.`

`deploy/nginx.conf.example` — переносимый, не готовый к установке пример только
для маршрутизации, кеширования и security headers приложения. Listen address,
server names, публичный root, FastCGI upstream, TLS, redirects, logging и
управление сервисами задаёт окружение размещения.

Ключевые smoke-сценарии:

| Метод и путь | Ожидаемый результат |
| --- | --- |
| `GET /` | 200, HTML, `Cache-Control: no-cache`, security headers |
| `POST /` | 405, `Allow: GET`, тело `Method Not Allowed.`, `no-store` |
| `GET /calendar?month=3&year=2010` | 200, HTML-фрагмент календаря, `no-store` |
| `GET /calendar` или недопустимая дата | 400, тело `Invalid calendar date.`, `no-store` |
| `POST /calendar` | 405, `Allow: GET`, тело `Method Not Allowed.`, `no-store` |
| `GET /rss.xml` | 200, статический RSS, `Cache-Control: no-cache` |
| `GET` существующего versioned CSS/JS или архивного изображения | 200, immutable caching |
| `GET` прямого PHP URL или закрытого служебного пути | 404 |

Security headers должны сохранять same-origin CSP, запрет MIME sniffing и
embedding, а также `strict-origin-when-cross-origin` referrer policy. Только
`public/` доступен через HTTP; прямой доступ к `public/index.php`, исходникам,
шаблонам, manifest, тестам и зависимостям запрещён.

## Проверка релиза

Команды подтверждают готовность кода независимо от способа размещения:

```shell
docker compose --profile test build
docker compose --profile test run --rm tests
docker compose --profile test run --rm smoke
docker compose --profile test run --rm e2e
docker compose --profile test run --rm tests composer validate --strict
docker compose --profile test run --rm tests composer audit --locked --no-dev
```

Сервис `tests` запускает `composer check`: сначала `composer rss:check`, затем
PHPUnit с `E_ALL` и PHPStan level max. При намеренном визуальном изменении нужно
просмотреть отличия Playwright screenshots до обновления baselines.

## Совместимость rollback

- Можно ли вернуть предыдущую версию кода без возврата данных: да
- Application-команды после возврата кода:
  `composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader`,
  затем `composer check-platform-reqs --no-dev` и `composer rss:check`
- Требуется восстановление данных: нет
- Несовместимые изменения: отсутствуют
- Проверки после возврата: ключевые HTTP smoke-сценарии из раздела «Внешнее
  поведение»

Здесь описаны только требования приложения. Выбор версии, переключение
checkout и другие действия окружения размещения в этот документ не входят.

## Когда обновлять этот документ

Обновить `DEPLOYMENT.md` в той же задаче, если меняются runtime,
production-зависимости, переменные окружения, публичный интерфейс,
writable-потребности, постоянные данные, lifecycle hooks, миграции, внешнее
поведение или совместимость rollback.

Обычное изменение контента или исправление кода документа не затрагивает, если
требования приложения остаются прежними.
