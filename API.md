# API Documentation

## Обзор

Система управления инвентаризацией предоставляет REST API для программного взаимодействия с данными. Все API endpoints требуют аутентификации, за исключением публичных инвентарей.

## Базовая информация

- **Базовый URL**: `https://your-domain.com`
- **Формат данных**: JSON
- **Аутентификация**: HTTP Basic Auth или Bearer Token (через Symfony Security)
- **Кодировка**: UTF-8
- **Rate Limiting**: Не применяется

## Аутентификация

Большинство API endpoints требуют аутентификации пользователя. Используйте стандартную Symfony аутентификацию через сессии или API токены.

### Заголовки запросов

```http
Content-Type: application/json
Accept: application/json
```

## Endpoints

### Инвентари (Inventory)

#### Получение статистики инвентаря

```http
GET /api/{id}/statistics
```

**Параметры:**
- `id` (path): ID инвентаря

**Права доступа:**
- Публичные инвентари: все пользователи
- Приватные инвентари: создатель, администраторы, пользователи с доступом

**Ответ (200 OK):**
```json
{
  "total_items": 25,
  "custom_fields_stats": {
    "custom_string1": {
      "total_values": 20,
      "unique_values": 15,
      "most_common": "Value A"
    },
    "custom_int1": {
      "min": 10,
      "max": 100,
      "avg": 45.5,
      "total_values": 18
    }
  }
}
```

#### Получение списка тегов

```http
GET /api/tags
```

**Параметры запроса:**
- `q` (string, опционально): Поисковый запрос
- `limit` (int, опционально): Максимальное количество результатов (по умолчанию 10)

**Права доступа:** Аутентифицированные пользователи

**Ответ (200 OK):**
```json
["tag1", "tag2", "tag3"]
```

#### Управление доступом к инвентарю

##### Получение списка пользователей с доступом

```http
GET /api/{id}/users
```

**Параметры:**
- `id` (path): ID инвентаря

**Права доступа:** Создатель инвентаря или администратор

**Ответ (200 OK):**
```json
[
  {
    "id": 1,
    "username": "user@example.com",
    "email": "user@example.com",
    "display_name": "user@example.com"
  }
]
```

##### Получение списка доступа

```http
GET /api/{id}/access-list
```

**Параметры:**
- `id` (path): ID инвентаря

**Права доступа:** Создатель инвентаря или администратор

**Ответ (200 OK):**
```json
[
  {
    "id": 1,
    "user": {
      "id": 2,
      "username": "john_doe",
      "email": "john@example.com"
    },
    "access_type": "write"
  }
]
```

##### Добавление доступа пользователю

```http
POST /api/{id}/access
```

**Параметры:**
- `id` (path): ID инвентаря

**Тело запроса:**
```json
{
  "username_or_email": "user@example.com"
}
```

**Права доступа:** Создатель инвентаря или администратор

**Ответ (200 OK):**
```json
{
  "success": true,
  "message": "Access granted"
}
```

##### Удаление доступа пользователя

```http
DELETE /api/{id}/access
```

**Параметры:**
- `id` (path): ID инвентаря

**Тело запроса:**
```json
{
  "user_id": 2
}
```

**Права доступа:** Создатель инвентаря или администратор

**Ответ (200 OK):**
```json
{
  "success": true
}
```

##### Установка публичного доступа

```http
POST /api/{id}/public-access
```

**Параметры:**
- `id` (path): ID инвентаря

**Тело запроса:**
```json
{
  "is_public": true
}
```

**Права доступа:** Создатель инвентаря или администратор

**Ответ (200 OK):**
```json
{
  "success": true
}
```

#### Комментарии к инвентарю

##### Получение комментариев

```http
GET /api/{id}/comments
```

**Параметры:**
- `id` (path): ID инвентаря

**Права доступа:**
- Публичные инвентари: все пользователи
- Приватные инвентари: создатель, администраторы, пользователи с доступом

**Ответ (200 OK):**
```json
{
  "success": true,
  "comments": [
    {
      "id": 1,
      "content": "Комментарий в Markdown",
      "content_html": "<p>Комментарий в Markdown</p>",
      "created_at": "2024-01-01 12:00:00",
      "updated_at": "2024-01-01 12:00:00",
      "user": {
        "id": 1,
        "username": "john_doe",
        "email": "john@example.com",
        "display_name": "john_doe"
      },
      "can_delete": true
    }
  ]
}
```

##### Добавление комментария

```http
POST /api/{id}/comments
```

**Параметры:**
- `id` (path): ID инвентаря

**Тело запроса:**
```json
{
  "content": "Текст комментария в Markdown"
}
```

**Права доступа:**
- Пользователи с подтвержденным email
- Права доступа к инвентарю (публичный или с доступом)

**Ответ (200 OK):**
```json
{
  "success": true,
  "comment": {
    "id": 2,
    "content": "Текст комментария в Markdown",
    "content_html": "<p>Текст комментария в Markdown</p>",
    "created_at": "2024-01-01 12:05:00",
    "user": {
      "id": 1,
      "username": "john_doe",
      "email": "john@example.com",
      "display_name": "john_doe"
    },
    "can_delete": true
  }
}
```

##### Удаление комментария

```http
DELETE /api/comments/{id}
```

**Параметры:**
- `id` (path): ID комментария

**Права доступа:** Автор комментария или администратор

**Ответ (200 OK):**
```json
{
  "success": true
}
```

#### Удаление инвентаря

```http
DELETE /api/{id}
```

**Параметры:**
- `id` (path): ID инвентаря

**Права доступа:** Создатель инвентаря или администратор

**Ответ (200 OK):**
```json
{
  "success": true,
  "message": "Inventory deleted successfully"
}
```

#### Массовое удаление инвентарей

```http
DELETE /api/batch-delete
```

**Тело запроса:**
```json
{
  "ids": [1, 2, 3]
}
```

**Права доступа:** Создатели соответствующих инвентарей или администратор

**Ответ (200 OK):**
```json
{
  "success": true,
  "deleted_count": 3
}
```

### Элементы (Items)

#### Получение списка элементов

```http
GET /api/items
```

**Параметры запроса:**
- `inventory_id` (int): ID инвентаря
- `page` (int, опционально): Номер страницы (по умолчанию 1)
- `limit` (int, опционально): Количество элементов на страницу (по умолчанию 50)
- `sort_by` (string, опционально): Поле сортировки
- `sort_order` (string, опционально): Направление сортировки (asc/desc)
- `filters` (json, опционально): Фильтры в формате JSON

**Права доступа:** Права доступа к инвентарю

**Ответ (200 OK):**
```json
{
  "items": [
    {
      "id": 1,
      "inventory_id": 1,
      "custom_id": "ITEM-001",
      "custom_string1_value": "Значение поля",
      "custom_int1_value": 42,
      "created_by": {
        "id": 1,
        "username": "john_doe"
      },
      "created_at": "2024-01-01 10:00:00",
      "updated_at": "2024-01-01 10:00:00",
      "likes_count": 5
    }
  ],
  "total": 25,
  "page": 1,
  "limit": 50,
  "total_pages": 1
}
```

#### Создание элемента

```http
POST /api/create
```

**Тело запроса:**
```json
{
  "inventory_id": 1,
  "custom_string1_value": "Значение",
  "custom_int1_value": 100,
  "custom_bool1_value": true
}
```

**Права доступа:** Права на запись в инвентаре

**Ответ (201 Created):**
```json
{
  "success": true,
  "item": {
    "id": 26,
    "inventory_id": 1,
    "custom_id": "AUTO-0026",
    "custom_string1_value": "Значение",
    "custom_int1_value": 100,
    "created_by": {
      "id": 1,
      "username": "john_doe"
    },
    "created_at": "2024-01-01 12:00:00"
  }
}
```

#### Обновление элемента

```http
PUT /api/{id}
```

**Параметры:**
- `id` (path): ID элемента

**Тело запроса:**
```json
{
  "custom_string1_value": "Новое значение",
  "custom_int1_value": 150,
  "version": 1
}
```

**Права доступа:** Права на запись в инвентаре

**Ответ (200 OK):**
```json
{
  "success": true,
  "item": {
    "id": 1,
    "inventory_id": 1,
    "custom_id": "ITEM-001",
    "custom_string1_value": "Новое значение",
    "updated_at": "2024-01-01 12:05:00",
    "version": 2
  }
}
```

#### Удаление элемента

```http
DELETE /api/{id}
```

**Параметры:**
- `id` (path): ID элемента

**Права доступа:** Права на запись в инвентаре

**Ответ (200 OK):**
```json
{
  "success": true
}
```

#### Массовое удаление элементов

```http
DELETE /api/batch-delete
```

**Тело запроса:**
```json
{
  "ids": [1, 2, 3]
}
```

**Права доступа:** Права на запись в инвентаре

**Ответ (200 OK):**
```json
{
  "success": true,
  "deleted_count": 3
}
```

#### Лайки элементов

##### Добавление/удаление лайка

```http
POST /api/{id}/like
```

**Параметры:**
- `id` (path): ID элемента

**Права доступа:** Пользователи с подтвержденным email

**Ответ (200 OK):**
```json
{
  "success": true,
  "liked": true,
  "likes_count": 6,
  "message": "Лайк добавлен"
}
```

##### Получение статуса лайка

```http
GET /api/{id}/like/status
```

**Параметры:**
- `id` (path): ID элемента

**Права доступа:** Аутентифицированные пользователи

**Ответ (200 OK):**
```json
{
  "liked": true,
  "likes_count": 6
}
```

### Поиск

#### Поисковые подсказки

```http
GET /api/search/suggestions
```

**Параметры запроса:**
- `q` (string): Поисковый запрос (минимум 2 символа)

**Ответ (200 OK):**
```json
[
  {
    "type": "inventory",
    "title": "Офисное оборудование",
    "description": "Инвентарь офисной техники",
    "url": "/inventory/1",
    "creator": "john_doe"
  }
]
```

#### Поиск тегов

```http
GET /api/tags/search
```

**Параметры запроса:**
- `q` (string): Поисковый запрос (минимум 2 символа)

**Ответ (200 OK):**
```json
[
  {
    "id": 1,
    "name": "оборудование",
    "inventory_count": 5
  }
]
```

## Коды ответов

### Успешные ответы

- `200 OK` - Запрос выполнен успешно
- `201 Created` - Ресурс создан

### Ошибки клиента

- `400 Bad Request` - Неверные параметры запроса
- `401 Unauthorized` - Требуется аутентификация
- `403 Forbidden` - Недостаточно прав доступа
- `404 Not Found` - Ресурс не найден
- `422 Unprocessable Entity` - Ошибка валидации данных

### Ошибки сервера

- `500 Internal Server Error` - Внутренняя ошибка сервера

## Формат ошибок

```json
{
  "error": "Описание ошибки",
  "code": 400,
  "details": {
    "field": "custom_id",
    "message": "Значение уже существует"
  }
}
```
## Примеры использования

### JavaScript (Fetch API)

```javascript
// Получение комментариев инвентаря
fetch('/api/1/comments', {
  method: 'GET',
  headers: {
    'Accept': 'application/json'
  }
})
.then(response => response.json())
.then(data => console.log(data));
```

### PHP (Symfony HttpClient)

```php
use Symfony\Component\HttpClient\HttpClient;

$client = HttpClient::create();
$response = $client->request('GET', '/api/1/statistics');
$data = $response->toArray();
```

### cURL

```bash
# Получение статистики инвентаря
curl -X GET "https://your-domain.com/api/1/statistics" \
  -H "Accept: application/json" \
  -H "Cookie: PHPSESSID=your_session_id"
```
