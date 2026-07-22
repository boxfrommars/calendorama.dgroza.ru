# Архитектура приложения

## Общая модель

Проект остаётся компактным PHP-приложением без framework kernel, роутера и
DI-контейнера. Nginx разрешает ровно два PHP entry point, поэтому отдельный
маршрутизатор здесь не добавил бы полезной абстракции. Все зависимости явно
собираются в `ApplicationFactory`.

```text
Nginx
  ├─ /, /index.php ──> Application::index()
  └─ /handler.php ───> Application::calendar(Request)
                              ├─ CalendarParameters
                              └─ CalendarMonth
                                     ↓
                              ViewFactory ──> CalendarArchive
                                     ↓
                              TemplateRenderer ──> Twig
```

Symfony HttpFoundation используется только как небольшой HTTP-слой, Twig — как
view-слой. Доменные классы календаря не зависят от HTTP или Twig.

## Каталоги и ответственность

| Путь | Назначение |
| --- | --- |
| `public/` | Единственный web-root: два PHP entry point, CSS, JS, RSS и изображения. |
| `src/Http/` | Строгий разбор входных параметров. |
| `src/Calendar/` | Календарные вычисления и доступ к manifest архива. |
| `src/View/` | Единая фабрика данных представления, URL ассетов и конфигурация Twig. |
| `templates/` | Весь серверный HTML: страница и календарный фрагмент. |
| `content/` | Приватный manifest опубликованных дней. |
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

1. `public/index.php` загружает `vendor/autoload.php`.
2. `ApplicationFactory` собирает приложение и фиксирует текущее время.
3. `Application::index()` получает данные представления от `ViewFactory::index()`.
4. Factory читает counts архива и content hashes CSS/JS.
5. `TemplateRenderer` рендерит `templates/index.html.twig` с autoescape.
6. Entry point отправляет `Response` с HTML и UTF-8 content type.

Массив представления уже содержит готовые маршруты списка месяцев, counts, итог архива,
начальный hash-route и versioned asset URLs. Twig не вычисляет бизнес-данные.

## Поток календарного фрагмента

1. `public/handler.php` создаёт HttpFoundation `Request` из PHP globals.
2. `Application::calendar()` разрешает только GET и передаёт query-параметры
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

## View-слой

`ViewFactory` строит для обоих шаблонов массивы фиксированной формы. Их контракты описаны
локальными PHPStan array-shape типами рядом с кодом построения: это сохраняет статическую
проверку полей без отдельных одноразовых DTO-классов.
`TemplateRenderer` занимается только Twig и включает:

- `strict_variables=true` — неполная view-model завершает рендер с ошибкой;
- HTML autoescape — текст manifest и атрибуты экранируются на границе HTML;
- `cache=false` — приложению не нужны writable-каталоги;
- `use_yield=true` — актуальный режим Twig 3.

В шаблонах нельзя использовать `raw`. PHP-классы не должны собирать HTML и не
должны передавать CSS-классы как часть доменной модели: классы оформления
назначаются в Twig.

## Ошибки и границы доверия

- Некорректный пользовательский ввод — ожидаемая ошибка HTTP 400.
- Неподдерживаемый HTTP-метод календаря — ожидаемая ошибка HTTP 405.
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
