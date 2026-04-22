<?php

declare(strict_types=1);

namespace MauticPlugin\MauticTelegramBotsBundle\Controller;

use Mautic\CoreBundle\Controller\CommonController;
use MauticPlugin\MauticTelegramBotsBundle\Entity\Bot;
use MauticPlugin\MauticTelegramBotsBundle\Entity\BotRepository;
use MauticPlugin\MauticTelegramBotsBundle\Helper\ContactManager;
use MauticPlugin\MauticTelegramBotsBundle\Helper\TelegramBotApiHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\ORM\EntityManagerInterface;

class WebhookController extends CommonController
{
    public function __construct(
        private EntityManagerInterface $em,
        private TelegramBotApiHelper $apiHelper,
        private ContactManager $contactManager,
    ) {
    }

    public function handleAction(Request $request, string $token): Response
    {
        // РќР°С…РѕРґРёРј Р±РѕС‚Р° РїРѕ С‚РѕРєРµРЅСѓ
        /** @var BotRepository $repository */
        $repository = $this->em->getRepository(Bot::class);
        $bot = $repository->findByToken($token);

        if (!$bot) {
            return new Response('Not found', 404);
        }

        $input  = $request->getContent();
        $update = json_decode($input, true);

        if (!$update) {
            return new Response('OK');
        }

        // РЎРЅР°С‡Р°Р»Р° РІРѕР·РІСЂР°С‰Р°РµРј OK С‡С‚РѕР±С‹ Telegram РЅРµ Р¶РґР°Р»
        if (isset($update['message'])) {
            $message = $update['message'];
            $botRef = $bot;
            // РСЃРїРѕР»СЊР·СѓРµРј shutdown function РґР»СЏ РѕР±СЂР°Р±РѕС‚РєРё РїРѕСЃР»Рµ РѕС‚РІРµС‚Р° Telegram
            register_shutdown_function(function() use ($message, $botRef) {
                $this->handleMessage($message, $botRef);
            });
        }

        return new Response('OK');
    }

    private function handleMessage(array $message, Bot $bot): void
    {
        $chatId  = $message['chat']['id'];
        $from    = $message['from'] ?? [];
        $text    = $message['text'] ?? '';
        $contact = $message['contact'] ?? null;

        if ($contact) {
            $this->handleContactShared($chatId, $from, $contact, $bot);
            return;
        }

        if (str_starts_with($text, '/start')) {
            $this->handleStart($chatId, $from, $bot);
        }
    }

    private function handleStart(int $chatId, array $from, Bot $bot): void
    {
        $firstName = $from['first_name'] ?? '';

        // РџР•Р Р•Р”РђР•Рњ $bot->getId() РґР»СЏ СЂРµРіРёСЃС‚СЂР°С†РёРё РїРѕРґРїРёСЃРєРё!
        $this->contactManager->createOrUpdate([
            'chat_id'    => $chatId,
            'username'   => $from['username'] ?? '',
            'first_name' => $firstName,
            'last_name'  => $from['last_name'] ?? '',
        ], $bot->getId(), $bot->getTagsArray());

        // Р’РјРµСЃС‚Рѕ СЃС‚Р°СЂРѕРіРѕ РёРЅРєСЂРµРјРµРЅС‚Р° РёСЃРїРѕР»СЊР·СѓРµРј СЂРµРїРѕР·РёС‚РѕСЂРёР№ РґР»СЏ РґРёРЅР°РјРёС‡РµСЃРєРѕРіРѕ РїРѕРґСЃС‡РµС‚Р°
        // (С…РѕС‚СЏ РјС‹ СѓР¶Рµ СЂРµС€РёР»Рё, С‡С‚Рѕ РІ С€Р°Р±Р»РѕРЅРµ Р±СѓРґРµРј Р±СЂР°С‚СЊ РґРёРЅР°РјРёС‡РµСЃРєРѕРµ Р·РЅР°С‡РµРЅРёРµ,
        // РЅРѕ РґР»СЏ РЅР°РґРµР¶РЅРѕСЃС‚Рё РјРѕР¶РµРј РѕР±РЅРѕРІРёС‚СЊ РїРѕР»Рµ, РµСЃР»Рё Р·Р°С…РѕС‚РёС‚Рµ РµРіРѕ РІРµСЂРЅСѓС‚СЊ)

        $welcomeText = $bot->getWelcomeMessage();
        if (empty($welcomeText)) {
            $welcomeText = "РџСЂРёРІРµС‚, <b>{$firstName}</b>! рџ‘‹\n\nР’С‹ СѓСЃРїРµС€РЅРѕ РїРѕРґРїРёСЃР°Р»РёСЃСЊ.";
        } else {
            $welcomeText = str_replace(['{first_name}', '{firstname}'], $firstName, $welcomeText);
        }

        if ($bot->isAskPhone()) {
            $askText = $bot->getAskPhoneMessage() ?: 'РџРѕРґРµР»РёС‚РµСЃСЊ РЅРѕРјРµСЂРѕРј С‚РµР»РµС„РѕРЅР°:';
            $this->apiHelper->sendMessage($bot->getToken(), $chatId, $welcomeText);
            $this->apiHelper->sendMessage($bot->getToken(), $chatId, $askText, $this->apiHelper->buildContactKeyboard());
        } else {
            $this->apiHelper->sendMessage($bot->getToken(), $chatId, $welcomeText);
        }
    }

    private function handleContactShared(int $chatId, array $from, array $contact, Bot $bot): void
    {
        $phone = preg_replace('/[^0-9+]/', '', $contact['phone_number'] ?? '');

        // РџР•Р Р•Р”РђР•Рњ $bot->getId() РґР»СЏ СЂРµРіРёСЃС‚СЂР°С†РёРё РїРѕРґРїРёСЃРєРё!
        $this->contactManager->createOrUpdate([
            'chat_id'    => $chatId,
            'username'   => $from['username'] ?? '',
            'first_name' => $contact['first_name'] ?? $from['first_name'] ?? '',
            'last_name'  => $contact['last_name'] ?? $from['last_name'] ?? '',
            'phone'      => $phone,
        ], $bot->getId(), $bot->getTagsArray());

        $this->apiHelper->sendMessage($bot->getToken(), $chatId, 'вњ… РЎРїР°СЃРёР±Рѕ! Р’Р°С€Рё РґР°РЅРЅС‹Рµ СЃРѕС…СЂР°РЅРµРЅС‹.', $this->apiHelper->removeKeyboard());
    }
}
