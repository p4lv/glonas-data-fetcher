# Troubleshooting Guide

Руководство по решению проблем при работе с Glonass Import API.

## Проблемы с API

### 403 Forbidden

**Проблема:**
```
[error] Vehicles parsing failed: HTTP/1.1 403 Forbidden returned for "https://regions.glonasssoft.ru/api/v3/vehicles/find".
```

**Причины:**
1. Учетная запись не имеет прав доступа к endpoint `/api/v3/vehicles/find`
2. Токен аутентификации устарел или невалиден
3. Ограничения на уровне API для вашей учетной записи

**Решения:**

1. **Проверьте права доступа:**
   - Свяжитесь с администратором Glonass для получения необходимых прав
   - Уточните, какие endpoints доступны для вашей учетной записи

2. **Используйте альтернативный подход:**
   Если у вас есть конкретный ID транспортного средства, используйте прямой доступ:
   ```bash
   php bin/console app:test:vehicle [VEHICLE_ID]
   ```

3. **Проверьте тип учетной записи:**
   - Некоторые endpoints могут требовать корпоративный аккаунт
   - Уточните уровень доступа вашей учетной записи

### 429 Too Many Requests

**Проблема:**
```
[error] Failed to get vehicles: HTTP/1.1 429 Too Many Requests returned for "https://regions.glonasssoft.ru/api/v3/vehicles/find".
```

**Причина:**
Превышен лимит запросов к API. Glonass API требует минимум 1 секунду между запросами.

**Решения:**

1. **Увеличьте задержку между запросами:**
   Откройте `src/Service/GlonassApiClient.php` и увеличьте `RATE_LIMIT_DELAY`:
   ```php
   private const RATE_LIMIT_DELAY = 2; // Увеличить с 1 до 2 секунд
   ```

2. **Избегайте параллельных запросов:**
   - Не запускайте несколько команд парсинга одновременно
   - Используйте только один воркер Messenger

3. **Подождите перед повторной попыткой:**
   ```bash
   # Подождите 1-2 минуты перед повторным запуском
   sleep 120
   php bin/console app:parse:vehicles
   ```

4. **Очистите очередь failed сообщений:**
   ```bash
   # Посмотрите сколько неудачных сообщений
   sqlite3 var/data.db "SELECT COUNT(*) FROM messenger_messages WHERE queue_name='failed';"

   # Очистите их
   sqlite3 var/data.db "DELETE FROM messenger_messages WHERE queue_name='failed';"
   ```

### 401 Unauthorized

**Проблема:**
```
[error] Authentication failed: HTTP/1.1 401 Unauthorized
```

**Причины:**
1. Неверный логин или пароль
2. Учетная запись заблокирована
3. Истек срок действия учетной записи

**Решения:**

1. **Проверьте учетные данные:**
   ```bash
   cat .env | grep GLONASS
   ```

2. **Убедитесь, что нет лишних пробелов:**
   ```env
   GLONASS_API_LOGIN=yourlogin  # Без пробелов!
   GLONASS_API_PASSWORD=yourpassword  # Без пробелов!
   ```

3. **Попробуйте войти через веб-интерфейс:**
   Проверьте доступ на https://regions.glonasssoft.ru

4. **Свяжитесь с поддержкой:**
   Если учетные данные верны, но ошибка повторяется

### Connection Timeout / Could not resolve host

**Проблема:**
```
[error] Transport error: Could not resolve host: api.glonasssoft.ru
[error] cURL error 28: Connection timeout
```

**Причины:**
1. Проблемы с интернет-соединением
2. Firewall блокирует исходящие соединения
3. DNS не может резолвить хост
4. API сервер недоступен

**Решения:**

1. **Проверьте интернет-соединение:**
   ```bash
   ping google.com
   ```

2. **Проверьте доступность API:**
   ```bash
   curl -I https://regions.glonasssoft.ru
   ```

3. **Проверьте DNS:**
   ```bash
   nslookup regions.glonasssoft.ru
   ```

4. **Проверьте firewall:**
   - Убедитесь, что порт 443 (HTTPS) открыт для исходящих соединений
   - Добавьте `regions.glonasssoft.ru` в whitelist

5. **Используйте VPN:**
   Если API доступен только из определенных регионов

## Проблемы с базой данных

### Database is locked

**Проблема:**
```
[error] SQLSTATE[HY000]: General error: 5 database is locked
```

**Причина:**
SQLite не поддерживает одновременную запись из нескольких процессов.

**Решения:**

1. **Остановите все процессы:**
   ```bash
   # Остановите все воркеры
   pkill -f "messenger:consume"

   # Остановите веб-сервер
   pkill -f "php -S"
   ```

2. **Используйте только один воркер:**
   ```bash
   php bin/console messenger:consume async --limit=100
   ```

3. **Для production используйте PostgreSQL/MySQL:**
   ```env
   DATABASE_URL="mysql://user:pass@localhost:3306/glonass"
   ```

### No such table: messenger_messages

**Проблема:**
```
[error] SQLSTATE[HY000]: General error: 1 no such table: messenger_messages
```

**Решение:**
```bash
php bin/console messenger:setup-transports
```

### Migration failed

**Проблема:**
```
[error] Migration DoctrineMigrations\Version... failed
```

**Решения:**

1. **Удалите базу и пересоздайте:**
   ```bash
   rm var/data.db*
   php bin/console doctrine:migrations:migrate --no-interaction
   php bin/console messenger:setup-transports
   ```

2. **Проверьте права доступа:**
   ```bash
   ls -la var/
   chmod 755 var/
   ```

## Проблемы с парсингом

### No vehicles found

**Проблема:**
Парсинг выполняется успешно, но транспортных средств в базе нет.

**Причины:**
1. API вернул пустой список
2. У учетной записи нет доступных транспортных средств
3. Сообщения в очереди не обработаны

**Решения:**

1. **Проверьте очередь сообщений:**
   ```bash
   sqlite3 var/data.db "SELECT COUNT(*) FROM messenger_messages;"
   ```

2. **Запустите воркер:**
   ```bash
   php bin/console messenger:consume async -vv
   ```

3. **Проверьте логи:**
   ```bash
   tail -f var/log/dev.log
   ```

4. **Проверьте права доступа к API:**
   ```bash
   php bin/console app:test:api
   ```

### Parsing is too slow

**Проблема:**
Парсинг занимает слишком много времени.

**Причина:**
Rate limit 1 секунда между запросами.

**Решения:**

1. **Используйте асинхронный режим:**
   ```bash
   php bin/console app:parse:vehicles --async
   ```

2. **Парсите данные порциями:**
   ```bash
   # Только нужные ТС
   php bin/console app:parse:vehicle-tracks VEHICLE_ID_1 --async
   php bin/console app:parse:vehicle-tracks VEHICLE_ID_2 --async
   ```

3. **Оптимизируйте частоту парсинга:**
   ```cron
   # Раз в час вместо каждые 5 минут
   0 * * * * php bin/console app:parse:vehicles --async
   ```

## Проблемы с веб-интерфейсом

### 404 Not Found

**Проблема:**
При открытии http://localhost:8000 получаете 404.

**Решения:**

1. **Убедитесь, что сервер запущен:**
   ```bash
   php -S localhost:8000 -t public/
   ```

2. **Проверьте правильность URL:**
   - ✅ `http://localhost:8000` (без public/)
   - ❌ `http://localhost:8000/public/`

3. **Очистите кэш:**
   ```bash
   php bin/console cache:clear
   ```

### Template not found

**Проблема:**
```
[error] Unable to find template "base.html.twig"
```

**Решение:**
```bash
# Проверьте наличие файлов
ls -la templates/

# Очистите кэш
php bin/console cache:clear
```

### CSS/Styles not loading

**Проблема:**
Страница загружается, но без стилей Bootstrap.

**Причина:**
CDN Bootstrap недоступен или блокируется.

**Решение:**
1. Проверьте интернет-соединение
2. Проверьте консоль браузера (F12) на наличие ошибок
3. При необходимости скачайте Bootstrap локально

## Проблемы с командами

### Command not found

**Проблема:**
```
[error] Command "app:parse:vehicles" is not defined
```

**Решения:**

1. **Очистите кэш:**
   ```bash
   php bin/console cache:clear
   ```

2. **Проверьте autoload:**
   ```bash
   composer dump-autoload
   ```

3. **Убедитесь, что класс существует:**
   ```bash
   ls -la src/Command/
   ```

### Memory limit exceeded

**Проблема:**
```
Fatal error: Allowed memory size of X bytes exhausted
```

**Решения:**

1. **Увеличьте memory_limit:**
   ```bash
   php -d memory_limit=512M bin/console app:parse:vehicles
   ```

2. **Обрабатывайте данные порциями:**
   Используйте параметры фильтрации в API

3. **Оптимизируйте код:**
   Используйте `$entityManager->clear()` после больших batch операций

## Получение помощи

### Включение подробного логирования

```bash
# Запуск команды с максимальной детализацией
php bin/console app:parse:vehicles -vvv

# Запуск воркера с детальным выводом
php bin/console messenger:consume async -vvv
```

### Проверка конфигурации

```bash
# Проверка routes
php bin/console debug:router

# Проверка сервисов
php bin/console debug:container

# Проверка конфигурации
php bin/console debug:config framework
```

### Сбор информации для отчета об ошибке

```bash
# Версия PHP
php -v

# Версия Symfony
php bin/console --version

# Структура базы данных
sqlite3 var/data.db ".schema"

# Количество записей
sqlite3 var/data.db "SELECT
  (SELECT COUNT(*) FROM vehicles) as vehicles,
  (SELECT COUNT(*) FROM vehicle_tracks) as tracks,
  (SELECT COUNT(*) FROM command_histories) as commands,
  (SELECT COUNT(*) FROM messenger_messages) as queue;"

# Последние логи
tail -100 var/log/dev.log
```

## Полезные команды для диагностики

```bash
# Проверить соединение с API
php bin/console app:test:api

# Проверить конкретное ТС
php bin/console app:test:vehicle [VEHICLE_ID]

# Проверить очередь
php bin/console messenger:stats

# Обработать failed сообщения
php bin/console messenger:consume failed

# Очистить очередь
php bin/console messenger:stop-workers

# Проверить миграции
php bin/console doctrine:migrations:status

# Проверить схему БД
php bin/console doctrine:schema:validate
```

## Контакты и ресурсы

- **Документация Glonass API:** https://wiki.glonasssoft.ru/bin/view/API/
- **Документация Symfony:** https://symfony.com/doc/current/index.html
- **Документация Doctrine:** https://www.doctrine-project.org/projects/doctrine-orm/en/latest/

При обращении в поддержку предоставьте:
1. Версию PHP и Symfony
2. Полный текст ошибки
3. Логи из `var/log/dev.log`
4. Вывод команды `php bin/console app:test:api`
