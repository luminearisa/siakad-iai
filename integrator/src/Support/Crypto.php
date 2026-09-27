<?php

declare(strict_types=1);

namespace Integrator\Support;

use RuntimeException;

/**
 * Symmetric encryption for credentials stored in the local database.
 *
 * The SIAKAD API key and the Neo Feeder password must be recoverable at runtime
 * (the feeder login needs the plaintext), so they cannot be hashed. They are
 * encrypted with AES-256-GCM using the app key from `.env`.
 */
final class Crypto
{
    private const CIPHER = 'aes-256-gcm';

    public function __construct(private readonly string $key)
    {
        if (strlen($this->key) < 16) {
            throw new RuntimeException('APP_KEY tidak valid. Jalankan `php bin/console key:generate`.');
        }
    }

    /**
     * Encrypt a value. Returns a self-describing payload with its IV and tag.
     */
    public function encrypt(string $plain): string
    {
        $ivLength = openssl_cipher_iv_length(self::CIPHER) ?: 12;
        $iv = random_bytes($ivLength);
        $tag = '';

        $cipher = openssl_encrypt($plain, self::CIPHER, $this->key, OPENSSL_RAW_DATA, $iv, $tag);

        if ($cipher === false) {
            throw new RuntimeException('Gagal mengenkripsi data sensitif.');
        }

        return 'enc:'.base64_encode($iv.$tag.$cipher);
    }

    /**
     * Decrypt a payload produced by {@see encrypt()}.
     *
     * Values without the `enc:` prefix are returned untouched, so a hand-edited
     * setting keeps working (and is encrypted again on the next save).
     */
    public function decrypt(string $payload): string
    {
        if (! str_starts_with($payload, 'enc:')) {
            return $payload;
        }

        $raw = base64_decode(substr($payload, 4), true);

        if ($raw === false) {
            throw new RuntimeException('Payload terenkripsi rusak.');
        }

        $ivLength = openssl_cipher_iv_length(self::CIPHER) ?: 12;
        $iv = substr($raw, 0, $ivLength);
        $tag = substr($raw, $ivLength, 16);
        $cipher = substr($raw, $ivLength + 16);

        $plain = openssl_decrypt($cipher, self::CIPHER, $this->key, OPENSSL_RAW_DATA, $iv, $tag);

        if ($plain === false) {
            throw new RuntimeException('Gagal mendekripsi data. Pastikan APP_KEY tidak berubah.');
        }

        return $plain;
    }

    /**
     * Mask a secret for display: keeps the head so operators can recognise it.
     */
    public static function mask(?string $value, int $visible = 6): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (strlen($value) <= $visible) {
            return str_repeat('•', strlen($value));
        }

        return substr($value, 0, $visible).str_repeat('•', min(12, strlen($value) - $visible));
    }

    public static function generateKey(): string
    {
        return 'base64:'.base64_encode(random_bytes(32));
    }
}
