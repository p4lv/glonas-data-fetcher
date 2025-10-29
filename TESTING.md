# Testing Guide

Это руководство описывает стратегию тестирования и как запускать тесты для Glonass Import API.

## Установка

PHPUnit и тестовые зависимости уже установлены через composer:

```bash
composer install
```

## Запуск тестов

### Все тесты

```bash
./vendor/bin/phpunit
```

Результат:
```
Tests: 59, Assertions: 118
```

### Конкретная группа тестов

```bash
# Message классы (DTO)
./vendor/bin/phpunit tests/Message/

# Entity классы
./vendor/bin/phpunit tests/Entity/

# Service классы
./vendor/bin/phpunit tests/Service/
```

### С детальным выводом

```bash
./vendor/bin/phpunit --testdox
```

### Конкретный тест

```bash
./vendor/bin/phpunit tests/Service/GlonassApiClientTest.php
./vendor/bin/phpunit tests/Entity/VehicleTest.php
```

## Структура тестов

```
tests/
├── bootstrap.php              # Bootstrap для загрузки окружения
├── Message/                   # Тесты для DTO Message классов
│   ├── ParseVehiclesMessageTest.php
│   ├── ParseVehicleHistoryMessageTest.php
│   └── ParseVehicleTracksMessageTest.php
├── Entity/                    # Тесты для Doctrine Entity
│   ├── VehicleTest.php
│   ├── VehicleTrackTest.php
│   └── CommandHistoryTest.php
└── Service/                   # Тесты для сервисов с моками
    └── GlonassApiClientTest.php
```

## Покрытие тестами

### Message классы (11 тестов, 26 assertions)

**ParseVehiclesMessage:**
- ✅ Конструктор с пустыми фильтрами
- ✅ Конструктор с фильтрами
- ✅ getFilters() возвращает корректные данные

**ParseVehicleHistoryMessage:**
- ✅ Конструктор только с обязательными полями
- ✅ Конструктор со всеми полями
- ✅ getVehicleId() возвращает корректное значение
- ✅ DateTimeInterface опциональны

**ParseVehicleTracksMessage:**
- ✅ Конструктор устанавливает все поля
- ✅ getVehicleId() возвращает корректное значение
- ✅ getFrom() возвращает корректную дату
- ✅ getTo() возвращает корректную дату

### Entity классы (39 тестов, 70 assertions)

**Vehicle:**
- ✅ Конструктор инициализирует коллекции
- ✅ Конструктор устанавливает timestamps
- ✅ Все геттеры и сеттеры
- ✅ Fluent interface (method chaining)
- ✅ getId() возвращает null для новой entity

**VehicleTrack:**
- ✅ Конструктор устанавливает createdAt
- ✅ Все геттеры и сеттеры для GPS данных
- ✅ Связь с Vehicle entity
- ✅ Fluent interface

**CommandHistory:**
- ✅ Конструктор устанавливает createdAt
- ✅ Все геттеры и сеттеры для команд
- ✅ Связь с Vehicle entity
- ✅ Fluent interface

### Service классы (9 тестов, 22 assertions)

**GlonassApiClient:**
- ✅ Успешная аутентификация
- ✅ Неудачная аутентификация (нет AuthId)
- ✅ maskToken() с валидным токеном
- ✅ maskToken() с коротким токеном
- ✅ maskToken() с null
- ✅ isAuthenticated() возвращает false изначально
- ✅ getAuthToken() возвращает null изначально
- ✅ checkAuth() требует аутентификации
- ✅ getVehicles() требует аутентификации

## Типы тестов

### Unit тесты

Тестируют отдельные компоненты в изоляции с использованием моков для зависимостей.

**Примеры:**
- Message классы - простые DTO без зависимостей
- Entity классы - тесты геттеров/сеттеров и бизнес-логики
- GlonassApiClient - с моками HttpClient и Logger

### Что НЕ покрыто (намеренно)

**Controllers** - требуют интеграционных тестов с базой данных

**Console Commands** - требуют интеграционных тестов

**MessageHandlers** - требуют моков репозиториев и API клиента (можно добавить при необходимости)

**Repositories** - стандартные Doctrine репозитории, требуют интеграционных тестов с БД

## Best Practices

### 1. Использование моков

Для внешних зависимостей используйте моки:

```php
$httpClient = $this->createMock(HttpClientInterface::class);
$logger = $this->createMock(LoggerInterface::class);
```

### 2. Тестирование приватных методов

Используйте Reflection API:

```php
$reflection = new \ReflectionClass($this->apiClient);
$method = $reflection->getMethod('maskToken');
$method->setAccessible(true);
$result = $method->invoke($this->apiClient, $token);
```

### 3. Assertions

Используйте специфичные assertions:

```php
$this->assertSame($expected, $actual);      // Строгое сравнение
$this->assertEquals($expected, $actual);    // Сравнение значений
$this->assertInstanceOf(Class::class, $obj);
$this->assertNull($value);
$this->assertTrue($condition);
```

### 4. Test Doubles

- **Mock** - полный контроль над поведением
- **Stub** - возвращает заранее определенные значения
- **Spy** - записывает вызовы методов

## Continuous Integration

Для CI/CD добавьте в pipeline:

```yaml
# .github/workflows/tests.yml
- name: Run tests
  run: ./vendor/bin/phpunit
```

## Troubleshooting

### Ошибка "Class not found"

Убедитесь что composer autoload обновлен:

```bash
composer dump-autoload
```

### Тесты не запускаются

Проверьте phpunit.xml.dist и bootstrap.php существуют и корректны.

### Memory limit

Увеличьте memory limit в php.ini или через cli:

```bash
php -d memory_limit=512M vendor/bin/phpunit
```

## Дальнейшее развитие

### Интеграционные тесты

Добавить тесты для:
- REST API endpoints (с TestClient)
- Console commands (с CommandTester)
- Message handlers (с тестовой БД)

### Code Coverage

Установить xdebug и запустить:

```bash
./vendor/bin/phpunit --coverage-html coverage/
```

### Mutation Testing

Использовать Infection для проверки качества тестов:

```bash
composer require --dev infection/infection
./vendor/bin/infection
```

## Полезные ссылки

- [PHPUnit Documentation](https://phpunit.de/)
- [Symfony Testing](https://symfony.com/doc/current/testing.html)
- [Test Doubles](https://martinfowler.com/bliki/TestDouble.html)
