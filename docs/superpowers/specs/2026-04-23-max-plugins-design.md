# MAX Plugins Design

Date: 2026-04-23
Status: Approved for planning

## Goal

Build MAX analogs of the existing Telegram plugins for Mautic using a foundation-first approach:

1. `MauticMaxBotsBundle` as the core bundle for bot management, webhook processing, subscriber lifecycle, and MAX API integration.
2. `MauticMaxBundle` as the campaign/action bundle for sending MAX messages through subscriptions managed by the bots bundle.

The target is a public-ready architecture suitable for reuse on other portals, without hardcoded portal-specific proxy or transport settings.

## Scope

This design covers:

- bundle boundaries and responsibilities;
- database entities and migrations for the bots bundle;
- webhook-first integration flow;
- admin UX for bot management;
- campaign action UX for sending MAX messages;
- security constraints;
- testing strategy;
- phased implementation order.

This design does not include:

- advanced scenario builders for callback automation beyond simple button handling;
- custom external SaaS control panels;
- Bitrix24-specific app packaging;
- non-webhook transport as a first-class flow.

## Architecture

### `MauticMaxBotsBundle`

Responsibilities:

- manage MAX bots inside Mautic;
- securely store bot tokens and endpoint configuration;
- register and validate webhooks;
- receive incoming MAX webhook events;
- create, update, reactivate, and deactivate subscriptions;
- run welcome flow, phone request flow, and post-contact flow;
- send bot-originated messages used by onboarding scenarios;
- expose reusable services for MAX message delivery to the campaign bundle;
- provide bot-level UI, status, and diagnostics.

Non-responsibilities:

- campaign action registration;
- campaign-specific timeline entries unrelated to MAX delivery;
- duplicate storage of subscriptions or delivery routing rules that belong in the send-message bundle.

### `MauticMaxBundle`

Responsibilities:

- register a Mautic campaign action such as `Send MAX Message`;
- resolve which active MAX subscriptions should receive a message;
- render a marketer-friendly action form;
- send text, media, and buttons through services exposed by `MauticMaxBotsBundle`;
- write timeline entries for send success, failure, and recipient availability status.

Non-responsibilities:

- storing bot credentials;
- receiving inbound webhooks;
- owning subscriber entities or webhook event entities;
- duplicating MAX API client logic.

## Data Model

### `max_bots`

Stores one configuration row per MAX bot.

Fields:

- `id`
- `name`
- `token`
- `username`
- `display_name`
- `is_published`
- `is_active`
- `welcome_message`
- `phone_request_message`
- `contact_success_message`
- `default_parse_mode`
- `send_welcome_on_subscribe`
- `request_phone_after_subscribe`
- `api_base_url`
- `webhook_base_url`
- `webhook_secret`
- `webhook_status`
- `last_webhook_error`
- `last_webhook_synced_at`
- `date_added`
- `created_by`
- `date_modified`
- `modified_by`

Notes:

- `token` must be stored encrypted at rest.
- `username` and `display_name` should be auto-populated from the MAX API when possible.
- base URLs are configurable for portability but strictly validated.

### `max_subscriptions`

Stores the relationship between a MAX chat and a Mautic contact for a specific bot.

Fields:

- `id`
- `bot_id`
- `lead_id`
- `chat_id`
- `max_user_id`
- `username`
- `first_name`
- `last_name`
- `language_code`
- `phone_number`
- `is_active`
- `is_blocked`
- `is_phone_confirmed`
- `subscribed_at`
- `unsubscribed_at`
- `last_interaction_at`
- `last_incoming_message_at`
- `last_outgoing_message_at`
- `last_delivery_status`
- `last_error_message`

Indexes and constraints:

- unique index on `(bot_id, chat_id)`;
- index on `lead_id`;
- index on `(bot_id, is_active)`.

Notes:

- the same Mautic contact may have subscriptions to multiple MAX bots;
- existing inactive subscriptions should be reactivated instead of creating duplicates.

### `max_incoming_events`

Stores raw inbound webhook events for diagnostics, idempotency, and later expansion.

Fields:

- `id`
- `bot_id`
- `subscription_id`
- `event_type`
- `event_id`
- `payload`
- `status`
- `processed_at`
- `error_message`
- `date_added`

Notes:

- payload can be stored as `longtext` with JSON content;
- this table is intentionally operational, not user-facing;
- it gives safe observability for webhook debugging and duplicate handling.

## Webhook-First Subscriber Lifecycle

### Registration

When an administrator saves or updates a bot:

1. the bundle generates or keeps a `webhook_secret`;
2. it builds a webhook endpoint like `/max/webhook/{webhookSecret}`;
3. it attempts to register that endpoint via MAX API;
4. it updates webhook status and stores the last synchronization error if registration fails.

### Inbound Processing

When MAX sends a webhook:

1. the controller resolves the bot by `webhook_secret` only;
2. it stores the raw event in `max_incoming_events`;
3. it determines the event type such as message, callback, contact, or service event;
4. it resolves or creates a subscription using `(bot_id, chat_id)`;
5. it updates profile fields like username, names, locale, and activity timestamps;
6. it invokes downstream contact binding or welcome-flow logic.

### Contact Binding

Recommended first-version behavior:

- create the subscription immediately when the user first interacts with the bot;
- do not create a full Mautic contact until a phone number is received;
- when a phone number arrives, try to find an existing contact by phone first;
- if none exists, create a new contact with the available data;
- mark `is_phone_confirmed = 1` once the phone is accepted and saved.

Rationale:

- this avoids creating large numbers of low-quality contacts from casual bot starts;
- it preserves subscriber state even before full identification.

### Welcome Flow

For first-time or reactivated subscribers:

- send `welcome_message` if enabled;
- if configured, send `phone_request_message` and trigger MAX contact request mechanics;
- when phone data is received and bound successfully, send `contact_success_message`.

### Duplicate Prevention

Duplicate protection relies on both code and schema:

- unique index on `(bot_id, chat_id)`;
- repeated starts reactivate the existing subscription instead of creating a new row;
- webhook processing must be idempotent when the same event is delivered more than once.

### Unsubscribe / Block / Unavailable Recipient

If MAX provides explicit unsubscribe signals, process them directly.

If outgoing sends return an unavailable, blocked, or forbidden style response:

- mark the subscription inactive;
- set `is_blocked` when appropriate;
- store the last error details;
- allow the campaign bundle to log a human-readable timeline event.

### Callback Buttons

Version one should support receiving callback button events and storing them in `max_incoming_events`.

Complex callback routing is out of scope for the first wave. The first implementation should focus on:

- storing and classifying callback payloads;
- leaving room for later expansion without redesigning the webhook pipeline.

## Admin UX for `MauticMaxBotsBundle`

The bot card should be usable by a non-technical administrator.

### Primary Section: Bot

Fields and controls:

- bot name;
- bot token;
- detected username and display name;
- published/active status;
- webhook state;
- `Check connection` action;
- `Refresh webhook` action.

Expected behavior:

- after saving a token, the system should try to fetch bot metadata automatically;
- users should not need to manually find numeric IDs if MAX API can provide them.

### Message Section

Fields:

- welcome message;
- toggle to request phone after subscribe;
- message shown before the phone request;
- message shown after successful phone capture.

### Buttons and Actions Section

Buttons should be configured visually, not through raw syntax.

Each button row should provide:

- button label;
- action type such as open URL, callback, request contact, or open app if supported;
- corresponding action value.

### Media Section

Fields:

- attachment type: none, image, file, video;
- media URL;
- caption.

### Subscriber Summary Section

Display:

- active subscriber count;
- blocked/inactive count;
- link to subscription list;
- recent delivery or webhook errors.

### Advanced Settings

Hidden or collapsed by default:

- API base URL;
- webhook base URL;
- webhook secret with regenerate option;
- low-level synchronization status.

### Contact Tokens in Textareas

Below text fields:

- provide a dropdown of contact tokens;
- when a token is selected, insert it automatically into the textarea;
- avoid browser autofill interference;
- do not require manual copy/paste from disappearing menus.

## Campaign UX for `MauticMaxBundle`

### Action Registration

Add a campaign action equivalent to `Send MAX Message`.

### Action Form

Fields:

- name;
- message text;
- contact token dropdown with automatic insertion into the message;
- attachment type;
- media URL;
- caption;
- visual buttons editor;
- bot selection mode.

### Bot Selection Modes

Supported choices:

- automatic: choose based on active subscription;
- specific bot;
- all active MAX subscriptions for the contact.

If multiple subscriptions are active, also provide:

- send to the first matching subscription;
- send to all matching subscriptions.

### Send Flow

On action execution:

1. resolve active subscriptions for the contact via `MauticMaxBotsBundle`;
2. choose one or more subscriptions based on action settings;
3. render contact tokens into the message;
4. build a MAX payload for text, media, and buttons;
5. send the message;
6. write timeline events with clear human-readable status.

### Timeline Events

At minimum:

- `MAX message sent: <bot name>`;
- `MAX send failed: <bot name>`;
- `Recipient unavailable in MAX`.

If MAX later supports reliable delivery/read statuses that fit this architecture, allow future extension for:

- delivered;
- read.

## Security Requirements

The MAX bundles should inherit the same stronger security posture we established for the Telegram plugins.

Mandatory rules:

- encrypt bot tokens at rest;
- never resolve bots by raw token in webhook URLs;
- validate `api_base_url` and `webhook_base_url`;
- allow only `https`;
- reject localhost, private IPs, reserved IP ranges, and credentials in URLs;
- avoid leaking secrets into UI errors, logs, or timeline messages;
- use standard Mautic permissions and CSRF protection for admin actions.

## Error Handling

### Webhook Errors

- store the event payload and mark the event row as error;
- avoid silent failures;
- do not expose stack traces to administrators.

### Send Errors

- record a clean timeline message for end users;
- retain technical detail internally for diagnostics;
- deactivate subscriptions when MAX clearly reports blocked or unavailable recipients;
- do not crash the entire campaign execution path for a single recipient failure.

## Testing Strategy

### Unit / Helper Tests

- MAX API URL building;
- custom base URL validation;
- token encryption and decryption;
- payload builders for text, media, and buttons.

### Webhook Tests

- resolve bot by webhook secret;
- create a new subscription;
- update an existing subscription without duplicates;
- process a payload containing phone data;
- reactivate an old inactive subscription.

### Campaign Action Tests

- resolve subscriptions for a contact;
- auto-select bot by subscription;
- send to all active subscriptions;
- deactivate subscription after blocked/unavailable send response;
- create timeline entries.

## Implementation Phases

### Phase 1: `MauticMaxBotsBundle` Foundation

- scaffold the bundle;
- create bot entity and migrations;
- implement encrypted token handling;
- implement MAX API helper;
- implement webhook registration;
- create subscription entity and migrations;
- add webhook controller and event persistence;
- implement welcome and phone flow;
- build bot list and bot card UI.

### Phase 2: `MauticMaxBotsBundle` UX Improvements

- visual button editor;
- media configuration UI;
- translations;
- subscriber list/status views;
- clearer admin-facing error messages.

### Phase 3: `MauticMaxBundle`

- register campaign action;
- build action form;
- implement contact token insertion;
- add timeline events;
- implement send error handling and subscription deactivation logic.

### Phase 4: Public-Ready Polish

- write README files;
- add distinct icons;
- confirm migration path;
- add broader tests;
- ensure no portal-specific transport assumptions remain.

## Recommended Delivery Strategy

Use the foundation-first approach:

1. deliver `MauticMaxBotsBundle` first as a stable core;
2. build `MauticMaxBundle` on top of its services and entities;
3. avoid designing both bundles in parallel in a way that duplicates MAX API integration or subscription ownership.

This keeps the data model clear, lowers rework risk, and mirrors the clean separation that already proved useful in the Telegram implementation.
