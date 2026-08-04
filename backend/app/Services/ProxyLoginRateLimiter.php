<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use RuntimeException;

/**
 * 代理 IP 登录频率限制：单个 proxy 一小时最多触发一次登录任务
 */
class ProxyLoginRateLimiter
{
    private const TTL_SECONDS = 3600;

    private function key(int $proxyId): string
    {
        return 'proxy_login_rate:'.$proxyId;
    }

    /** 是否允许发起登录 */
    public function allow(int $proxyId): bool
    {
        return !Cache::has($this->key($proxyId));
    }

    /**
     * 占用一次登录额度；若已存在则抛错
     */
    public function hit(int $proxyId): void
    {
        $key = $this->key($proxyId);
        if (Cache::has($key)) {
            $ttl = $this->remainingSeconds($proxyId);
            throw new RuntimeException(
                '该代理 IP 一小时内已触发过登录，请 '.ceil($ttl / 60).' 分钟后再试（防风控）'
            );
        }
        Cache::put($key, now()->timestamp, self::TTL_SECONDS);
    }

    public function remainingSeconds(int $proxyId): int
    {
        try {
            $ttl = Redis::ttl(config('cache.prefix').$this->key($proxyId));
            if (is_int($ttl) && $ttl > 0) {
                return $ttl;
            }
        } catch (\Throwable) {
            // ignore
        }

        return self::TTL_SECONDS;
    }
}
