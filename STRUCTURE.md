# Структура проекта

Детальное описание структуры приложения Glonass Import API.

## Дерево директорий

```
glonass_import_api/
├── bin/                        # Исполняемые файлы
│   └── console                 # Symfony Console
├── config/                     # Конфигурация
│   ├── packages/               # Конфигурация пакетов
│   │   ├── doctrine.yaml       # Настройки Doctrine ORM
│   │   ├── doctrine_migrations.yaml  # Настройки миграций
│   │   ├── framework.yaml      # Настройки Symfony Framework
│   │   ├── messenger.yaml      # Настройки Symfony Messenger
│   │   └── twig.yaml           # Настройки Twig
│   ├── bundles.php             # Регистрация бандлов
│   ├── routes.yaml             # Маршруты приложения
│   └── services.yaml           # Определение сервисов
├── migrations/                 # Миграции базы данных
│   └── Version*.php            # Файлы миграций
├── public/                     # Публичная директория (document root)
│   └── index.php               # Точка входа приложения
├── src/                        # Исходный код приложения
│   ├── Command/                # Console команды
│   │   ├── ParseVehiclesCommand.php
│   │   ├── ParseVehicleHistoryCommand.php
│   │   └── ParseVehicleTracksCommand.php
│   ├── Controller/             # HTTP контроллеры
│   │   ├── Api/                # REST API контроллеры
│   │   │   ├── VehicleController.php
│   │   │   ├── VehicleTrackController.php
│   │   │   └── CommandHistoryController.php
│   │   ├── HomeController.php  # Главная страница
│   │   └── VehicleWebController.php  # Веб-интерфейс для ТС
│   ├── Entity/                 # Doctrine entities (модели данных)
│   │   ├── Vehicle.php
│   │   ├── VehicleTrack.php
│   │   └── CommandHistory.php
│   ├── Message/                # Symfony Messenger сообщения
│   │   ├── ParseVehiclesMessage.php
│   │   ├── ParseVehicleHistoryMessage.php
│   │   └── ParseVehicleTracksMessage.php
│   ├── MessageHandler/         # Обработчики сообщений
│   │   ├── ParseVehiclesMessageHandler.php
│   │   ├── ParseVehicleHistoryMessageHandler.php
│   │   └── ParseVehicleTracksMessageHandler.php
│   ├── Repository/             # Doctrine репозитории
│   │   ├── VehicleRepository.php
│   │   ├── VehicleTrackRepository.php
│   │   └── CommandHistoryRepository.php
│   ├── Service/                # Бизнес-логика
│   │   └── GlonassApiClient.php
│   └── Kernel.php              # Ядро приложения
├── templates/                  # Twig шаблоны
│   ├── base.html.twig          # Базовый шаблон
│   ├── home/
│   │   └── index.html.twig     # Dashboard
│   └── vehicle/
│       ├── index.html.twig     # Список ТС
│       └── show.html.twig      # Детали ТС
├── var/                        # Переменные файлы (логи, кэш, БД)
│   ├── cache/                  # Кэш приложения
│   ├── log/                    # Логи
│   └── data.db                 # SQLite база данных
├── vendor/                     # Зависимости Composer
├── .env                        # Переменные окружения (по умолчанию)
├── .env.local.example          # Пример локальных переменных
├── .gitignore                  # Git ignore файл
├── composer.json               # Зависимости проекта
├── composer.lock               # Версии зависимостей
├── API_EXAMPLES.md             # Примеры использования API
├── QUICKSTART.md               # Быстрый старт
├── README.md                   # Основная документация
└── STRUCTURE.md                # Этот файл
```

## Подробное описание компонентов

### Entities (src/Entity/)

#### Vehicle.php
Основная модель транспортного средства.

**Свойства:**
- `id` - первичный ключ
- `externalId` - уникальный ID из Glonass API
- `name` - название ТС
- `plateNumber` - госномер
- `latitude`, `longitude` - текущие GPS координаты
- `speed` - текущая скорость (км/ч)
- `course` - направление движения (градусы)
- `lastPositionTime` - время последней позиции
- `additionalData` - дополнительные данные (JSON)
- `createdAt`, `updatedAt` - временные метки
- `tracks` - связь с треками (One-to-Many)
- `commandHistories` - связь с командами (One-to-Many)

#### VehicleTrack.php
Модель трека движения (точка GPS).

**Свойства:**
- `id` - первичный ключ
- `vehicle` - связь с транспортным средством (Many-to-One)
- `latitude`, `longitude` - GPS координаты точки
- `speed` - скорость в точке
- `course` - направление движения
- `altitude` - высота над уровнем моря
- `satellites` - количество спутников
- `timestamp` - время фиксации точки
- `additionalData` - дополнительные данные (JSON)
- `createdAt` - время создания записи

#### CommandHistory.php
Модель истории команды терминала.

**Свойства:**
- `id` - первичный ключ
- `vehicle` - связь с транспортным средством (Many-to-One)
- `commandType` - тип команды
- `commandText` - текст команды
- `response` - ответ терминала
- `latitude`, `longitude` - координаты при отправке
- `sentAt` - время отправки команды
- `receivedAt` - время получения ответа
- `status` - статус выполнения команды
- `additionalData` - дополнительные данные (JSON)
- `createdAt` - время создания записи

### Services (src/Service/)

#### GlonassApiClient.php
Клиент для работы с Glonass API.

**Методы:**
- `authenticate()` - аутентификация и получение токена
- `getVehicles($filters)` - получить список транспортных средств
- `getVehicle($vehicleId)` - получить данные конкретного ТС
- `getVehicleCommandHistory($vehicleId, $from, $to)` - получить историю команд
- `getVehicleTracks($vehicleId, $from, $to)` - получить треки движения
- `logout()` - выход и инвалидация токена

**Особенности:**
- Автоматическая аутентификация при первом запросе
- Rate limiting (1 секунда между запросами)
- Автоматическое продление токена
- Логирование всех операций

### Repositories (src/Repository/)

Репозитории предоставляют методы для работы с базой данных.

#### VehicleRepository.php
- `findByExternalId($externalId)` - найти по внешнему ID
- `findAllWithRecentPosition()` - найти все ТС с последней позицией

#### VehicleTrackRepository.php
- `findByVehicleAndDateRange($vehicle, $from, $to)` - найти треки по диапазону дат
- `findLatestByVehicle($vehicle, $limit)` - получить последние N треков

#### CommandHistoryRepository.php
- `findByVehicleAndDateRange($vehicle, $from, $to)` - найти команды по диапазону дат
- `findLatestByVehicle($vehicle, $limit)` - получить последние N команд

### Commands (src/Command/)

Console команды для ручного запуска парсинга.

#### ParseVehiclesCommand.php
```bash
php bin/console app:parse:vehicles [--async]
```

#### ParseVehicleHistoryCommand.php
```bash
php bin/console app:parse:vehicle-history VEHICLE_ID [--from=DATE] [--to=DATE] [--async]
```

#### ParseVehicleTracksCommand.php
```bash
php bin/console app:parse:vehicle-tracks VEHICLE_ID [--from=DATE] [--to=DATE] [--async]
```

### Messages & Handlers (src/Message/, src/MessageHandler/)

Асинхронная обработка через Symfony Messenger.

#### Сообщения (Message)
- `ParseVehiclesMessage` - парсинг транспортных средств
- `ParseVehicleHistoryMessage` - парсинг истории команд
- `ParseVehicleTracksMessage` - парсинг треков

#### Обработчики (MessageHandler)
- `ParseVehiclesMessageHandler` - обрабатывает ParseVehiclesMessage
- `ParseVehicleHistoryMessageHandler` - обрабатывает ParseVehicleHistoryMessage
- `ParseVehicleTracksMessageHandler` - обрабатывает ParseVehicleTracksMessage

**Поток работы:**
1. Команда создает сообщение (Message)
2. MessageBus отправляет сообщение в транспорт (Doctrine)
3. Воркер (`messenger:consume`) получает сообщение из транспорта
4. MessageHandler обрабатывает сообщение
5. Данные сохраняются в базу

### Controllers (src/Controller/)

#### REST API (src/Controller/Api/)

**VehicleController.php**
- `GET /api/vehicles` - список всех ТС
- `GET /api/vehicles/{id}` - данные конкретного ТС
- `DELETE /api/vehicles/{id}` - удалить ТС

**VehicleTrackController.php**
- `GET /api/vehicles/{vehicleId}/tracks` - список треков
- `GET /api/vehicles/{vehicleId}/tracks/{id}` - данные конкретного трека
- `DELETE /api/vehicles/{vehicleId}/tracks/{id}` - удалить трек

**CommandHistoryController.php**
- `GET /api/vehicles/{vehicleId}/commands` - список команд
- `GET /api/vehicles/{vehicleId}/commands/{id}` - данные конкретной команды
- `DELETE /api/vehicles/{vehicleId}/commands/{id}` - удалить команду

#### Web Interface (src/Controller/)

**HomeController.php**
- `GET /` - главная страница (dashboard)

**VehicleWebController.php**
- `GET /vehicles` - список всех ТС
- `GET /vehicles/{id}` - детали транспортного средства
- `POST /vehicles/{id}/delete` - удалить ТС

### Templates (templates/)

#### base.html.twig
Базовый шаблон с:
- Bootstrap 5 для стилизации
- Bootstrap Icons
- Навигационное меню
- Флеш-сообщения
- Footer

#### home/index.html.twig
Dashboard с:
- Статистикой (общее количество ТС, ТС с позицией, процент покрытия)
- Таблицей последних 10 транспортных средств
- Быстрыми командами для парсинга

#### vehicle/index.html.twig
Список всех транспортных средств с:
- Таблицей со всеми ТС
- GPS координатами
- Скоростью и курсом
- Временем последней позиции
- Кнопками действий (просмотр, удаление)

#### vehicle/show.html.twig
Детальная информация о ТС с:
- Основной информацией
- GPS позицией
- Статистикой (количество треков, команд)
- Дополнительными данными в JSON
- Ссылкой на Google Maps

## Конфигурация (config/)

### services.yaml
Определение сервисов и зависимостей.

**Параметры:**
- `glonass.api.url` - URL Glonass API
- `glonass.api.login` - логин для API
- `glonass.api.password` - пароль для API

**Сервисы:**
- Autowiring и autoconfigure для всех классов в `src/`
- Настройка GlonassApiClient с параметрами API

### packages/doctrine.yaml
Настройки Doctrine ORM:
- SQLite драйвер
- Автомаппинг entity классов
- Настройки для production (кэширование запросов)

### packages/messenger.yaml
Настройки Symfony Messenger:
- Транспорт `async` через Doctrine
- Транспорт `failed` для неудачных сообщений
- Маршрутизация сообщений в очередь `async`
- Retry стратегия (3 попытки с увеличением интервала)

### packages/framework.yaml
Базовые настройки Symfony:
- Секретный ключ
- Настройки сессий
- Обработка ошибок

### packages/twig.yaml
Настройки Twig:
- Путь к шаблонам
- Bootstrap 5 форм темы

### routes.yaml
Определение маршрутов:
- Автоматический routing для контроллеров через атрибуты
- Префикс `/api` для API контроллеров

## База данных (var/data.db)

### Схема таблиц

```sql
-- Транспортные средства
CREATE TABLE vehicles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    external_id VARCHAR(255) UNIQUE NOT NULL,
    name VARCHAR(255),
    plate_number VARCHAR(100),
    latitude FLOAT,
    longitude FLOAT,
    speed FLOAT,
    course FLOAT,
    last_position_time DATETIME,
    additional_data TEXT, -- JSON
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
);

-- Треки движения
CREATE TABLE vehicle_tracks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    vehicle_id INTEGER NOT NULL,
    latitude FLOAT NOT NULL,
    longitude FLOAT NOT NULL,
    speed FLOAT,
    course FLOAT,
    altitude FLOAT,
    satellites INTEGER,
    timestamp DATETIME NOT NULL,
    additional_data TEXT, -- JSON
    created_at DATETIME NOT NULL,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
);

-- История команд
CREATE TABLE command_histories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    vehicle_id INTEGER NOT NULL,
    command_type VARCHAR(255),
    command_text TEXT,
    response TEXT,
    latitude FLOAT,
    longitude FLOAT,
    sent_at DATETIME,
    received_at DATETIME,
    status VARCHAR(50),
    additional_data TEXT, -- JSON
    created_at DATETIME NOT NULL,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
);

-- Очередь сообщений Messenger
CREATE TABLE messenger_messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    body TEXT NOT NULL,
    headers TEXT NOT NULL,
    queue_name VARCHAR(190) NOT NULL,
    created_at DATETIME NOT NULL,
    available_at DATETIME NOT NULL,
    delivered_at DATETIME
);
```

### Индексы

```sql
-- Для быстрого поиска по external_id
CREATE INDEX idx_external_id ON vehicles(external_id);

-- Для быстрого поиска треков по ТС и времени
CREATE INDEX idx_vehicle_timestamp ON vehicle_tracks(vehicle_id, timestamp);

-- Для быстрого поиска команд по ТС и времени
CREATE INDEX idx_vehicle_sent_at ON command_histories(vehicle_id, sent_at);
```

## Переменные окружения (.env)

```env
# Окружение приложения (dev, prod)
APP_ENV=dev

# Секретный ключ (для production должен быть изменен)
APP_SECRET=changeme_generate_a_secret_key

# URL базы данных SQLite
DATABASE_URL="sqlite:///%kernel.project_dir%/var/data.db"

# Конфигурация Glonass API
GLONASS_API_URL=https://api.glonasssoft.ru
GLONASS_API_LOGIN=your_login_here
GLONASS_API_PASSWORD=your_password_here

# Транспорт Messenger (Doctrine)
MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0
```

## Процесс парсинга данных

### Синхронный режим

```
User запускает команду
    ↓
Console Command создает Message
    ↓
MessageBus отправляет Message в Handler
    ↓
Handler синхронно обрабатывает
    ↓
Данные сохраняются в БД
    ↓
Команда завершается
```

### Асинхронный режим

```
User запускает команду с --async
    ↓
Console Command создает Message
    ↓
MessageBus отправляет Message в транспорт
    ↓
Message сохраняется в таблице messenger_messages
    ↓
Команда завершается (почти мгновенно)

Отдельно:
Worker (messenger:consume) читает таблицу
    ↓
Worker получает Message
    ↓
Handler обрабатывает Message
    ↓
Данные сохраняются в БД
    ↓
Worker удаляет Message из очереди
```

## Расширение функционала

### Добавление нового типа данных

1. Создайте Entity в `src/Entity/`
2. Создайте Repository в `src/Repository/`
3. Создайте Message в `src/Message/`
4. Создайте MessageHandler в `src/MessageHandler/`
5. Создайте Command в `src/Command/`
6. Создайте Controller в `src/Controller/Api/`
7. Добавьте маршрутизацию в `config/packages/messenger.yaml`
8. Создайте миграцию: `php bin/console doctrine:migrations:diff`
9. Выполните миграцию: `php bin/console doctrine:migrations:migrate`

### Добавление нового API endpoint

1. Создайте метод в Controller с атрибутом `#[Route]`
2. Используйте Repository для работы с данными
3. Верните JsonResponse

### Добавление новой веб-страницы

1. Создайте метод в Controller с атрибутом `#[Route]`
2. Создайте Twig шаблон в `templates/`
3. Верните `$this->render('template.html.twig', $data)`
