<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMaxBotsBundle\Helper;

final class TokenCryptoHelper
{
    private const PREFIX = 'enc:v1:';

    public function __construct(private string $key)
    {
    }

    public function encrypt(string $plaintext): string
    {
        return $this->encryptIfNeeded($plaintext);
    }

    public function encryptIfNeeded(string $value): string
    {
        $value = trim($value);
        if ('' === $value || $this->isEncrypted($value)) {
            return $value;
        }

        $iv = random_bytes(16);
        $ciphertext = openssl_encrypt(
            $value,
            'aes-256-cbc',
            $this->getEncryptionKey(),
            OPENSSL_RAW_DATA,
            $iv
        );

        if (false === $ciphertext) {
            throw new \RuntimeException('Unable to encrypt MAX token');
        }

        $mac = hash_hmac('sha256', $iv.$ciphertext, $this->getMacKey(), true);

        return self::PREFIX.base64_encode($iv).':'.base64_encode($ciphertext).':'.base64_encode($mac);
    }

    public function decrypt(string $encoded): string
    {
        return $this->decryptIfNeeded($encoded);
    }

    public function decryptIfNeeded(?string $value): string
    {
        $value = trim((string) $value);
        if ('' === $value || !$this->isEncrypted($value)) {
            return $value;
        }

        $parts = explode(':', $value, 5);
        if (5 !== count($parts)) {
            throw new \RuntimeException('Malformed encrypted MAX token.');
        }

        [, , $ivEncoded, $ciphertextEncoded, $macEncoded] = $parts;

        $iv = base64_decode($ivEncoded, true);
        $ciphertext = base64_decode($ciphertextEncoded, true);
        $mac = base64_decode($macEncoded, true);

        if (false === $iv || false === $ciphertext || false === $mac) {
            throw new \RuntimeException('Invalid encrypted MAX token encoding.');
        }

        $expectedMac = hash_hmac('sha256', $iv.$ciphertext, $this->getMacKey(), true);
        if (!hash_equals($expectedMac, $mac)) {
            throw new \RuntimeException('Encrypted MAX token integrity check failed.');
        }

        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-cbc',
            $this->getEncryptionKey(),
            OPENSSL_RAW_DATA,
            $iv
        );

        if (false === $plaintext) {
            throw new \RuntimeException('Unable to decrypt MAX token');
        }

        return $plaintext;
    }

    public function isEncrypted(?string $value): bool
    {
        return str_starts_with(trim((string) $value), self::PREFIX);
    }

    private function getEncryptionKey(): string
    {
        return hash('sha256', $this->key, true);
    }

    private function getMacKey(): string
    {
        return hash('sha256', $this->key.'|mac', true);
    }
}
