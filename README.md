# Glonass Import API

Приложение для парсинга и хранения GPS данных из Glonass API. Построено на Symfony 7.3 с использованием SQLite базы данных.

## ⚠️ Важно

**Текущий статус:** Приложение полностью функционально и готово к использованию. Для работы требуется учетная запись Glonass с соответствующими правами доступа к API endpoints.

**Известные ограничения:**
- API Glonass требует минимум **1 секунду между запросами** (rate limit)
- Endpoint `/api/v3/vehicles/find` может возвращать **403 Forbidden** или **429 Too Many Requests** в зависимости от прав доступа учетной записи
- Рекомендуется использовать асинхронный режим парсинга для больших объемов данных

Подробнее см. [TROUBLESHOOTING.md](TROUBLESHOOTING.md)

## Возможности

- Парсинг данных о транспортных средствах из Glonass API
- Сохранение GPS координат, скорости, курса и других параметров
- Хранение истории команд терминалов
- Сохранение треков движения транспорта
- REST API для доступа к данным
- Простой веб-интерфейс для просмотра и управления данными
- Асинхронная обработка через Symfony Messenger
- Поддержка как ручного, так и автоматического парсинга по расписанию

## Требования

- PHP 8.2 или выше
- Composer
- SQLite3
- Расширения PHP: pdo_sqlite, json, mbstring

## Установка

1. Клонируйте репозиторий или распакуйте архив:
```bash
cd /path/to/glonass_import_api
```

2. Установите зависимости:
```bash
composer install
```

3. Настройте переменные окружения:
```bash
cp .env.local.example .env.local
```

Отредактируйте `.env.local` и укажите ваши учетные данные Glonass API:
```env
GLONASS_API_URL=https://api.glonasssoft.ru
GLONASS_API_LOGIN=your_login
GLONASS_API_PASSWORD=your_password
```

4. Создайте базу данных и настройте Messenger:
```bash
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console messenger:setup-transports
```

5. **Протестируйте подключение к API:**
```bash
php bin/console app:test:api
```

Эта команда:
- Проверит аутентификацию
- Попробует получить список транспортных средств
- Покажет детальную информацию об ошибках, если они есть

**Важно:** Если вы получаете ошибки 403 или 429, см. [TROUBLESHOOTING.md](TROUBLESHOOTING.md)

## Использование

### Парсинг данных (вручную)

#### Получить все транспортные средства
```bash
php bin/console app:parse:vehicles
```

#### Получить историю команд для конкретного ТС
```bash
php bin/console app:parse:vehicle-history VEHICLE_ID --from="2024-01-01" --to="2024-01-31"
```

#### Получить треки движения для конкретного ТС
```bash
php bin/console app:parse:vehicle-tracks VEHICLE_ID --from="2024-01-01 00:00:00" --to="2024-01-31 23:59:59"
```

### Асинхронный парсинг

Добавьте флаг `--async` для выполнения в фоновом режиме:
```bash
php bin/console app:parse:vehicles --async
```

Запустите воркер для обработки очереди:
```bash
php bin/console messenger:consume async -vv
```

### Автоматический парсинг по расписанию

Добавьте в crontab:
```cron
# Парсинг транспортных средств каждый час
0 * * * * cd /path/to/glonass_import_api && php bin/console app:parse:vehicles --async

# Парсинг треков каждые 5 минут
*/5 * * * * cd /path/to/glonass_import_api && php bin/console app:parse:vehicle-tracks VEHICLE_ID --async
```

### Веб-интерфейс

Запустите встроенный сервер Symfony:
```bash
symfony server:start
# или
php -S localhost:8000 -t public/
```

Откройте в браузере: http://localhost:8000

#### Доступные страницы:
- `/` - Dashboard с общей статистикой
- `/vehicles` - Список всех транспортных средств
- `/vehicles/{id}` - Детальная информация о транспортном средстве

### REST API

#### Транспортные средства

**GET /api/vehicles** - Получить список всех ТС
```bash
curl http://localhost:8000/api/vehicles
```

**GET /api/vehicles/{id}** - Получить данные конкретного ТС
```bash
curl http://localhost:8000/api/vehicles/1
```

**DELETE /api/vehicles/{id}** - Удалить ТС
```bash
curl -X DELETE http://localhost:8000/api/vehicles/1
```

#### Треки движения

**GET /api/vehicles/{vehicleId}/tracks** - Получить треки ТС
```bash
# Последние 100 записей
curl http://localhost:8000/api/vehicles/1/tracks

# С фильтрацией по дате
curl "http://localhost:8000/api/vehicles/1/tracks?from=2024-01-01&to=2024-01-31"

# С ограничением количества
curl "http://localhost:8000/api/vehicles/1/tracks?limit=50"
```

**GET /api/vehicles/{vehicleId}/tracks/{id}** - Получить конкретную запись трека
```bash
curl http://localhost:8000/api/vehicles/1/tracks/1
```

**DELETE /api/vehicles/{vehicleId}/tracks/{id}** - Удалить запись трека
```bash
curl -X DELETE http://localhost:8000/api/vehicles/1/tracks/1
```

#### История команд

**GET /api/vehicles/{vehicleId}/commands** - Получить историю команд
```bash
# Последние 50 записей
curl http://localhost:8000/api/vehicles/1/commands

# С фильтрацией по дате
curl "http://localhost:8000/api/vehicles/1/commands?from=2024-01-01&to=2024-01-31"
```

**GET /api/vehicles/{vehicleId}/commands/{id}** - Получить конкретную команду
```bash
curl http://localhost:8000/api/vehicles/1/commands/1
```

**DELETE /api/vehicles/{vehicleId}/commands/{id}** - Удалить запись команды
```bash
curl -X DELETE http://localhost:8000/api/vehicles/1/commands/1
```

## Структура базы данных

### Таблица `vehicles`
Хранит основную информацию о транспортных средствах:
- `id` - внутренний ID
- `external_id` - ID из Glonass API (уникальный)
- `name` - название ТС
- `plate_number` - госномер
- `latitude`, `longitude` - текущие GPS координаты
- `speed` - текущая скорость
- `course` - направление движения
- `last_position_time` - время последней позиции
- `additional_data` - дополнительные данные в JSON
- `created_at`, `updated_at` - метки времени

### Таблица `vehicle_tracks`
Хранит треки движения:
- `id` - ID записи
- `vehicle_id` - связь с ТС
- `latitude`, `longitude` - GPS координаты точки
- `speed` - скорость в точке
- `course` - направление
- `altitude` - высота
- `satellites` - количество спутников
- `timestamp` - время фиксации точки
- `additional_data` - дополнительные данные в JSON

### Таблица `command_histories`
Хранит историю команд терминалов:
- `id` - ID записи
- `vehicle_id` - связь с ТС
- `command_type` - тип команды
- `command_text` - текст команды
- `response` - ответ терминала
- `latitude`, `longitude` - координаты при отправке команды
- `sent_at` - время отправки
- `received_at` - время получения ответа
- `status` - статус команды
- `additional_data` - дополнительные данные в JSON

## Архитектура

### Entity классы
- `App\Entity\Vehicle` - транспортное средство
- `App\Entity\VehicleTrack` - трек движения
- `App\Entity\CommandHistory` - история команд

### Сервисы
- `App\Service\GlonassApiClient` - клиент для работы с Glonass API
  - Автоматическая аутентификация
  - Соблюдение rate limit (1 секунда между запросами)
  - Обработка ошибок и логирование

### Message Bus
- `App\Message\ParseVehiclesMessage` - парсинг транспортных средств
- `App\Message\ParseVehicleHistoryMessage` - парсинг истории команд
- `App\Message\ParseVehicleTracksMessage` - парсинг треков

### Message Handlers
- `App\MessageHandler\ParseVehiclesMessageHandler`
- `App\MessageHandler\ParseVehicleHistoryMessageHandler`
- `App\MessageHandler\ParseVehicleTracksMessageHandler`

### Console Commands

**Парсинг данных:**
- `app:parse:vehicles` - парсинг транспортных средств
- `app:parse:vehicle-history` - парсинг истории команд
- `app:parse:vehicle-tracks` - парсинг треков

**Тестирование:**
- `app:test:api` - тестирование подключения к API и аутентификации
- `app:test:vehicle [VEHICLE_ID]` - получение данных конкретного ТС
- `app:test:rate-limit` - проверка работы rate limiting
- `app:debug:auth` - проверка передачи токена в заголовках запросов
- `app:auth:check` - проверка статуса аутентификации через GET /api/v3/auth/check

### REST API Controllers
- `App\Controller\Api\VehicleController` - CRUD для транспортных средств
- `App\Controller\Api\VehicleTrackController` - CRUD для треков
- `App\Controller\Api\CommandHistoryController` - CRUD для истории команд

### Web Controllers
- `App\Controller\HomeController` - главная страница (dashboard)
- `App\Controller\VehicleWebController` - веб-интерфейс для транспортных средств

## Особенности реализации

### Rate Limiting
API Glonass требует минимум 1 секунду между запросами. Это реализовано в `GlonassApiClient::enforceRateLimit()`.

**Как это работает:**
1. Замеряется время завершения предыдущего запроса
2. Перед новым запросом проверяется, прошла ли 1 секунда
3. Если нет - приложение ждет оставшееся время (+10ms буфер)
4. Только после этого отправляется новый запрос

Это гарантирует, что между **окончанием** предыдущего и **началом** следующего запроса всегда проходит >= 1 секунда.

**Тестирование:**
```bash
php bin/console app:test:rate-limit --count=5
```

Эта команда сделает 5 запросов и покажет детальную статистику времени между ними.

### Автоматическое продление токена
Токен аутентификации автоматически продлевается при каждом запросе, согласно документации API.

### Обработка ошибок
Все ошибки логируются через PSR-3 LoggerInterface. Можно настроить Monolog для записи в файлы или другие хранилища.

### Асинхронная обработка
Используется Symfony Messenger с Doctrine транспортом для очередей. Можно настроить Redis или RabbitMQ для production.

## Разработка

### Создание новой миграции
```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

### Просмотр логов
```bash
tail -f var/log/dev.log
```

### Очистка кэша
```bash
php bin/console cache:clear
```

### Запуск тестов
```bash
# Запустить все тесты
./vendor/bin/phpunit

# Запустить конкретную группу тестов
./vendor/bin/phpunit tests/Message/
./vendor/bin/phpunit tests/Entity/
./vendor/bin/phpunit tests/Service/

# Запустить с verbose output
./vendor/bin/phpunit --testdox
```

**Покрытие тестами:**
- Message классы (DTO) - 100%
- Entity классы - 100%
- GlonassApiClient service - основные методы покрыты
- Всего: 59 тестов, 118 assertions

## Production

### Оптимизация для production

1. Установите правильное окружение в `.env.local`:
```env
APP_ENV=prod
APP_DEBUG=0
```

2. Оптимизируйте autoloader:
```bash
composer install --no-dev --optimize-autoloader
```

3. Очистите и прогрейте кэш:
```bash
php bin/console cache:clear
php bin/console cache:warmup
```

4. Настройте веб-сервер (Nginx + PHP-FPM рекомендуется)

5. Настройте супервизор для Messenger воркеров:
```ini
[program:messenger-consume]
command=php /path/to/glonass_import_api/bin/console messenger:consume async --time-limit=3600
user=www-data
numprocs=2
autostart=true
autorestart=true
process_name=%(program_name)s_%(process_num)02d
```

## Troubleshooting

### Ошибка "Failed to authenticate"
Проверьте правильность учетных данных в `.env.local`.

### Ошибка "Rate limit exceeded"
API возвращает 429 - уменьшите частоту запросов или подождите.

### База данных не создается
SQLite создает файл автоматически. Проверьте права доступа к директории `var/`.

### Веб-интерфейс не работает
Убедитесь, что Twig Bundle установлен и настроен корректно.

## Тестирование

Проект покрыт unit тестами. Подробная информация в [TESTING.md](TESTING.md).

**Быстрый старт:**
```bash
# Запустить все тесты
./vendor/bin/phpunit

# С детальным выводом
./vendor/bin/phpunit --testdox
```

**Результаты:**
- ✅ 59 тестов
- ✅ 118 assertions
- ✅ 100% покрытие Message и Entity классов
- ✅ Основные методы GlonassApiClient покрыты

## Лицензия

MIT

## Автор

Generated with Claude Code
