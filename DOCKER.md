# Docker - Руководство по развертыванию

## Требования

- Docker Engine 20.10+
- Docker Compose 2.0+

## Быстрый старт

### 1. Клонирование репозитория

```bash
git clone <repository-url>
cd glonass_import_api
```

### 2. Настройка переменных окружения

Скопируйте файл с примером и заполните необходимые значения:

```bash
cp .env.local.example .env.local
```

Отредактируйте `.env.local` и укажите:
```env
GLONASS_API_URL=https://your-api-url
GLONASS_API_LOGIN=your_login
GLONASS_API_PASSWORD=your_password
```

### 3. Запуск проекта

```bash
# Сборка и запуск всех контейнеров
docker-compose up -d

# Установка зависимостей (если не установлены)
docker-compose exec app composer install

# Выполнение миграций базы данных
docker-compose exec app php bin/console doctrine:migrations:migrate --no-interaction

# Проверка статуса контейнеров
docker-compose ps
```

### 4. Доступ к приложению

Приложение будет доступно по адресу: **http://localhost:8080**

## Основные команды Docker

### Управление контейнерами

```bash
# Запустить все сервисы
docker-compose up -d

# Остановить все сервисы
docker-compose down

# Перезапустить сервисы
docker-compose restart

# Остановить и удалить контейнеры, сети и volumes
docker-compose down -v
```

### Просмотр логов

```bash
# Все логи
docker-compose logs

# Логи конкретного сервиса
docker-compose logs app
docker-compose logs nginx
docker-compose logs messenger-worker

# Логи в реальном времени
docker-compose logs -f

# Последние 100 строк логов
docker-compose logs --tail=100
```

### Выполнение команд внутри контейнера

```bash
# Выполнить команду в контейнере app
docker-compose exec app php bin/console <command>

# Примеры:
docker-compose exec app php bin/console cache:clear
docker-compose exec app php bin/console doctrine:migrations:migrate
docker-compose exec app php bin/console app:sync-vehicles
docker-compose exec app php bin/console app:sync-tracks

# Войти в shell контейнера
docker-compose exec app bash

# Проверить версию PHP
docker-compose exec app php -v
```

## Symfony команды через Docker

### Работа с базой данных

```bash
# Создать базу данных (если нужно)
docker-compose exec app php bin/console doctrine:database:create

# Выполнить миграции
docker-compose exec app php bin/console doctrine:migrations:migrate

# Откатить последнюю миграцию
docker-compose exec app php bin/console doctrine:migrations:migrate prev

# Создать новую миграцию
docker-compose exec app php bin/console doctrine:migrations:generate
```

### Очистка кеша

```bash
# Очистить кеш для dev окружения
docker-compose exec app php bin/console cache:clear

# Очистить кеш для prod окружения
docker-compose exec app php bin/console cache:clear --env=prod
```

### Работа с Messenger (очереди)

```bash
# Просмотр сообщений в очереди
docker-compose exec app php bin/console messenger:stats

# Обработка сообщений вручную
docker-compose exec app php bin/console messenger:consume async -vv

# Перезапуск worker'а
docker-compose restart messenger-worker
```

### Синхронизация данных GLONASS

```bash
# Синхронизация автомобилей
docker-compose exec app php bin/console app:sync-vehicles

# Синхронизация треков
docker-compose exec app php bin/console app:sync-tracks

# Синхронизация с определенной даты
docker-compose exec app php bin/console app:sync-tracks --start-date="2024-01-01"
```

## Тестирование

```bash
# Запустить все тесты
docker-compose exec app php bin/phpunit

# Запустить конкретный тест
docker-compose exec app php bin/phpunit tests/Entity/VehicleTest.php

# Запустить тесты с покрытием
docker-compose exec app php bin/phpunit --coverage-html var/coverage
```

## Обновление зависимостей

```bash
# Обновить composer зависимости
docker-compose exec app composer update

# Установить новый пакет
docker-compose exec app composer require vendor/package

# Удалить пакет
docker-compose exec app composer remove vendor/package
```

## Архитектура Docker

### Контейнеры

1. **app** - PHP-FPM 8.3
   - Основное приложение Symfony
   - Обработка PHP кода
   - Порт: 9000 (внутренний)

2. **nginx** - Nginx Alpine
   - Веб-сервер
   - Проксирование запросов к PHP-FPM
   - Порт: 8080 (внешний) → 80 (внутренний)

3. **messenger-worker** - PHP CLI
   - Обработка асинхронных задач
   - Работает в фоновом режиме

### Volumes

```yaml
volumes:
  - ./:/var/www/html           # Код приложения
  - ./var/data:/var/www/html/var/data  # База данных SQLite
```

### Сеть

Все контейнеры находятся в одной сети `glonass-network` и могут общаться между собой по именам сервисов.

## Разработка

### Hot Reload

Код автоматически обновляется благодаря volume mapping. Изменения в PHP файлах применяются сразу.

### Отладка

```bash
# Просмотр переменных окружения
docker-compose exec app env

# Проверка конфигурации Symfony
docker-compose exec app php bin/console debug:config

# Просмотр маршрутов
docker-compose exec app php bin/console debug:router

# Просмотр сервисов
docker-compose exec app php bin/console debug:container
```

## Production

Для production окружения рекомендуется:

1. Изменить Dockerfile:
```dockerfile
# Уже установлено: --no-dev
RUN composer install --no-interaction --optimize-autoloader --no-dev
```

2. Изменить переменные окружения:
```env
APP_ENV=prod
APP_DEBUG=0
```

3. Собрать оптимизированный кеш:
```bash
docker-compose exec app php bin/console cache:warmup --env=prod
```

## Решение проблем

### Контейнеры не запускаются

```bash
# Проверить логи
docker-compose logs

# Пересобрать образы
docker-compose build --no-cache
docker-compose up -d
```

### Проблемы с правами доступа

```bash
# Исправить права на папки
docker-compose exec app chown -R www-data:www-data var
docker-compose exec app chmod -R 775 var
```

### База данных не создается

```bash
# Создать вручную директорию и файл
docker-compose exec app mkdir -p var/data
docker-compose exec app touch var/data/data.db
docker-compose exec app chown -R www-data:www-data var/data
docker-compose exec app php bin/console doctrine:migrations:migrate
```

### Очистка всех данных

```bash
# ВНИМАНИЕ: Удалит все данные!
docker-compose down -v
rm -rf var/cache/* var/log/* var/data/data.db
docker-compose up -d
docker-compose exec app php bin/console doctrine:migrations:migrate
```

### Проблемы с Composer

```bash
# Очистить кеш composer
docker-compose exec app composer clear-cache

# Переустановить зависимости
docker-compose exec app rm -rf vendor
docker-compose exec app composer install
```

## Мониторинг

### Проверка здоровья контейнеров

```bash
# Статус всех контейнеров
docker-compose ps

# Использование ресурсов
docker stats

# Информация о конкретном контейнере
docker inspect glonass_app
```

### Проверка базы данных

```bash
# Войти в SQLite
docker-compose exec app sqlite3 var/data/data.db

# Внутри SQLite:
.tables              # Список таблиц
.schema vehicles     # Структура таблицы
SELECT * FROM vehicles LIMIT 10;  # Просмотр данных
.quit                # Выход
```

## Бэкап и восстановление

### Бэкап базы данных

```bash
# Создать бэкап
docker-compose exec app cp var/data/data.db var/data/data.db.backup

# Или скопировать на хост
docker cp glonass_app:/var/www/html/var/data/data.db ./backup-$(date +%Y%m%d).db
```

### Восстановление базы данных

```bash
# Восстановить из бэкапа
docker cp ./backup-20241112.db glonass_app:/var/www/html/var/data/data.db
docker-compose exec app chown www-data:www-data var/data/data.db
docker-compose restart app
```

## Полезные ссылки

- [Docker Documentation](https://docs.docker.com/)
- [Docker Compose Documentation](https://docs.docker.com/compose/)
- [Symfony Docker Documentation](https://symfony.com/doc/current/setup/docker.html)
- [PHP Docker Images](https://hub.docker.com/_/php)
