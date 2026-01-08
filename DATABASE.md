# Структура базы данных

## Обзор

Система управления инвентаризацией использует PostgreSQL/MySQL базу данных с Doctrine ORM. База данных состоит из 9 основных таблиц с нормализованной структурой.

## Диаграмма связей

```
┌─────────────┐     ┌─────────────┐
│   Category  │────▶│  Inventory │
└─────────────┘     └──────┬──────┘
                           │
                           ▼
┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│    User     │◀────│    Item     │────▶│    Like     │
└──────┬──────┘     └──────┬──────┘     └─────────────┘
       │                   │
       ▼                   ▼
┌─────────────┐     ┌─────────────┐
│InventoryAcc│     │  Comment    │
└─────────────┘     └─────────────┘
                           ▲
                           │
                    ┌─────────────┐
                    │    Tag      │
                    └──────┬─────┘
                           │
                    ┌─────────────┐
                    │inventory_tag│
                    └─────────────┘
```

## Таблицы

### 1. `users` - Пользователи

Основная таблица пользователей системы.

| Поле | Тип | Описание | Ограничения |
|------|-----|----------|-------------|
| `id` | SERIAL/BIGINT | Первичный ключ | AUTO_INCREMENT |
| `email` | VARCHAR(180) | Email пользователя | UNIQUE, NOT NULL |
| `username` | VARCHAR(255) | Имя пользователя | NULL |
| `roles` | JSON | Роли пользователя (ROLE_USER, ROLE_ADMIN) | NOT NULL |
| `password` | VARCHAR(255) | Хэш пароля | NULL (для соц. аутентификации) |
| `created_at` | DATETIME | Дата создания | NOT NULL |
| `updated_at` | DATETIME | Дата обновления | NULL |
| `is_blocked` | BOOLEAN | Флаг блокировки | DEFAULT FALSE |
| `google_id` | VARCHAR(255) | ID Google аккаунта | UNIQUE, NULL |
| `facebook_id` | VARCHAR(255) | ID Facebook аккаунта | UNIQUE, NULL |
| `avatar_url` | VARCHAR(500) | URL аватара | NULL |
| `email_verified_at` | DATETIME | Дата верификации email | NULL |
| `email_verification_token` | VARCHAR(255) | Токен верификации | UNIQUE, NULL |
| `password_reset_token` | VARCHAR(255) | Токен сброса пароля | NULL |
| `password_reset_token_expires_at` | DATETIME | Срок действия токена | NULL |

**Индексы:**
- PRIMARY KEY (`id`)
- UNIQUE KEY `UNIQ_IDENTIFIER_EMAIL` (`email`)
- UNIQUE KEY (`google_id`)
- UNIQUE KEY (`facebook_id`)
- UNIQUE KEY (`email_verification_token`)

### 2. `categories` - Категории инвентарей

Справочник категорий для классификации инвентарей.

| Поле | Тип | Описание | Ограничения |
|------|-----|----------|-------------|
| `id` | SERIAL/BIGINT | Первичный ключ | AUTO_INCREMENT |
| `name` | VARCHAR(255) | Название категории | NOT NULL |

**Индексы:**
- PRIMARY KEY (`id`)

**Предустановленные категории:**
- Equipment (Оборудование)
- Furniture (Мебель)
- Book (Книги)
- Other (Прочее)

### 3. `inventories` - Инвентари

Основная таблица инвентарей с большим количеством полей для кастомных настроек.

| Поле | Тип | Описание | Ограничения |
|------|-----|----------|-------------|
| `id` | SERIAL/BIGINT | Первичный ключ | AUTO_INCREMENT |
| `title` | VARCHAR(255) | Название инвентаря | NOT NULL |
| `description` | TEXT | Описание инвентаря | NULL |
| `image_url` | VARCHAR(500) | URL изображения | NULL |
| `creator_id` | BIGINT | ID создателя | NOT NULL, FK → users.id |
| `category_id` | BIGINT | ID категории | NOT NULL, FK → categories.id |
| `is_public` | BOOLEAN | Публичный доступ | DEFAULT FALSE |
| `version` | INT | Версия для optimistic locking | DEFAULT 1 |
| `created_at` | DATETIME | Дата создания | NOT NULL |
| `updated_at` | DATETIME | Дата обновления | NULL |
| `custom_id_format` | JSON | Формат кастомных ID | NULL |

**Поля кастомных настроек (15 типов × 3 поля = 45 полей):**

*Строковые поля (String):*
- `custom_string1_state` BOOLEAN - Включено ли поле
- `custom_string1_name` VARCHAR(255) - Название поля
- `custom_string1_description` TEXT - Описание поля
- `custom_string1_show_in_table` BOOLEAN - Показывать в таблице
- `custom_string1_min_length` INT - Минимальная длина
- `custom_string1_max_length` INT - Максимальная длина
- `custom_string1_regex` VARCHAR(500) - Регулярное выражение

*(Аналогично для custom_string2 и custom_string3)*

*Текстовые поля (Text):*
- `custom_text1_state` BOOLEAN
- `custom_text1_name` VARCHAR(255)
- `custom_text1_description` TEXT
- `custom_text1_show_in_table` BOOLEAN

*(Аналогично для custom_text2 и custom_text3)*

*Числовые поля (Integer):*
- `custom_int1_state` BOOLEAN
- `custom_int1_name` VARCHAR(255)
- `custom_int1_description` TEXT
- `custom_int1_show_in_table` BOOLEAN
- `custom_int1_min_value` INT - Минимальное значение
- `custom_int1_max_value` INT - Максимальное значение

*(Аналогично для custom_int2 и custom_int3)*

*Булевы поля (Boolean):*
- `custom_bool1_state` BOOLEAN
- `custom_bool1_name` VARCHAR(255)
- `custom_bool1_description` TEXT
- `custom_bool1_show_in_table` BOOLEAN

*(Аналогично для custom_bool2 и custom_bool3)*

*Поля ссылок (Link):*
- `custom_link1_state` BOOLEAN
- `custom_link1_name` VARCHAR(255)
- `custom_link1_description` TEXT
- `custom_link1_show_in_table` BOOLEAN

*(Аналогично для custom_link2 и custom_link3)*

**Индексы:**
- PRIMARY KEY (`id`)
- FOREIGN KEY (`creator_id`) REFERENCES `users` (`id`)
- FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`)

### 4. `items` - Элементы инвентарей

Таблица элементов с значениями кастомных полей.

| Поле | Тип | Описание | Ограничения |
|------|-----|----------|-------------|
| `id` | SERIAL/BIGINT | Первичный ключ | AUTO_INCREMENT |
| `inventory_id` | BIGINT | ID инвентаря | NOT NULL, FK → inventories.id |
| `custom_id` | VARCHAR(255) | Кастомный ID элемента | NOT NULL |
| `created_by` | BIGINT | ID создателя | NOT NULL, FK → users.id |
| `created_at` | DATETIME | Дата создания | NOT NULL |
| `updated_at` | DATETIME | Дата обновления | NULL |
| `version` | INT | Версия для optimistic locking | DEFAULT 1 |

**Значения кастомных полей (15 типов × 3 поля = 45 полей):**

*Строковые значения:*
- `custom_string1_value` VARCHAR(255)
- `custom_string2_value` VARCHAR(255)
- `custom_string3_value` VARCHAR(255)

*Текстовые значения:*
- `custom_text1_value` TEXT
- `custom_text2_value` TEXT
- `custom_text3_value` TEXT

*Числовые значения:*
- `custom_int1_value` BIGINT
- `custom_int2_value` BIGINT
- `custom_int3_value` BIGINT

*Булевы значения:*
- `custom_bool1_value` BOOLEAN
- `custom_bool2_value` BOOLEAN
- `custom_bool3_value` BOOLEAN

*Значения ссылок:*
- `custom_link1_value` VARCHAR(500)
- `custom_link2_value` VARCHAR(500)
- `custom_link3_value` VARCHAR(500)

**Индексы:**
- PRIMARY KEY (`id`)
- UNIQUE KEY `unique_inventory_custom_id` (`inventory_id`, `custom_id`)
- FOREIGN KEY (`inventory_id`) REFERENCES `inventories` (`id`) ON DELETE CASCADE
- FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)

### 5. `inventory_access` - Доступ к инвентарям

Таблица прав доступа пользователей к инвентарям.

| Поле | Тип | Описание | Ограничения |
|------|-----|----------|-------------|
| `id` | SERIAL/BIGINT | Первичный ключ | AUTO_INCREMENT |
| `inventory_id` | BIGINT | ID инвентаря | NOT NULL, FK → inventories.id |
| `user_id` | BIGINT | ID пользователя | NOT NULL, FK → users.id |
| `access_type` | VARCHAR(10) | Тип доступа ('write') | NOT NULL |

**Индексы:**
- PRIMARY KEY (`id`)
- UNIQUE KEY `unique_inventory_user` (`inventory_id`, `user_id`)
- FOREIGN KEY (`inventory_id`) REFERENCES `inventories` (`id`) ON DELETE CASCADE
- FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE

### 6. `comments` - Комментарии

Таблица комментариев к инвентарям.

| Поле | Тип | Описание | Ограничения |
|------|-----|----------|-------------|
| `id` | SERIAL/BIGINT | Первичный ключ | AUTO_INCREMENT |
| `inventory_id` | BIGINT | ID инвентаря | NOT NULL, FK → inventories.id |
| `user_id` | BIGINT | ID автора | NOT NULL, FK → users.id |
| `content` | TEXT | Содержимое комментария | NOT NULL |
| `created_at` | DATETIME | Дата создания | NOT NULL |
| `updated_at` | DATETIME | Дата обновления | NULL |

**Индексы:**
- PRIMARY KEY (`id`)
- FOREIGN KEY (`inventory_id`) REFERENCES `inventories` (`id`) ON DELETE CASCADE
- FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)

### 7. `likes` - Лайки

Таблица лайков элементов.

| Поле | Тип | Описание | Ограничения |
|------|-----|----------|-------------|
| `id` | SERIAL/BIGINT | Первичный ключ | AUTO_INCREMENT |
| `item_id` | BIGINT | ID элемента | NOT NULL, FK → items.id |
| `user_id` | BIGINT | ID пользователя | NOT NULL, FK → users.id |

**Индексы:**
- PRIMARY KEY (`id`)
- UNIQUE KEY `unique_user_item` (`user_id`, `item_id`)
- FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE
- FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE

### 8. `tags` - Теги

Справочник тегов для инвентарей.

| Поле | Тип | Описание | Ограничения |
|------|-----|----------|-------------|
| `id` | SERIAL/BIGINT | Первичный ключ | AUTO_INCREMENT |
| `name` | VARCHAR(255) | Название тега | UNIQUE, NOT NULL |

**Индексы:**
- PRIMARY KEY (`id`)
- UNIQUE KEY `unique_tag_name` (`name`)

### 9. `inventory_tags` - Связь инвентарей и тегов

Many-to-many таблица связи инвентарей и тегов.

| Поле | Тип | Описание | Ограничения |
|------|-----|----------|-------------|
| `inventory_id` | BIGINT | ID инвентаря | NOT NULL, FK → inventories.id |
| `tag_id` | BIGINT | ID тега | NOT NULL, FK → tags.id |

**Индексы:**
- PRIMARY KEY (`inventory_id`, `tag_id`)
- FOREIGN KEY (`inventory_id`) REFERENCES `inventories` (`id`) ON DELETE CASCADE
- FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE

### 10. `error_logs` - Логи ошибок

Таблица для логирования ошибок приложения.

| Поле | Тип | Описание | Ограничения |
|------|-----|----------|-------------|
| `id` | SERIAL/BIGINT | Первичный ключ | AUTO_INCREMENT |
| `error_id` | VARCHAR(36) | UUID ошибки | UNIQUE, NOT NULL |
| `message` | TEXT | Сообщение ошибки | NOT NULL |
| `trace` | TEXT | Стек-трейс | NULL |
| `url` | VARCHAR(255) | URL где произошла ошибка | NULL |
| `ip_address` | VARCHAR(45) | IP адрес пользователя | NULL |
| `user_id` | BIGINT | ID пользователя | NULL, FK → users.id |
| `created_at` | DATETIME | Дата ошибки | NOT NULL |
| `user_agent` | VARCHAR(255) | User-Agent браузера | NULL |

**Индексы:**
- PRIMARY KEY (`id`)
- UNIQUE KEY (`error_id`)
- FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)

## Особенности структуры

### Optimistic Locking
- Таблицы `inventories` и `items` имеют поле `version` для предотвращения конфликтов одновременного редактирования
- При обновлении проверяется версия и инкрементируется

### Кастомные поля
- Архитектура поддерживает до 3 полей каждого из 5 типов (15 полей всего)
- Типы: string, text, int, bool, link
- Каждое поле имеет настройки валидации и отображения

### Безопасность
- Все внешние ключи имеют правильные каскадные удаления
- Уникальные ограничения предотвращают дублирование данных
- Email и социальные ID уникальны

### Производительность
- Индексы на часто используемые поля фильтрации
- FULLTEXT индексы для поиска (в MySQL)
- Eager loading для связанных сущностей в Repository

## Миграции

База данных управляется через Doctrine migrations. Все изменения структуры фиксируются в файлах миграций в папке `migrations/`.

Последняя миграция: `Version20260105081505.php`
