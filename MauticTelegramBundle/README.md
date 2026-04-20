# MauticTelegramBundle

Плагин для отправки сообщений через Telegram Bot API в кампаниях Mautic 7.

## Возможности

- ✅ Отправка текстовых сообщений с HTML/Markdown форматированием
- ✅ Отправка фото с подписью
- ✅ Отправка документов/файлов
- ✅ Inline кнопки (inline keyboard)
- ✅ Токены контакта в тексте сообщения ({firstname}, {email} и др.)
- ✅ Действие в кампании (автоматизации)
- ✅ Русский и английский интерфейс

## Установка

### 1. Скопировать плагин
```bash
cp -r MauticTelegramBundle /path/to/mautic/plugins/
```

### 2. Установить плагин
```bash
php bin/console mautic:plugins:install --env=prod
# или через UI: Настройки → Плагины → Установить/Обновить
```

### 3. Очистить кеш
```bash
php bin/console cache:clear --env=prod
```

## Настройка

### Шаг 1: Создать Telegram бота
1. Напишите @BotFather в Telegram
2. Создайте бота командой `/newbot`
3. Скопируйте Bot Token

### Шаг 2: Настроить плагин в Mautic
1. Перейдите: **Настройки → Плагины → Telegram**
2. Включите плагин
3. Введите **Bot Token**
4. Выберите **режим форматирования** (HTML рекомендуется)
5. Сохраните

### Шаг 3: Создать кастомное поле контакта
1. Перейдите: **Настройки → Поля контактов**
2. Создайте поле с alias: `telegram_chat_id`
3. Тип: Text

### Шаг 4: Заполнить chat_id контактов
Есть два способа:
- **Вручную**: Отредактировать контакт и вписать chat_id
- **Через бота**: Настроить webhook бота, который при `/start` сохраняет chat_id в Mautic через API

## Использование в кампаниях

1. Создайте или откройте кампанию
2. Добавьте действие **"Отправить сообщение в Telegram"**
3. Заполните поля:

### Поле "Сообщение"
Поддерживает HTML форматирование и токены:
```
Привет, {firstname}!

Ваш email: {email}

<b>Жирный текст</b>
<i>Курсив</i>
<a href="https://example.com">Ссылка</a>
```

### Поле "Кнопки"
Одна кнопка на строку в формате `Текст|URL`:
```
Перейти на сайт|https://yogavedi.ru
Записаться|https://yogavedi.ru/signup
```

### Медиа
- Выберите тип: Фото или Документ
- Укажите публичный URL файла

## Получение chat_id

Когда пользователь напишет вашему боту `/start`, Telegram пришлёт update с `message.chat.id` — это и есть chat_id.

Пример webhook обработчика для получения chat_id:
```
GET /mautic/api/contacts?search=email:user@example.com
PATCH /mautic/api/contacts/{id}/edit
{"telegram_chat_id": "123456789"}
```

## Структура файлов

```
MauticTelegramBundle/
├── Config/
│   └── config.php                    # Конфигурация плагина
├── EventListener/
│   └── CampaignSubscriber.php        # Обработчик действий кампании
├── Form/
│   └── Type/
│       └── TelegramSendMessageType.php # Форма настройки действия
├── Helper/
│   └── TelegramApiHelper.php         # Работа с Telegram Bot API
├── Integration/
│   └── TelegramIntegration.php       # Настройки интеграции
├── translations/
│   ├── messages.en_US.xlf            # Английский перевод
│   └── messages.ru_RU.xlf            # Русский перевод
├── MauticTelegramBundle.php          # Главный класс плагина
└── README.md                         # Документация
```
