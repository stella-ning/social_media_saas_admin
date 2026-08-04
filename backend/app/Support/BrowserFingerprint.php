<?php

namespace App\Support;

/**
 * 为每个社媒账号生成并固定浏览器指纹（UA + 分辨率）
 * 后续刷新 Cookie、爬虫任务必须复用同一指纹
 */
class BrowserFingerprint
{
    private const UA_POOL = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 13_6_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36',
    ];

    public const VIEWPORT = '1920x1080';

    /**
     * 基于账号标识稳定生成 UA（同一账号始终同一指纹）
     */
    public static function generate(string $seed): array
    {
        $idx = abs(crc32($seed)) % count(self::UA_POOL);

        return [
            'user_agent' => self::UA_POOL[$idx],
            'viewport' => self::VIEWPORT,
        ];
    }
}
