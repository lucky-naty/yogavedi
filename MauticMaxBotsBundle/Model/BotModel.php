<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMaxBotsBundle\Model;

use Mautic\CoreBundle\Model\FormModel;
use MauticPlugin\MauticMaxBotsBundle\Entity\Bot;
use MauticPlugin\MauticMaxBotsBundle\Entity\BotRepository;
use MauticPlugin\MauticMaxBotsBundle\Helper\MaxApiHelper;
use MauticPlugin\MauticMaxBotsBundle\Helper\TokenCryptoHelper;

class BotModel extends FormModel
{
    public function getRepository(): BotRepository
    {
        /** @var BotRepository $repository */
        $repository = $this->em->getRepository(Bot::class);

        return $repository;
    }

    public function getPermissionBase(): string
    {
        return 'plugin:mauticMaxBots:bots';
    }

    public function saveEntity($entity, $unlock = true): void
    {
        if (!$entity instanceof Bot) {
            $this->em->persist($entity);
            $this->em->flush();

            return;
        }

        $this->validateApiBaseUrl($entity->getApiBaseUrl());
        $this->validateWebhookBaseUrl($entity->getWebhookBaseUrl());

        if ('' === trim($entity->getWebhookSecret())) {
            $entity->setWebhookSecret(bin2hex(random_bytes(32)));
        }

        if ($entity->getId() && '' === trim($entity->getToken())) {
            $existingToken = $this->em->getConnection()->fetchOne(
                'SELECT token FROM max_bots WHERE id = :id',
                ['id' => $entity->getId()]
            );

            if (is_string($existingToken) && '' !== trim($existingToken)) {
                $entity->setToken($existingToken);
            }
        }

        $entity->setToken($this->getTokenCryptoHelper()->encryptIfNeeded($entity->getToken()));

        $this->em->persist($entity);
        $this->em->flush();
    }

    public function checkConnection(Bot $bot): array
    {
        try {
            $token = $this->getTokenCryptoHelper()->decryptIfNeeded($bot->getToken());
            if ('' === trim($token)) {
                return [
                    'ok'          => false,
                    'description' => 'MAX token is empty.',
                ];
            }

            $this->validateApiBaseUrl($bot->getApiBaseUrl());

            return MaxApiHelper::getMe($token, $bot->getApiBaseUrl());
        } catch (\Throwable $exception) {
            return [
                'ok'          => false,
                'description' => $exception->getMessage(),
            ];
        }
    }

    public function registerWebhook(Bot $bot): array
    {
        try {
            $token = $this->getTokenCryptoHelper()->decryptIfNeeded($bot->getToken());
            if ('' === trim($token)) {
                return [
                    'ok'          => false,
                    'description' => 'MAX token is empty.',
                ];
            }

            $webhookUrl = $this->buildWebhookUrl($bot);

            return MaxApiHelper::registerWebhook($token, $webhookUrl, $bot->getWebhookSecret(), $bot->getApiBaseUrl());
        } catch (\Throwable $exception) {
            return [
                'ok'          => false,
                'description' => $exception->getMessage(),
            ];
        }
    }

    public function buildWebhookUrl(Bot $bot): string
    {
        $baseUrl = $this->validateWebhookBaseUrl($bot->getWebhookBaseUrl());
        $secret = trim($bot->getWebhookSecret());

        if ('' === $secret) {
            throw new \InvalidArgumentException('MAX webhook secret is required.');
        }

        return $baseUrl.'/max/webhook/'.rawurlencode($secret);
    }

    private function validateApiBaseUrl(string $url): string
    {
        $url = trim($url);
        if ('' === $url) {
            throw new \InvalidArgumentException('MAX API base URL is required.');
        }

        return MaxApiHelper::validateBaseUrlForTest($url);
    }

    private function validateWebhookBaseUrl(string $url): string
    {
        $url = trim($url);
        if ('' === $url) {
            throw new \InvalidArgumentException('MAX webhook base URL is required.');
        }

        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            throw new \InvalidArgumentException('Invalid MAX webhook base URL.');
        }

        if ('https' !== strtolower((string) ($parts['scheme'] ?? ''))) {
            throw new \InvalidArgumentException('MAX webhook base URL must use https.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new \InvalidArgumentException('MAX webhook base URL must not contain credentials.');
        }

        if (isset($parts['query']) || isset($parts['fragment'])) {
            throw new \InvalidArgumentException('MAX webhook base URL must not contain query strings or fragments.');
        }

        if (isset($parts['port']) && 443 !== (int) $parts['port']) {
            throw new \InvalidArgumentException('MAX webhook base URL must not use a non-443 port.');
        }

        $host = strtolower((string) $parts['host']);
        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            throw new \InvalidArgumentException('MAX webhook base URL must not target localhost.');
        }

        if (filter_var($host, FILTER_VALIDATE_IP) && false === filter_var(
            $host,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        )) {
            throw new \InvalidArgumentException('MAX webhook base URL must not target private or reserved IP ranges.');
        }

        return rtrim($url, '/');
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
