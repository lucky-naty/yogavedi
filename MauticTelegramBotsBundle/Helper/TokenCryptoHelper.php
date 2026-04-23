<?php

declare(strict_types=1);

namespace MauticPlugin\MauticTelegramBotsBundle\Helper;

class TokenCryptoHelper
{
    private const PREFIX = 'enc:v1:';

    public function encryptIfNeeded(string $value): string
    {
        $value = trim($value);
        if ('' === $value || $this->isEncrypted($value)) {
            return $value;
        }

        $iv = random_bytes(16);
        $ciphertext = openssl_encrypt(
            $value,
            'AES-256-CBC',
            $this->getEncryptionKey(),
            OPENSSL_RAW_DATA,
            $iv
        );

        if (false === $ciphertext) {
            throw new \RuntimeException('Unable to encrypt Telegram token.');
        }

        $mac = hash_hmac('sha256', $iv.$ciphertext, $this->getMacKey(), true);

        return self::PREFIX.base64_encode($iv).':'.base64_encode($ciphertext).':'.base64_encode($mac);
    }

    public function decryptIfNeeded(?string $value): string
    {
        $value = trim((string) $value);
        if ('' === $value || !$this->isEncrypted($value)) {
            return $value;
        }

        $parts = explode(':', $value, 5);
        if (5 !== count($parts)) {
            throw new \RuntimeException('Malformed encrypted Telegram token.');
        }

        [, , $ivEncoded, $ciphertextEncoded, $macEncoded] = $parts;

        $iv = base64_decode($ivEncoded, true);
        $ciphertext = base64_decode($ciphertextEncoded, true);
        $mac = base64_decode($macEncoded, true);

        if (false === $iv || false === $ciphertext || false === $mac) {
            throw new \RuntimeException('Invalid encrypted Telegram token encoding.');
        }

        $expectedMac = hash_hmac('sha256', $iv.$ciphertext, $this->getMacKey(), true);
        if (!hash_equals($expectedMac, $mac)) {
            throw new \RuntimeException('Encrypted Telegram token integrity check failed.');
        }

        $plaintext = openssl_decrypt(
            $ciphertext,
            'AES-256-CBC',
            $this->getEncryptionKey(),
            OPENSSL_RAW_DATA,
            $iv
        );

        if (false === $plaintext) {
            throw new \RuntimeException('Unable to decrypt Telegram token.');
        }

        return $plaintext;
    }

    public function isEncrypted(?string $value): bool
    {
        return str_starts_with(trim((string) $value), self::PREFIX);
    }

    private function getEncryptionKey(): string
    {
        return hash('sha256', $this->getSecret().'|telegram-token|enc', true);
    }

    private function getMacKey(): string
    {
        return hash('sha256', $this->getSecret().'|telegram-token|mac', true);
    }

    private function getSecret(): string
    {
        $projectRoot = dirname(__DIR__, 3);
        $candidateFiles = [
            $projectRoot.'/config/local.php',
            $projectRoot.'/app/config/local.php',
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

        throw new \RuntimeException('Mautic secret_key is required for Telegram token encryption.');
    }
}
