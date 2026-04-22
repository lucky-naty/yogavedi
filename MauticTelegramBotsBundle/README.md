# Mautic Telegram Bots

Mautic 7 plugin for managing Telegram bots and storing Telegram subscribers as Mautic contacts.

## Features

- Register Telegram bots in Mautic.
- Store bot tokens without showing them in the edit form.
- Register Telegram webhooks with a generated secret URL.
- Create or update contacts when users start a bot.
- Store Telegram chat ID and username on the contact.
- Prevent duplicate active subscriptions for the same bot and chat.
- Detect blocked users when Telegram returns a blocked-bot response.
- Count active subscribers per bot.
- Configure optional custom send and receive URLs for installations that use their own Telegram proxy.

## Requirements

- Mautic 7.
- PHP extensions required by Mautic and cURL.
- A Telegram bot token from [@BotFather](https://t.me/BotFather).

## Installation

Copy the bundle into your Mautic `plugins` directory:

```bash
cp -r MauticTelegramBotsBundle /path/to/mautic/plugins/
cd /path/to/mautic
php bin/console mautic:plugins:reload
php bin/console cache:clear
```

Then open Mautic and go to `Channels > Telegram Bots`.

## Bot Settings

- `Bot name`: internal name shown in Mautic.
- `Bot token`: Telegram bot token. Existing tokens are preserved when the field is left empty while editing.
- `Telegram API send URL`: optional. Leave empty to use `https://api.telegram.org`. Set this only when outgoing Telegram API requests must go through your own proxy.
- `Public webhook receive URL`: optional. Leave empty to use the current Mautic domain. Set this only when Telegram must send incoming webhook updates to a public proxy or another external URL.
- `Welcome message`: message sent after `/start`.
- `Ask for phone number`: sends a Telegram contact-request button.
- `Tags to add`: comma-separated tags added to created or updated contacts.

No project-specific proxy URL is bundled with the plugin.

## Webhook

After saving a bot, use the webhook registration action in Mautic. The plugin stores a generated secret URL and registers it with Telegram.

If your Mautic instance is behind a reverse proxy, set `Public webhook receive URL` to the public URL that Telegram can reach.
