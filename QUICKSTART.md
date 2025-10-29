# Quick Start Guide

Быстрое руководство по запуску приложения Glonass Import API.

## Шаг 1: Установка зависимостей

```bash
composer install
```

## Шаг 2: Конфигурация

Учетные данные для Glonass API уже настроены в `.env`. Если нужно изменить:

```bash
# Отредактируйте файл .env
nano .env

# Или создайте .env.local для локальных настроек
cp .env.local.example .env.local
nano .env.local
```

Убедитесь, что указаны правильные данные:
```env
GLONASS_API_URL=https://api.glonasssoft.ru
GLONASS_API_LOGIN=ваш_логин
GLONASS_API_PASSWORD=ваш_пароль
```

## Шаг 3: База данных

База данных SQLite уже создана и миграции применены. Если нужно пересоздать:

```bash
# Удалить текущую базу
rm var/data.db*

# Выполнить миграции заново
php bin/console doctrine:migrations:migrate --no-interaction
```

## Шаг 4: Первый парсинг данных

Запустите парсинг транспортных средств:

```bash
php bin/console app:parse:vehicles
```

Эта команда:
1. Подключится к Glonass API
2. Получит список всех транспортных средств
3. Сохранит их в базу данных

Ожидаемый вывод:
```
Parsing Vehicles from Glonass API
==================================

 [OK] Vehicles parsing completed successfully.
```

## Шаг 5: Проверка данных

Проверьте, что данные сохранены:

```bash
sqlite3 var/data.db "SELECT id, name, plate_number, latitude, longitude FROM vehicles LIMIT 5;"
```

Или используйте веб-интерфейс (следующий шаг).

## Шаг 6: Запуск веб-сервера

Запустите встроенный веб-сервер PHP:

```bash
php -S localhost:8000 -t public/
```

Откройте в браузере: http://localhost:8000

Вы увидите:
- Dashboard с общей статистикой
- Список транспортных средств
- Детальную информацию о каждом ТС

## Шаг 7: Тестирование REST API

Откройте новый терминал и проверьте API:

```bash
# Получить список всех транспортных средств
curl http://localhost:8000/api/vehicles | jq

# Получить данные конкретного ТС (замените 1 на реальный ID)
curl http://localhost:8000/api/vehicles/1 | jq
```

## Дополнительные команды

### Парсинг истории команд

После того как у вас есть транспортные средства в базе:

```bash
# Получите ID транспортного средства (externalId из базы)
sqlite3 var/data.db "SELECT id, external_id, name FROM vehicles LIMIT 1;"

# Запустите парсинг истории команд (замените VEHICLE_ID на реальный externalId)
php bin/console app:parse:vehicle-history VEHICLE_ID --from="2024-01-01" --to="2024-01-31"
```

### Парсинг треков движения

```bash
# Запустите парсинг треков (замените VEHICLE_ID на реальный externalId)
php bin/console app:parse:vehicle-tracks VEHICLE_ID --from="2024-01-01 00:00:00" --to="2024-01-31 23:59:59"
```

### Асинхронный парсинг (рекомендуется для больших объемов)

Запустите парсинг в фоне:

```bash
# Добавить задачу в очередь
php bin/console app:parse:vehicles --async

# В отдельном терминале запустить воркер для обработки очереди
php bin/console messenger:consume async -vv
```

## Автоматизация (опционально)

Добавьте в crontab для регулярного парсинга:

```bash
crontab -e
```

Добавьте строки:

```cron
# Парсинг транспортных средств каждый час
0 * * * * cd /path/to/glonass_import_api && php bin/console app:parse:vehicles >> var/log/cron.log 2>&1

# Парсинг треков каждые 5 минут для конкретного ТС
*/5 * * * * cd /path/to/glonass_import_api && php bin/console app:parse:vehicle-tracks VEHICLE_ID >> var/log/cron.log 2>&1
```

## Проверка работоспособности

### 1. Проверка базы данных

```bash
# Количество транспортных средств
sqlite3 var/data.db "SELECT COUNT(*) FROM vehicles;"

# Количество треков
sqlite3 var/data.db "SELECT COUNT(*) FROM vehicle_tracks;"

# Количество команд
sqlite3 var/data.db "SELECT COUNT(*) FROM command_histories;"
```

### 2. Проверка веб-интерфейса

Перейдите по адресам:
- http://localhost:8000 - Dashboard
- http://localhost:8000/vehicles - Список транспортных средств
- http://localhost:8000/vehicles/1 - Детали транспортного средства

### 3. Проверка REST API

```bash
# Тест всех endpoints
curl http://localhost:8000/api/vehicles
curl http://localhost:8000/api/vehicles/1
curl http://localhost:8000/api/vehicles/1/tracks
curl http://localhost:8000/api/vehicles/1/commands
```

## Структура данных

### База данных SQLite

Файл: `var/data.db`

Таблицы:
- `vehicles` - транспортные средства
- `vehicle_tracks` - треки движения
- `command_histories` - история команд
- `messenger_messages` - очередь асинхронных задач

### Логи

Файлы логов находятся в: `var/log/`

Просмотр логов:
```bash
tail -f var/log/dev.log
```

## Типичные проблемы и решения

### Ошибка "Failed to authenticate"

**Проблема:** Неверные учетные данные для Glonass API.

**Решение:** Проверьте данные в `.env`:
```bash
cat .env | grep GLONASS
```

### Ошибка "No vehicles found"

**Проблема:** API не вернул данные или учетная запись пустая.

**Решение:** Проверьте логи и убедитесь, что учетная запись имеет доступ к транспортным средствам.

### Веб-интерфейс не загружается

**Проблема:** Порт 8000 занят или веб-сервер не запущен.

**Решение:**
```bash
# Проверьте, запущен ли сервер
lsof -ti:8000

# Используйте другой порт
php -S localhost:8080 -t public/
```

### База данных заблокирована

**Проблема:** SQLite не поддерживает одновременную запись из нескольких процессов.

**Решение:** Не запускайте несколько команд парсинга одновременно. Используйте асинхронный режим с одним воркером.

## Следующие шаги

После успешного запуска:

1. Изучите [README.md](README.md) для подробной документации
2. Ознакомьтесь с [API_EXAMPLES.md](API_EXAMPLES.md) для примеров использования API
3. Настройте автоматический парсинг через cron
4. Настройте мониторинг и алерты (если требуется)
5. Для production окружения следуйте инструкциям в разделе "Production" в README.md

## Помощь

Если возникли проблемы:

1. Проверьте логи: `tail -f var/log/dev.log`
2. Убедитесь, что все зависимости установлены: `composer install`
3. Проверьте права доступа к директории `var/`
4. Убедитесь, что SQLite установлен: `sqlite3 --version`
5. Проверьте версию PHP: `php -v` (требуется >= 8.2)

## Полезные команды

```bash
# Очистить кэш
php bin/console cache:clear

# Проверить конфигурацию
php bin/console debug:config framework

# Список всех команд
php bin/console list

# Проверить маршруты
php bin/console debug:router

# Проверить сервисы
php bin/console debug:container
```
