<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMaxBundle\Helper;

use Doctrine\ORM\EntityManagerInterface;
use MauticPlugin\MauticMaxBotsBundle\Helper\MaxApiHelper;
use MauticPlugin\MauticMaxBotsBundle\Helper\TokenCryptoHelper;
use Psr\Log\LoggerInterface;

final class MaxMessageHelper
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    public function sendTextToSubscription(int $botId, string $chatId, string $message): array
    {
        $bot = $this->entityManager->getConnection()->fetchAssociative(
            'SELECT token, api_base_url FROM max_bots WHERE id = :id AND is_published = 1 AND is_active = 1',
            ['id' => $botId]
        );

        if (!$bot || empty($bot['token'])) {
            return [
                'ok'          => false,
                'description' => sprintf('Published MAX bot %d was not found.', $botId),
            ];
        }

        $token = $this->getTokenCryptoHelper()->decryptIfNeeded((string) $bot['token']);
        if ('' === trim($token)) {
            return [
                'ok'          => false,
                'description' => 'MAX bot token is empty.',
            ];
        }

        return MaxApiHelper::sendTextMessage(
            $token,
            $chatId,
            $message,
            (string) ($bot['api_base_url'] ?? '')
        );
    }

    public function deactivateSubscription(int $botId, string $chatId): void
    {
        $this->entityManager->getConnection()->executeStatement(
            'UPDATE max_subscriptions
                SET is_active = 0,
                    is_blocked = 1,
                    unsubscribed_at = NOW(),
                    date_modified = NOW()
              WHERE bot_id = :botId AND chat_id = :chatId AND is_active = 1',
            [
                'botId'  => $botId,
                'chatId' => $chatId,
            ]
        );

        $this->logger->info('MAX subscription deactivated after failed delivery.', [
            'bot_id'  => $botId,
            'chat_id' => $chatId,
        ]);
    }

    public function isRecipientUnavailable(array $response): bool
    {
        $description = strtolower((string) ($response['description'] ?? $response['message'] ?? ''));

        if ('' === $description) {
            return false;
        }

        foreach (['blocked', 'forbidden', 'unavailable', 'not found', 'deactivated'] as $needle) {
            if (str_contains($description, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function getTokenCryptoHelper(): TokenCryptoHelper
    {
        return new TokenCryptoHelper($this->getSecretKey());
    }

    private function getSecretKey(): string
    {
        foreach (['MAUTIC_SECRET_KEY', 'APP_SECRET', 'SYMFONY_SECRET'] as $environmentVariable) {
            $secret = trim((string) getenv($environmentVariable));
            if ('' !== $secret) {
                return $secret;
            }
        }

        $bundleRoot = dirname(__DIR__);
        $candidateFiles = [
            dirname($bundleRoot, 1).'/config/local.php',
            dirname($bundleRoot, 2).'/config/local.php',
            dirname($bundleRoot, 3).'/config/local.php',
            dirname($bundleRoot, 1).'/app/config/local.php',
            dirname($bundleRoot, 2).'/app/config/local.php',
            dirname($bundleRoot, 3).'/app/config/local.php',
        ];

        foreach ($candidateFiles as $file) {
            if (!is_file($file)) {
                continue;
            }

            $parameters = [];
            require $file;

            $secret = (string) ($parameters['secret_key'] ?? '');
            if ('' !== $secret) {
                return $secret;
            }
        }

        throw new \RuntimeException('Mautic secret_key is required for MAX token encryption.');
    }
}
