# Mautic MAX Bundle

This bundle sends MAX messages from Mautic campaigns.

Current scope:
- campaign action for text sending
- timeline logging for sent and failed MAX messages
- depends on `MauticMaxBotsBundle` for bot storage, webhook handling, and active subscriptions

## Current purpose

The bundle now provides a campaign action for sending text messages through active MAX subscriptions.
It uses `MauticMaxBotsBundle` as the source of truth for bots and subscriptions.

## Dependency

Install this bundle together with `MauticMaxBotsBundle`.
The bots bundle owns:
- encrypted MAX bot tokens
- validated MAX API/webhook base URLs
- webhook-first subscription lifecycle
- welcome and phone-request flow

## Campaign UX

The send-message campaign form already follows the updated Telegram behavior:

1. The user first chooses `send_scope`.
2. The specific bot dropdown appears only when `send_scope = selected_only`.
3. Auto-routing and multi-subscription delivery stay available without forcing the user to pick a bot manually.

This is intentional and should stay aligned between the Telegram and MAX campaign bundles.

## Next phase

Planned follow-up work:
- richer media and button sending from campaign actions
- delivery and read tracking if MAX exposes it reliably
- more advanced per-bot routing rules
