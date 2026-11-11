# Architectural Decision Record — Cross-cutting

> Covers: общая архитектура backend (Laravel 12) и организация кода.
> Domain-specific решения живут в своих файлах (`auth-audit-decisions.md`,
> `frontend-audit-decisions.md`).
>
> Формат тот же, что в остальных ADR-файлах: записи неизменяемы после принятия,
> пересмотр = новый ADR со ссылкой `Supersedes`.
>
> Нумерация `ARCH-N` отдельная, чтобы не пересекаться с AUTH-N из
> `auth-audit-decisions.md`.

---

### ARCH-1: Modular monolith — папки по доменам + ServiceProvider на модуль

**Status:** Accepted

**Контекст:**
Реализован только модуль auth (и тот не закончен), но схема БД
(`schema_final.sql`) уже фиксирует будущие домены: Auth (`users`), Catalog
(`categories`, `products`, `product_variants`, `product_images`), Cart
(`carts`, `cart_items`), Orders (`orders`, `order_items`,
`order_status_history`), Payments (`payments`), Wishlist (`wishlists`).
Объём кода вырастет в разы с приходом Catalog, Cart, Checkout и Payments, поэтому
структуру выбираем сейчас, пока перенос стоит дёшево. Модель «плагин/модуль со
своими роутами и миграциями» знакома по CakePHP Plugins; в Laravel
ближайший нативный аналог — ServiceProvider с `loadRoutesFrom` /
`loadMigrationsFrom`.

**Выбрали:**

1. **Папки по доменам:** `app/Modules/<Name>/`, namespace `App\Modules\<Name>`
   (стандартный PSR-4 из `composer.json`, ничего настраивать не нужно).
2. **ServiceProvider на модуль:** `app/Modules/<Name>/Providers/<Name>ServiceProvider.php`,
   в `boot()` подключает роуты (`loadRoutesFrom`) и миграции
   (`loadMigrationsFrom`). Провайдеры регистрируются в `bootstrap/providers.php`.

Структура модуля:

```text
app/Modules/Auth/
├── Actions/
├── DTO/
├── Database/
│   └── Migrations/
├── Http/
│   ├── Controllers/
│   ├── Requests/
│   └── Resources/
├── Listeners/
├── Models/
├── Notifications/
├── Providers/
│   └── AuthServiceProvider.php
├── Routes/
│   └── api.php
└── Services/
```

Сквозная инфраструктура (например, `ApiException`, `ValidationErrorCodeMapper`,
базовые классы) живёт в `app/Shared/`, а не принадлежит ни одному модулю.

**Правила границ:**

- Eloquent-связи по FK из общей схемы допустимы (`Order::belongsTo(User::class)`).
- Бизнес-операции над чужим доменом выполняются только через публичный
  Action/Service этого модуля, через DTO или через событие. Не через
  внутренние классы соседа.
- Одна БД и одна схема. Отдельных баз или схем на модуль нет.

**Почему (Архитектурные преимущества):**

- **Framework-native:** `loadRoutesFrom` и `loadMigrationsFrom` — штатные методы
  ServiceProvider, никаких сторонних пакетов и самописной магии.
- **Самодостаточный блок:** роуты, миграции, код и события домена лежат рядом.
  Связь с FSD на фронте: деление по доменам с обеих сторон.
- **Дёшево сейчас:** переносить один модуль гораздо проще, чем пять.
- **Путь к выделению:** при необходимости модуль можно вынести в отдельный
  composer-пакет или сервис, потому что границы уже проведены.

**Trade-off (Компромиссы):**

- **Порядок миграций:** Laravel собирает пути миграций всех модулей и сортирует
  файлы по имени (timestamp). Миграции с FK должны иметь timestamp позже
  таблиц, на которые они ссылаются: `users` → `addresses` → `categories` →
  `products` → `product_variants` → `carts` → `orders` → `payments`.
  Циклические FK (`users` ↔ `addresses`) остаются отдельной миграцией
  после создания обеих таблиц, как в `schema_final.sql`.
- **Event discovery:** слушатели больше не в `app/Listeners`. Пути автообнаружения
  в `bootstrap/app.php` нужно заменить на паттерн по модулям
  (`withEvents(discover: [__DIR__.'/../app/Modules/*/Listeners'])`). Конкретный
  синтаксис проверяется по документации при внедрении.
- **Boilerplate:** один ServiceProvider и одна структура папок на каждый модуль.
- **Границы держатся дисциплиной, не инструментом:** PHP не запрещает
  импортировать класс из соседнего модуля.
- **Перенос существующего auth:** смена namespace, перенос тестов и миграций,
  обновление ссылок на модель `User` (например, `config/auth.php`).

**Сознательно отвергнуто:**

- **Локальные composer-пакеты (path repository):** своё версионирование и
  `composer.json` на модуль окупаются только при отдельных потребителях или
  команде.
- **`nwidart/laravel-modules`:** неофициальный пакет поверх того же, что мы
  делаем нативно.
- **Отдельная БД или схема на модуль:** противоречит общей схеме и FK между
  доменами.

**Не решено на момент записи:**

- В какой модуль войдёт `addresses` (Auth или отдельный Profile/Account).
- Нужны ли автоматические проверки границ (архитектурные тесты) — вернёмся,
  если границы начнут нарушаться.

**Когда пересматривать (Триггеры для рефакторинга):**

1. Границы регулярно нарушаются, и нужны автоматические проверки.
2. Появляется отдельный потребитель или команда у конкретного модуля. Тогда
   выделение в composer-пакет или сервис.
3. Миграции по модулям начинают мешать (конфликты порядка). Тогда вернуться
   к общей папке миграций.

---

_Last updated: 2026-11-11_