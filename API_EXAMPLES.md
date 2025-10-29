# API Examples

Примеры использования REST API для работы с GPS данными.

## Общая информация

- Base URL: `http://localhost:8000/api`
- Формат данных: JSON
- Все даты в формате: `Y-m-d H:i:s` (например, `2024-01-15 14:30:00`)

## Транспортные средства (Vehicles)

### Получить список всех транспортных средств

**Request:**
```bash
curl -X GET http://localhost:8000/api/vehicles
```

**Response (200 OK):**
```json
[
  {
    "id": 1,
    "externalId": "12345-67890-abcdef",
    "name": "Камаз 001",
    "plateNumber": "А123БВ77",
    "latitude": 55.751244,
    "longitude": 37.618423,
    "speed": 45.5,
    "course": 180.0,
    "lastPositionTime": "2024-01-15 14:30:00",
    "updatedAt": "2024-01-15 14:35:00"
  },
  {
    "id": 2,
    "externalId": "98765-43210-fedcba",
    "name": "ГАЗель 002",
    "plateNumber": "В456ГД77",
    "latitude": 55.755826,
    "longitude": 37.617300,
    "speed": 0.0,
    "course": null,
    "lastPositionTime": "2024-01-15 12:00:00",
    "updatedAt": "2024-01-15 12:05:00"
  }
]
```

### Получить данные конкретного транспортного средства

**Request:**
```bash
curl -X GET http://localhost:8000/api/vehicles/1
```

**Response (200 OK):**
```json
{
  "id": 1,
  "externalId": "12345-67890-abcdef",
  "name": "Камаз 001",
  "plateNumber": "А123БВ77",
  "latitude": 55.751244,
  "longitude": 37.618423,
  "speed": 45.5,
  "course": 180.0,
  "lastPositionTime": "2024-01-15 14:30:00",
  "additionalData": {
    "Id": "12345-67890-abcdef",
    "Name": "Камаз 001",
    "PlateNumber": "А123БВ77",
    "Latitude": 55.751244,
    "Longitude": 37.618423,
    "Speed": 45.5,
    "Course": 180.0,
    "LastPositionTime": "2024-01-15T14:30:00",
    "DriverName": "Иванов И.И.",
    "FuelLevel": 75.5
  },
  "createdAt": "2024-01-10 10:00:00",
  "updatedAt": "2024-01-15 14:35:00"
}
```

### Удалить транспортное средство

**Request:**
```bash
curl -X DELETE http://localhost:8000/api/vehicles/1
```

**Response (200 OK):**
```json
{
  "message": "Vehicle deleted successfully"
}
```

**Response (404 Not Found):**
```json
{
  "error": "Vehicle not found"
}
```

## Треки движения (Tracks)

### Получить треки транспортного средства

#### Последние 100 записей (по умолчанию)

**Request:**
```bash
curl -X GET http://localhost:8000/api/vehicles/1/tracks
```

#### С параметрами фильтрации

**Request:**
```bash
# По диапазону дат
curl -X GET "http://localhost:8000/api/vehicles/1/tracks?from=2024-01-01%2000:00:00&to=2024-01-31%2023:59:59"

# С ограничением количества
curl -X GET "http://localhost:8000/api/vehicles/1/tracks?limit=50"
```

**Response (200 OK):**
```json
{
  "vehicle": {
    "id": 1,
    "name": "Камаз 001"
  },
  "tracks": [
    {
      "id": 1,
      "latitude": 55.751244,
      "longitude": 37.618423,
      "speed": 45.5,
      "course": 180.0,
      "altitude": 150.0,
      "satellites": 12,
      "timestamp": "2024-01-15 14:30:00"
    },
    {
      "id": 2,
      "latitude": 55.752000,
      "longitude": 37.619000,
      "speed": 50.0,
      "course": 185.0,
      "altitude": 148.5,
      "satellites": 11,
      "timestamp": "2024-01-15 14:31:00"
    }
  ],
  "count": 2
}
```

### Получить конкретную запись трека

**Request:**
```bash
curl -X GET http://localhost:8000/api/vehicles/1/tracks/1
```

**Response (200 OK):**
```json
{
  "id": 1,
  "latitude": 55.751244,
  "longitude": 37.618423,
  "speed": 45.5,
  "course": 180.0,
  "altitude": 150.0,
  "satellites": 12,
  "timestamp": "2024-01-15 14:30:00",
  "additionalData": {
    "Latitude": 55.751244,
    "Longitude": 37.618423,
    "Speed": 45.5,
    "Course": 180.0,
    "Altitude": 150.0,
    "Satellites": 12,
    "Timestamp": "2024-01-15T14:30:00",
    "Hdop": 1.2,
    "Vdop": 1.5
  },
  "createdAt": "2024-01-15 14:30:05"
}
```

### Удалить запись трека

**Request:**
```bash
curl -X DELETE http://localhost:8000/api/vehicles/1/tracks/1
```

**Response (200 OK):**
```json
{
  "message": "Track deleted successfully"
}
```

## История команд (Commands)

### Получить историю команд транспортного средства

#### Последние 50 записей (по умолчанию)

**Request:**
```bash
curl -X GET http://localhost:8000/api/vehicles/1/commands
```

#### С параметрами фильтрации

**Request:**
```bash
# По диапазону дат
curl -X GET "http://localhost:8000/api/vehicles/1/commands?from=2024-01-01%2000:00:00&to=2024-01-31%2023:59:59"

# С ограничением количества
curl -X GET "http://localhost:8000/api/vehicles/1/commands?limit=20"
```

**Response (200 OK):**
```json
{
  "vehicle": {
    "id": 1,
    "name": "Камаз 001"
  },
  "commands": [
    {
      "id": 1,
      "commandType": "GET_LOCATION",
      "commandText": "WHERE?",
      "response": "LAT:55.751244,LON:37.618423,SPEED:45.5",
      "latitude": 55.751244,
      "longitude": 37.618423,
      "sentAt": "2024-01-15 14:30:00",
      "receivedAt": "2024-01-15 14:30:05",
      "status": "SUCCESS"
    },
    {
      "id": 2,
      "commandType": "GET_STATUS",
      "commandText": "STATUS?",
      "response": "OK,FUEL:75.5,ENGINE:ON",
      "latitude": 55.751244,
      "longitude": 37.618423,
      "sentAt": "2024-01-15 14:25:00",
      "receivedAt": "2024-01-15 14:25:03",
      "status": "SUCCESS"
    }
  ],
  "count": 2
}
```

### Получить конкретную команду

**Request:**
```bash
curl -X GET http://localhost:8000/api/vehicles/1/commands/1
```

**Response (200 OK):**
```json
{
  "id": 1,
  "commandType": "GET_LOCATION",
  "commandText": "WHERE?",
  "response": "LAT:55.751244,LON:37.618423,SPEED:45.5",
  "latitude": 55.751244,
  "longitude": 37.618423,
  "sentAt": "2024-01-15 14:30:00",
  "receivedAt": "2024-01-15 14:30:05",
  "status": "SUCCESS",
  "additionalData": {
    "Type": "GET_LOCATION",
    "Command": "WHERE?",
    "Response": "LAT:55.751244,LON:37.618423,SPEED:45.5",
    "Latitude": 55.751244,
    "Longitude": 37.618423,
    "SentAt": "2024-01-15T14:30:00",
    "ReceivedAt": "2024-01-15T14:30:05",
    "Status": "SUCCESS",
    "ExecutionTime": 5.2
  },
  "createdAt": "2024-01-15 14:30:05"
}
```

### Удалить запись команды

**Request:**
```bash
curl -X DELETE http://localhost:8000/api/vehicles/1/commands/1
```

**Response (200 OK):**
```json
{
  "message": "Command history deleted successfully"
}
```

## Коды ошибок

| HTTP Code | Описание |
|-----------|----------|
| 200 | OK - запрос выполнен успешно |
| 404 | Not Found - ресурс не найден |
| 500 | Internal Server Error - внутренняя ошибка сервера |

## Примеры с использованием различных инструментов

### HTTPie

```bash
# Список транспортных средств
http GET localhost:8000/api/vehicles

# Получить треки с фильтрацией
http GET localhost:8000/api/vehicles/1/tracks from=="2024-01-01 00:00:00" to=="2024-01-31 23:59:59"

# Удалить транспортное средство
http DELETE localhost:8000/api/vehicles/1
```

### JavaScript (Fetch API)

```javascript
// Получить список транспортных средств
fetch('http://localhost:8000/api/vehicles')
  .then(response => response.json())
  .then(data => console.log(data))
  .catch(error => console.error('Error:', error));

// Получить треки с фильтрацией
const params = new URLSearchParams({
  from: '2024-01-01 00:00:00',
  to: '2024-01-31 23:59:59',
  limit: 100
});

fetch(`http://localhost:8000/api/vehicles/1/tracks?${params}`)
  .then(response => response.json())
  .then(data => console.log(data))
  .catch(error => console.error('Error:', error));

// Удалить транспортное средство
fetch('http://localhost:8000/api/vehicles/1', {
  method: 'DELETE'
})
  .then(response => response.json())
  .then(data => console.log(data))
  .catch(error => console.error('Error:', error));
```

### Python (requests)

```python
import requests

# Получить список транспортных средств
response = requests.get('http://localhost:8000/api/vehicles')
vehicles = response.json()
print(vehicles)

# Получить треки с фильтрацией
params = {
    'from': '2024-01-01 00:00:00',
    'to': '2024-01-31 23:59:59',
    'limit': 100
}
response = requests.get('http://localhost:8000/api/vehicles/1/tracks', params=params)
tracks = response.json()
print(tracks)

# Удалить транспортное средство
response = requests.delete('http://localhost:8000/api/vehicles/1')
result = response.json()
print(result)
```

### PHP (Guzzle)

```php
<?php
use GuzzleHttp\Client;

$client = new Client(['base_uri' => 'http://localhost:8000/api/']);

// Получить список транспортных средств
$response = $client->get('vehicles');
$vehicles = json_decode($response->getBody(), true);
print_r($vehicles);

// Получить треки с фильтрацией
$response = $client->get('vehicles/1/tracks', [
    'query' => [
        'from' => '2024-01-01 00:00:00',
        'to' => '2024-01-31 23:59:59',
        'limit' => 100
    ]
]);
$tracks = json_decode($response->getBody(), true);
print_r($tracks);

// Удалить транспортное средство
$response = $client->delete('vehicles/1');
$result = json_decode($response->getBody(), true);
print_r($result);
```
