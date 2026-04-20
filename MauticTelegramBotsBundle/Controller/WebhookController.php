<?php

declare(strict_types=1);

namespace MauticPlugin\MauticTelegramBotsBundle\Controller;

use Mautic\CoreBundle\Controller\CommonController;
use MauticPlugin\MauticTelegramBotsBundle\Entity\Bot;
use MauticPlugin\MauticTelegramBotsBundle\Helper\ContactManager;
use MauticPlugin\MauticTelegramBotsBundle\Helper\TelegramBotApiHelper;
use MauticPlugin\MauticTelegramBotsBundle\Repository\BotRepository;
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
        // Находим бота по токену
        $bot = $this->em->getRepository(Bot::class)->findOneBy([
            'token'       => $token,
            'isPublished' => true,
        ]);

        if (!$bot) {
            return new Response('Not found', 404);
        }

        $input  = $request->getContent();
        $update = json_decode($input, true);

        if (!$update) {
            return new Response('OK');
        }

        // Сначала возвращаем OK чтобы Telegram не ждал
        if (isset($update['message'])) {
            // Регистрируем shutdown function - выполнится после отправки ответа
            $message = $update['message'];
            $botRef = $bot;
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

        $this->contactManager->createOrUpdate([
            'chat_id'    => $chatId,
            'username'   => $from['username'] ?? '',
            'first_name' => $firstName,
            'last_name'  => $from['last_name'] ?? '',
        ], $bot->getTagsArray());

        $bot->incrementSubscribersCount();
        $this->em->flush();

        $welcomeText = $bot->getWelcomeMessage();
        if (empty($welcomeText)) {
            $welcomeText = "Привет, <b>{$firstName}</b>! 👋\n\nВы успешно подписались.";
        } else {
            $welcomeText = str_replace(['{first_name}', '{firstname}'], $firstName, $welcomeText);
        }

        if ($bot->isAskPhone()) {
            $askText = $bot->getAskPhoneMessage() ?: 'Поделитесь номером телефона:';
            $this->apiHelper->sendMessage($bot->getToken(), $chatId, $welcomeText);
            $this->apiHelper->sendMessage($bot->getToken(), $chatId, $askText, $this->apiHelper->buildContactKeyboard());
        } else {
            $this->apiHelper->sendMessage($bot->getToken(), $chatId, $welcomeText);
        }
    }

    private function handleContactShared(int $chatId, array $from, array $contact, Bot $bot): void
    {
        $phone = preg_replace('/[^0-9+]/', '', $contact['phone_number'] ?? '');

        $this->contactManager->createOrUpdate([
            'chat_id'    => $chatId,
            'username'   => $from['username'] ?? '',
            'first_name' => $contact['first_name'] ?? $from['first_name'] ?? '',
            'last_name'  => $contact['last_name'] ?? $from['last_name'] ?? '',
            'phone'      => $phone,
        ], $bot->getTagsArray());

        $this->apiHelper->sendMessage($bot->getToken(), $chatId, '✅ Спасибо! Ваши данные сохранены.', $this->apiHelper->removeKeyboard());
    }
}
