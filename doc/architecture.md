# Архитектура приложения

## Общая модель

Проект остаётся компактным PHP-приложением без framework kernel, роутера и
DI-контейнера. Один внутренний front controller и точный `match` двух маршрутов
в `Application` не требуют отдельного пакета маршрутизации. Все зависимости
явно собираются в `ApplicationFactory`.

```text
Nginx
  ├─ существующий статический файл ──> напрямую
  └─ / или /calendar ──> internal public/index.php
                              ↓
                         Application::handle(Request)
                              ├─ / ─────────> index()
                              └─ /calendar ─> calendar(Request)
                              ├─ CalendarParameters
                              └─ CalendarMonth
                                     ↓
                              ViewFactory ──> CalendarArchive
                                     ↓
                              TemplateRenderer ──> Twig

content/archive.json ──> RssGenerator ──> Twig ──> public/rss.xml
```

Symfony HttpFoundation используется только как небольшой HTTP-слой, Twig — как
view-слой. Доменные классы календаря не зависят от HTTP или Twig.

## Каталоги и ответственность

| Путь | Назначение |
| --- | --- |
| `public/` | Единственный web-root: внутренний PHP front controller, CSS, JS, RSS и изображения. |
| `src/Http/` | Строгий разбор входных параметров. |
| `src/Calendar/` | Календарные вычисления и доступ к manifest архива. |
| `src/Rss/` | Детерминированная сборка RSS из manifest. |
| `src/View/` | Единая фабрика данных представления, URL ассетов и конфигурация Twig. |
| `templates/` | Вся генерируемая разметка: HTML страницы, календарный фрагмент и RSS XML. |
| `content/` | Приватный manifest опубликованных дней, заголовков и исходных дат. |
| `bin/` | Локальные CLI-команды для воспроизводимых generated-файлов. |
| `tests/` | PHPUnit, HTTP smoke и Playwright-сценарии. |
| `docker/` | Локальная Nginx-конфигурация. |
| `deploy/` | Шаблон production Nginx virtual host. |

Composer загружает namespace `Calendorama\` из `src/`. Ручного списка
`require_once`, собственного autoloader и runtime discovery сервисов нет.

## Композиция приложения и время

`ApplicationFactory::create()` — composition root. Он принимает корень проекта
и необязательный снимок времени `DateTimeImmutable`, затем один раз создаёт:

- `TemplateRenderer` для `templates/`;
- `CalendarArchive` для `content/archive.json`;
- `AssetUrlGenerator` для файлов внутри `public/`;
- единый `ViewFactory` для страницы и календаря;
- `Application` как единственную HTTP-границу.

Production создаёт один снимок системного времени на запрос. Тесты передают
фиксированный `DateTimeImmutable`, поэтому сегодняшний день, начальный маршрут
и число дней в году проверяются детерминированно. Системная timezone для этого
снимка не переопределяется. Календарные вычисления используют стандартный
`DateTimeImmutable` в UTC и не зависят от расширения `ext-calendar`.

## Поток главной страницы

1. Nginx внутренне передаёт `/` в `public/index.php`.
2. Front controller загружает Composer, создаёт `Request` из PHP globals и
   передаёт его в `Application::handle()`.
3. `Application` выбирает маршрут `/`, а `ApplicationFactory` собирает
   зависимости и фиксирует текущее время.
4. `index()` получает данные представления от `ViewFactory::index()`.
5. Factory читает counts архива и content hashes CSS/JS.
6. `TemplateRenderer` рендерит `templates/index.html.twig` с autoescape.
7. Front controller отправляет `Response` с HTML и UTF-8 content type.

Массив представления уже содержит готовые маршруты списка месяцев, counts, итог архива,
начальный hash-route и versioned asset URLs. Twig не вычисляет бизнес-данные.

## Поток календарного фрагмента

1. Nginx передаёт `/calendar` тому же внутреннему `public/index.php`.
2. `Application::handle()` выбирает календарный маршрут; `calendar()` разрешает
   только GET и передаёт query-параметры
   `month` и `year` в `CalendarParameters`.
3. `CalendarMonth` через `DateTimeImmutable` вычисляет дни, первый weekday,
   название и nullable-соседей в пределах годов `1..9999`.
4. `ViewFactory::calendar()` всегда строит шесть недель, то есть 42 ячейки,
   добавляет заголовки из архива, формирует URL изображений по соглашению об
   именах и сравнивает даты с общим снимком времени.
5. `TemplateRenderer` рендерит `calendar-fragment.html.twig`.
6. `Application` возвращает HTML, точную ошибку `Invalid calendar date.` или
   `Method Not Allowed.` и во всех случаях запрещает кешировать fragment.

`CalendarParameters` принимает PHP integers и строки только из цифр. Массивы,
отсутствующие значения, текст, month вне `1..12` и year вне `1..9999`
приводят к HTTP 400. POST и другие методы получают HTTP 405 и `Allow: GET`.

## Поток RSS

RSS не генерируется во время HTTP-запроса. `bin/generate-rss.php` загружает
`CalendarArchive`, передаёт публикации в `RssGenerator` и рендерит
`templates/rss.xml.twig`. Полученный `public/rss.xml` коммитится и раздаётся
Nginx как обычный статический файл.

Генератор сортирует записи по исходному `publishedAt`, вычисляет публичные
ссылки и сохраняет legacy HTTP GUID. `composer rss:check` повторяет рендер в
памяти и завершается ошибкой, если tracked XML устарел.

## View-слой

`ViewFactory` строит для обоих шаблонов массивы фиксированной формы. Их контракты описаны
локальными PHPStan array-shape типами рядом с кодом построения: это сохраняет статическую
проверку полей без отдельных одноразовых DTO-классов.
`TemplateRenderer` занимается только Twig и включает:

- `strict_variables=true` — неполная view-model завершает рендер с ошибкой;
- autoescape — текст manifest и атрибуты экранируются на границе HTML/XML;
- `cache=false` — приложению не нужны writable-каталоги;
- `use_yield=true` — актуальный режим Twig 3.

В шаблонах нельзя использовать `raw`. PHP-классы не должны собирать HTML и не
должны передавать CSS-классы как часть доменной модели: классы оформления
назначаются в Twig.

## Ошибки и границы доверия

- Некорректный пользовательский ввод — ожидаемая ошибка HTTP 400.
- Неподдерживаемый HTTP-метод календаря — ожидаемая ошибка HTTP 405.
- Неизвестный маршрут — текстовый HTTP 404.
- Отсутствующий, повреждённый или логически неверный manifest — ошибка
  конфигурации/данных. Она не маскируется fallback-заголовком и должна привести
  к HTTP 500 с записью контекста в PHP-FPM log.
- Отсутствующий CSS/JS при вычислении content hash — ошибка выпуска, а не повод
  выдавать URL без версии.
- Отсутствующее full-size изображение обрабатывается браузером как ошибка
  просмотра, но целостность canonical image pairs должна быть поймана тестами
  до выпуска.

## Осознанные ограничения

Проект не использует базу данных, кеш приложения, логгер, Validator component,
PSR-7 bridge, frontend bundler или Node в production. Добавлять новый слой или
пакет стоит только при появлении отдельной ответственности, которую нельзя
ясно выразить существующими небольшими классами.
