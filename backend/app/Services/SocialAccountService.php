<?php

namespace App\Services;

use App\Models\AccountCookie;
use App\Models\AccountOperationLog;
use App\Models\ProxyIp;
use App\Models\SocialAccount;
use App\Models\TenantProxy;
use App\Support\AesCrypto;
use App\Support\BrowserFingerprint;
use App\Support\PlatformEnum;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * 社媒账号服务：凭据绑定 + Playwright 自动登录 + Cookie 管理
 * 硬性约束：一号一IP、租户隔离、密码 AES 加密、日志脱敏
 */
class SocialAccountService
{
    public function __construct(
        private PythonLoginClient $pythonLogin,
        private ProxyLoginRateLimiter $rateLimiter,
    ) {
    }

    public function list(array $filters, ?int $scopeTenantId = null): LengthAwarePaginator
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $size = min(100, max(1, (int) ($filters['size'] ?? 10)));

        $query = SocialAccount::query()->with(['tenant', 'proxy'])->orderByDesc('id');

        if ($scopeTenantId) {
            $query->where('tenant_id', $scopeTenantId);
        } elseif (!empty($filters['tenantId']) || !empty($filters['tenant_id'])) {
            $query->where('tenant_id', $filters['tenantId'] ?? $filters['tenant_id']);
        }

        if (!empty($filters['keyword'])) {
            $kw = $filters['keyword'];
            $query->where(function ($q) use ($kw) {
                $q->where('account_name', 'like', "%{$kw}%")
                    ->orWhere('display_name', 'like', "%{$kw}%");
            });
        }

        if (isset($filters['status']) && $filters['status'] !== '') {
            $status = $filters['status'];
            if ($status === 'online' || $status === '1' || $status === 1) {
                $query->where('account_status', 1);
            } elseif ($status === 'offline' || $status === '0' || $status === 0) {
                $query->where('account_status', 0);
            }
        }

        if (!empty($filters['platform'])) {
            try {
                $query->where('platform', PlatformEnum::toCode($filters['platform']));
            } catch (\InvalidArgumentException) {
                // ignore invalid filter
            }
        }

        return $query->paginate($size, ['*'], 'page', $page);
    }

    /**
     * 获取租户下空闲可用代理 IP
     * 条件：归属该租户、状态正常、当前未被任何账号绑定
     */
    public function freeProxyIps(int $tenantId): array
    {
        // 同步：若 proxy_ips.tenant_id 已绑定但关联表缺失，补齐关联
        $this->syncTenantProxyLinks($tenantId);

        $boundIds = SocialAccount::query()
            ->whereNotNull('bind_proxy_id')
            ->pluck('bind_proxy_id')
            ->all();

        $proxyIds = TenantProxy::query()
            ->where('tenant_id', $tenantId)
            ->pluck('proxy_ip_id')
            ->all();

        // 兼容：直接看 proxy_ips.tenant_id
        $directIds = ProxyIp::query()
            ->where('tenant_id', $tenantId)
            ->pluck('id')
            ->all();

        $ids = array_values(array_unique(array_merge($proxyIds, $directIds)));
        if (!$ids) {
            return [];
        }

        return ProxyIp::query()
            ->whereIn('id', $ids)
            ->whereNotIn('id', $boundIds ?: [0])
            ->whereIn('status', ['running', 'idle'])
            ->orderBy('id')
            ->get()
            ->map(fn (ProxyIp $p) => $p->toFrontendArray())
            ->values()
            ->all();
    }

    /**
     * 绑定新账号：校验 → AES 加密密码 → 调 Python 登录 → 存 Cookie
     *
     * @param  array{
     *   tenant_id:int,
     *   platform:string|int,
     *   account_name:string,
     *   password:string,
     *   code?:string|null,
     *   proxy_ip_id:int
     * }  $data
     */
    public function storeWithAutoLogin(array $data): SocialAccount
    {
        $tenantId = (int) $data['tenant_id'];
        $platformCode = PlatformEnum::toCode($data['platform']);
        $accountName = trim((string) $data['account_name']);
        $password = (string) $data['password'];
        $verifyCode = isset($data['code']) ? trim((string) $data['code']) : null;
        $proxyIpId = (int) $data['proxy_ip_id'];

        // 套餐：账号数量 + 平台权限
        \App\Support\PackageQuota::assertCanBindSocialAccount($tenantId, $platformCode);

        $proxy = $this->assertProxyAvailableForTenant($tenantId, $proxyIpId);

        // 一小时一次登录频率限制
        $this->rateLimiter->hit($proxyIpId);

        $fingerprint = BrowserFingerprint::generate($tenantId.'|'.$platformCode.'|'.$accountName);
        $encryptPwd = AesCrypto::encrypt($password);

        return DB::transaction(function () use (
            $tenantId,
            $platformCode,
            $accountName,
            $password,
            $verifyCode,
            $proxy,
            $fingerprint,
            $encryptPwd
        ) {
            $account = SocialAccount::create([
                'tenant_id' => $tenantId,
                'platform' => $platformCode,
                'account_name' => $accountName,
                'encrypt_pwd' => $encryptPwd,
                'bind_proxy_id' => $proxy->id,
                'browser_user_agent' => $fingerprint['user_agent'],
                'browser_viewport' => $fingerprint['viewport'],
                'account_status' => 0,
                'display_name' => '新绑定账号_'.PlatformEnum::toLabel($platformCode),
                'avatar' => 'https://cube.elemecdn.com/3/7c/3ea6beec64369c2642b92c6726f1epng.png',
            ]);

            $this->writeLog($account->id, 'info', '开始自动登录绑定');

            try {
                $result = $this->pythonLogin->autoLogin([
                    'platform' => PlatformEnum::toPythonKey($platformCode),
                    'proxy_server_addr' => $this->formatProxyAddr($proxy),
                    'account' => $accountName,
                    'password' => $password,
                    'verify_code' => $verifyCode,
                    'user_agent' => $fingerprint['user_agent'],
                ]);
            } finally {
                // 明文用完立刻销毁
                unset($password);
            }

            if (!$result['success'] || empty($result['cookies'])) {
                $msg = $result['msg'] ?: '自动登录失败';
                if (!empty($result['captcha'])) {
                    $msg = '检测到滑块/拼图验证码，登录失败（防风控）';
                    $account->risk_tip = '触发人机验证，请人工处理后重试';
                }
                $account->account_status = 0;
                $account->login_fail_count = 1;
                $account->save();
                $this->writeLog($account->id, 'danger', '绑定失败：'.$msg);
                throw new RuntimeException($msg);
            }

            $this->persistCookies($account, $result['cookies']);
            $account->account_status = 1;
            $account->login_fail_count = 0;
            $account->risk_tip = null;
            $account->last_refresh_cookie = now();
            if (!empty($result['user_agent'])) {
                $account->browser_user_agent = $result['user_agent'];
            }
            $account->save();

            // 标记代理占用
            if ($proxy->status === 'idle') {
                $proxy->status = 'running';
                $proxy->save();
            }
            $proxy->increment('load');

            $this->writeLog($account->id, 'success', '自动登录成功，Cookie 已保存，账号在线');

            return $account->load(['tenant', 'proxy']);
        });
    }

    /**
     * 检测 Cookie 会话是否有效
     */
    public function checkLogin(SocialAccount $account): array
    {
        $account->loadMissing(['proxy', 'activeCookie']);
        if (!$account->proxy || !$account->activeCookie) {
            return ['valid' => false, 'msg' => '缺少代理或有效 Cookie'];
        }

        $cookies = json_decode($account->activeCookie->cookie_json, true) ?: [];
        $result = $this->pythonLogin->checkCookie(
            PlatformEnum::toPythonKey((int) $account->platform),
            $this->formatProxyAddr($account->proxy),
            $cookies,
            (string) $account->browser_user_agent
        );

        if (!$result['valid']) {
            $account->activeCookie->update(['expire_status' => 0]);
            $this->writeLog($account->id, 'warning', '会话检测失效');
        } else {
            $this->writeLog($account->id, 'success', '会话检测有效');
        }

        return $result;
    }

    /**
     * 使用绑定专属代理 + 固定指纹重新登录刷新 Cookie
     * @return bool 是否刷新成功
     */
    public function refreshCookie(SocialAccount $account): bool
    {
        $account->loadMissing('proxy');
        if (!$account->proxy || !$account->bind_proxy_id) {
            $account->account_status = 0;
            $account->risk_tip = '未绑定专属代理 IP，无法刷新会话';
            $account->save();
            $this->writeLog($account->id, 'danger', '刷新失败：未绑定代理');

            return false;
        }

        // 爬虫/刷新强制使用绑定 IP，不可更换
        try {
            $this->rateLimiter->hit((int) $account->bind_proxy_id);
        } catch (RuntimeException $e) {
            $this->writeLog($account->id, 'warning', '刷新跳过：'.$e->getMessage());

            return false;
        }

        $plainPwd = null;
        try {
            $plainPwd = AesCrypto::decrypt($account->encrypt_pwd);
            $result = $this->pythonLogin->autoLogin([
                'platform' => PlatformEnum::toPythonKey((int) $account->platform),
                'proxy_server_addr' => $this->formatProxyAddr($account->proxy),
                'account' => $account->account_name,
                'password' => $plainPwd,
                'verify_code' => null,
                'user_agent' => (string) $account->browser_user_agent,
            ]);
        } catch (\Throwable $e) {
            $account->login_fail_count = (int) $account->login_fail_count + 1;
            $account->save();
            $this->writeLog($account->id, 'danger', '刷新异常：服务调用失败');

            return false;
        } finally {
            unset($plainPwd);
        }

        if (!$result['success'] || empty($result['cookies']) || !empty($result['captcha'])) {
            $account->login_fail_count = (int) $account->login_fail_count + 1;
            if (!empty($result['captcha'])) {
                $account->risk_tip = '刷新触发人机验证';
            }
            if ($account->login_fail_count >= 3) {
                $account->account_status = 0;
                $account->risk_tip = '连续登录失败 '.$account->login_fail_count.' 次，账号已置离线';
                $this->writeLog($account->id, 'danger', $account->risk_tip);
            } else {
                $this->writeLog($account->id, 'warning', 'Cookie 刷新失败（'.$account->login_fail_count.'/3）');
            }
            $account->save();

            return false;
        }

        $this->persistCookies($account, $result['cookies']);
        $account->account_status = 1;
        $account->login_fail_count = 0;
        $account->risk_tip = null;
        $account->last_refresh_cookie = now();
        $account->save();
        $this->writeLog($account->id, 'success', 'Cookie 已自动刷新');

        return true;
    }

    /** 解绑：释放代理占用 */
    public function unbind(SocialAccount $account): void
    {
        DB::transaction(function () use ($account) {
            if ($account->bind_proxy_id) {
                $proxy = ProxyIp::query()->find($account->bind_proxy_id);
                if ($proxy) {
                    if ($proxy->load > 0) {
                        $proxy->decrement('load');
                    }
                    $proxy->refresh();
                    if ((int) $proxy->load <= 0) {
                        $proxy->update(['status' => 'idle', 'load' => 0]);
                    }
                }
            }
            AccountCookie::query()->where('social_account_id', $account->id)->update(['expire_status' => 0]);
            $account->delete();
        });
    }

    /** 批量刷新状态：以 Cookie 检测为准（替代随机 mock） */
    public function refreshStatus(?int $scopeTenantId = null): array
    {
        $query = SocialAccount::query()->with(['tenant', 'proxy', 'activeCookie']);
        if ($scopeTenantId) {
            $query->where('tenant_id', $scopeTenantId);
        }
        $list = $query->get();
        foreach ($list as $item) {
            $check = $this->checkLogin($item);
            $item->account_status = $check['valid'] ? 1 : 0;
            $item->save();
        }

        return $list->fresh(['tenant', 'proxy'])->map->toFrontendArray()->values()->all();
    }

    public function operationLogs(SocialAccount $account): array
    {
        return AccountOperationLog::query()
            ->where('social_account_id', $account->id)
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (AccountOperationLog $log) => [
                'id' => $log->id,
                'type' => $log->type,
                'content' => $log->content,
                'time' => optional($log->logged_at)?->format('Y-m-d H:i:s'),
            ])
            ->values()
            ->all();
    }

    private function assertProxyAvailableForTenant(int $tenantId, int $proxyIpId): ProxyIp
    {
        $proxy = ProxyIp::query()->find($proxyIpId);
        if (!$proxy) {
            throw new RuntimeException('代理 IP 不存在');
        }

        $belongs = TenantProxy::query()
            ->where('tenant_id', $tenantId)
            ->where('proxy_ip_id', $proxyIpId)
            ->exists()
            || (int) $proxy->tenant_id === $tenantId;

        if (!$belongs) {
            throw new RuntimeException('该代理 IP 不属于当前租户，禁止跨租户使用');
        }

        if (!in_array($proxy->status, ['running', 'idle'], true)) {
            throw new RuntimeException('该代理 IP 不可用');
        }

        $occupied = SocialAccount::query()->where('bind_proxy_id', $proxyIpId)->exists();
        if ($occupied) {
            throw new RuntimeException('一号一IP：该代理已被其他账号绑定');
        }

        return $proxy;
    }

    private function persistCookies(SocialAccount $account, array $cookies): void
    {
        AccountCookie::query()
            ->where('social_account_id', $account->id)
            ->where('expire_status', 1)
            ->update(['expire_status' => 0]);

        AccountCookie::create([
            'social_account_id' => $account->id,
            'cookie_json' => json_encode($cookies, JSON_UNESCAPED_UNICODE),
            'expire_status' => 1,
            'created_at' => now(),
        ]);
    }

    private function writeLog(int $accountId, string $type, string $content): void
    {
        // 二次脱敏：避免误写入敏感词段落
        $safe = preg_replace('/(password|passwd|cookie|token)\s*[:=]\s*\S+/i', '$1=***', $content) ?: $content;
        AccountOperationLog::create([
            'social_account_id' => $accountId,
            'type' => $type,
            'content' => mb_substr($safe, 0, 500),
            'logged_at' => now(),
        ]);
    }

    private function formatProxyAddr(ProxyIp $proxy): string
    {
        $addr = $proxy->address;
        if (!str_contains($addr, '://')) {
            $scheme = str_contains(strtoupper((string) $proxy->protocol), 'SOCKS') ? 'socks5' : 'http';
            $addr = $scheme.'://'.$addr;
        }

        return $addr;
    }

    private function syncTenantProxyLinks(int $tenantId): void
    {
        $ids = ProxyIp::query()->where('tenant_id', $tenantId)->pluck('id');
        foreach ($ids as $pid) {
            TenantProxy::query()->firstOrCreate([
                'tenant_id' => $tenantId,
                'proxy_ip_id' => $pid,
            ]);
        }
    }
}
