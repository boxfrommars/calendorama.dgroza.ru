# Архитектура приложения

## Общая модель

Проект остаётся компактным PHP-приложением без framework kernel, роутера и
DI-контейнера. Nginx разрешает ровно два PHP entry point, поэтому отдельный
маршрутизатор здесь не добавил бы полезной абстракции. Все зависимости явно
собираются в `ApplicationFactory`.

```text
Nginx
  ├─ /, /index.php ──> IndexController ──> IndexViewFactory ──> Twig
  └─ /handler.php ───> CalendarController
                         ├─ CalendarParameters
                         ├─ CalendarMonth
                         ├─ CalendarViewFactory
                         │    ├─ CalendarArchive
                         │    └─ ClockInterface
                         └─ TemplateRenderer ──> Twig
```

Symfony HttpFoundation используется только как небольшой HTTP-слой, Symfony
Clock — как реализация PSR-20 времени, Twig — как view-слой. Доменные классы
календаря не зависят от HTTP или Twig.

## Каталоги и ответственность

| Путь | Назначение |
| --- | --- |
| `public/` | Единственный web-root: два PHP entry point, CSS, JS, RSS и изображения. |
| `src/Http/` | Разбор входных параметров и HTTP-контроллеры. |
| `src/Calendar/` | Календарные вычисления и доступ к manifest архива. |
| `src/View/` | Построение view-model, URL ассетов и конфигурация Twig. |
| `src/View/Model/` | Типизированные readonly-модели, доступные Twig. |
| `templates/` | Весь серверный HTML: страница и календарный фрагмент. |
| `content/` | Приватный manifest опубликованных дней. |
| `tests/` | PHPUnit, HTTP smoke и Playwright-сценарии. |
| `docker/` | Локальная Nginx-конфигурация. |
| `deploy/` | Шаблон production Nginx virtual host. |

Composer загружает namespace `Calendorama\` из `src/`. Ручного списка
`require_once`, собственного autoloader и runtime discovery сервисов нет.

## Композиция приложения

`ApplicationFactory::create()` — composition root. Он принимает корень проекта
и необязательный `ClockInterface`, затем один раз создаёт:

- `TemplateRenderer` для `templates/`;
- `CalendarArchive` для `content/archive.json`;
- `AssetUrlGenerator` для файлов внутри `public/`;
- `IndexViewFactory` и `CalendarViewFactory`;
- `IndexController` и `CalendarController`;
- фасад `Application` с методами `index()` и `calendar()`.

Production использует `NativeClock`. Тесты передают `MockClock`, поэтому
текущий день, текущий год, начальный маршрут и число дней в году проверяются
детерминированно. Системная timezone намеренно не переопределяется.

## Поток главной страницы

1. `public/index.php` загружает `vendor/autoload.php`.
2. `ApplicationFactory` собирает приложение.
3. `IndexController` получает `IndexView` от `IndexViewFactory`.
4. Factory читает текущую дату из общего clock, сводку архива и content hashes
   CSS/JS.
5. `TemplateRenderer` рендерит `templates/index.html.twig` с autoescape.
6. Entry point отправляет `Response` с HTML и UTF-8 content type.

`IndexView` уже содержит готовые ссылки списка месяцев, counts, итог архива,
начальный hash-route и versioned asset URLs. Twig не вычисляет бизнес-данные.

## Поток календарного фрагмента

1. `public/handler.php` создаёт HttpFoundation `Request` из PHP globals.
2. `CalendarParameters` разбирает только POST-параметры `month` и `year`.
3. `CalendarMonth` вычисляет дни, первый weekday и соседние месяцы.
4. `CalendarViewFactory` всегда строит шесть недель, то есть 42 ячейки,
   добавляет архивные записи и отмечает сегодняшний день через clock.
5. `TemplateRenderer` рендерит `calendar-fragment.html.twig`.
6. Контроллер возвращает HTML либо точную ошибку `Invalid calendar date.`.

`CalendarParameters` принимает PHP integers и строки только из цифр. Массивы,
отсутствующие значения, текст, month вне `1..12` и year вне `1..9999`
приводят к HTTP 400. Метод запроса отдельно не ограничен: GET не содержит
POST-параметров и поэтому тоже получает прежний 400, а не 405.

## View-слой

`CalendarViewFactory` и `IndexViewFactory` строят типизированные readonly
объекты. `TemplateRenderer` занимается только Twig и включает:

- `strict_variables=true` — неполная view-model завершает рендер с ошибкой;
- HTML autoescape — текст manifest и атрибуты экранируются на границе HTML;
- `cache=false` — приложению не нужны writable-каталоги;
- `use_yield=true` — актуальный режим Twig 3.

В шаблонах нельзя использовать `raw`. PHP-классы не должны собирать HTML и не
должны передавать CSS-классы как часть доменной модели: классы оформления
назначаются в Twig.

## Ошибки и границы доверия

- Некорректный пользовательский ввод — ожидаемая ошибка HTTP 400.
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
