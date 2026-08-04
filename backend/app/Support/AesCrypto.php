<?php

namespace App\Support;

use RuntimeException;

/**
 * AES-256-CBC 加密工具
 * 用途：社媒账号登录密码落库加密，明文用完即销毁，禁止写入日志
 */
class AesCrypto
{
    private const CIPHER = 'AES-256-CBC';

    /**
     * 从配置解析 32 字节密钥
     */
    private static function key(): string
    {
        $raw = (string) config('services.aes.key', '');
        if (str_starts_with($raw, 'base64:')) {
            $raw = base64_decode(substr($raw, 7), true) ?: '';
        }
        // 统一派生为 32 字节
        return hash('sha256', $raw, true);
    }

    /**
     * 加密明文，返回 base64(iv + cipher)
     */
    public static function encrypt(string $plain): string
    {
        $iv = random_bytes(openssl_cipher_iv_length(self::CIPHER));
        $cipher = openssl_encrypt($plain, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv);
        if ($cipher === false) {
            throw new RuntimeException('AES 加密失败');
        }

        return base64_encode($iv.$cipher);
    }

    /**
     * 解密；调用方用完明文后应立即 unset
     */
    public static function decrypt(string $payload): string
    {
        $bin = base64_decode($payload, true);
        if ($bin === false || strlen($bin) < 17) {
            throw new RuntimeException('AES 密文无效');
        }
        $ivLen = openssl_cipher_iv_length(self::CIPHER);
        $iv = substr($bin, 0, $ivLen);
        $cipher = substr($bin, $ivLen);
        $plain = openssl_decrypt($cipher, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv);
        if ($plain === false) {
            throw new RuntimeException('AES 解密失败');
        }

        return $plain;
    }
}
