# Mautic Telegram Campaign Messages

Mautic 7 plugin that adds a campaign action for sending Telegram messages to contacts.

## Features

- Send Telegram messages from Mautic campaigns.
- Use a specific bot, auto-select a bot from a contact subscription, or send through all subscribed bots.
- Insert contact field tokens such as `{contact.firstname}`, `{contact.email}`, and `{contact.mobile}`.
- Send text, photos, documents, videos, audio, voice messages, and animations by public file URL.
- Add simple inline buttons without writing Telegram API JSON by hand.
- Log sent and failed Telegram messages in the contact timeline.
- Optionally use a custom Telegram API proxy URL. By default, the official Telegram Bot API is used.

## Requirements

- Mautic 7.
- PHP extensions required by Mautic and cURL.
- A Telegram bot token from [@BotFather](https://t.me/BotFather).
- For subscription-aware sending, install `MauticTelegramBotsBundle` as well.

## Installation

Copy the bundle into your Mautic `plugins` directory:

```bash
cp -r MauticTelegramBundle /path/to/mautic/plugins/
cd /path/to/mautic
php bin/console mautic:plugins:reload
php bin/console cache:clear
```

Then open Mautic, go to plugins, enable Telegram, and save the integration settings.

## Configuration

The integration settings include:

- `Bot Token`: Telegram bot token. This can be left empty when `MauticTelegramBotsBundle` manages the bots and the campaign action auto-selects a subscribed bot.
- `Telegram API send URL`: optional. Leave empty to use `https://api.telegram.org`. Set this only if your installation must send Telegram requests through your own proxy.
- `Parse mode`: message formatting mode, usually `HTML`.

No project-specific proxy URL is bundled with the plugin.

## Campaign Usage

Add the campaign action `Send Telegram Message`.

Choose how the bot should be selected:

- `Auto`: use the contact's active Telegram subscriptions.
- `Selected bot only`: send through one chosen bot.
- `First subscribed bot only`: prevents duplicate messages when the contact follows several bots.
- `All subscribed bots`: sends the same message through every active subscription.

Use the token picker below the message field to insert contact variables into the message body.

## Attachments

For attachments, choose an attachment type and provide a public direct URL to the file, for example:

```text
https://example.com/photo.jpg
https://example.com/file.pdf
```

Telegram must be able to download the file directly.
