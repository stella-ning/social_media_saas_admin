<?php

namespace App\Services;

use App\Models\CrawlerHumanBehavior;
use App\Models\CrmFollowReminder;
use App\Models\CrmLead;
use App\Models\IndustryPrompt;
use App\Models\PackageSetting;
use App\Models\PremiumFeatureUsageLog;
use App\Models\Tenant;
use App\Models\TenantSubAccount;
use App\Models\User;
use App\Support\PackageQuota;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class TenantPremiumService
{
    public function listSubAccounts(int $tenantId): array
    {
        PackageQuota::assertFeatureEnabled($tenantId, 'enable_sub_account', '多员工子账号');

        return TenantSubAccount::query()
            ->where('tenant_id', $tenantId)
            ->orderByDesc('id')
            ->get()
            ->map->toFrontendArray()
            ->values()
            ->all();
    }

    public function saveSubAccount(int $tenantId, array $data): TenantSubAccount
    {
        PackageQuota::assertFeatureEnabled($tenantId, 'enable_sub_account', '多员工子账号');
        $username = trim((string) ($data['username'] ?? ''));
        if ($username === '') {
            throw new RuntimeException('请填写子账号用户名');
        }

        $row = null;
        if (!empty($data['id'])) {
            $row = TenantSubAccount::query()->where('tenant_id', $tenantId)->findOrFail((int) $data['id']);
        } else {
            $row = new TenantSubAccount(['tenant_id' => $tenantId]);
            // 同步创建登录用户（演示）
            $user = User::query()->firstOrCreate(
                ['username' => $username],
                [
                    'display_name' => $data['displayName'] ?? $data['display_name'] ?? $username,
                    'name' => $data['displayName'] ?? $data['display_name'] ?? $username,
                    'password' => Hash::make($data['password'] ?? 'password123'),
                    'role' => 'operator',
                    'tenant_id' => $tenantId,
                    'status' => 1,
                ]
            );
            $row->user_id = $user->id;
        }

        $row->fill([
            'username' => $username,
            'display_name' => $data['displayName'] ?? $data['display_name'] ?? $username,
            'role' => $data['role'] ?? 'operator',
            'permissions' => $data['permissions'] ?? [],
            'status' => isset($data['status']) ? (int) $data['status'] : 1,
        ]);
        $row->save();

        PremiumFeatureUsageLog::create([
            'tenant_id' => $tenantId,
            'feature_key' => 'sub_account',
            'feature_label' => '子账号管理',
            'detail' => '保存子账号 '.$username,
            'used_at' => now(),
        ]);

        return $row->fresh();
    }

    public function deleteSubAccount(int $tenantId, int $id): void
    {
        PackageQuota::assertFeatureEnabled($tenantId, 'enable_sub_account', '多员工子账号');
        TenantSubAccount::query()->where('tenant_id', $tenantId)->where('id', $id)->delete();
    }

    public function industryPrompts(int $tenantId): array
    {
        $tenant = Tenant::query()->findOrFail($tenantId);
        $type = PackageSetting::typeFromPackageCode((string) $tenant->package);

        return IndustryPrompt::query()
            ->where('is_published', 1)
            ->orderBy('sort')
            ->orderBy('id')
            ->get()
            ->map(fn (IndustryPrompt $p) => $p->toFrontendArray($type))
            ->values()
            ->all();
    }

    public function saveHumanBehavior(int $tenantId, array $data): CrawlerHumanBehavior
    {
        PackageQuota::assertFeatureEnabled($tenantId, 'enable_human_behavior', '爬虫高级真人行为配置');
        $taskId = $data['crawlerTaskId'] ?? $data['crawler_task_id'] ?? null;

        $row = CrawlerHumanBehavior::query()->updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'crawler_task_id' => $taskId,
            ],
            [
                'scroll_min_ms' => (int) ($data['scrollMinMs'] ?? $data['scroll_min_ms'] ?? 800),
                'scroll_max_ms' => (int) ($data['scrollMaxMs'] ?? $data['scroll_max_ms'] ?? 2400),
                'dwell_min_ms' => (int) ($data['dwellMinMs'] ?? $data['dwell_min_ms'] ?? 1500),
                'dwell_max_ms' => (int) ($data['dwellMaxMs'] ?? $data['dwell_max_ms'] ?? 5000),
                'interval_min_ms' => (int) ($data['intervalMinMs'] ?? $data['interval_min_ms'] ?? 3000),
                'interval_max_ms' => (int) ($data['intervalMaxMs'] ?? $data['interval_max_ms'] ?? 8000),
                'fingerprint_preset' => $data['fingerprintPreset'] ?? $data['fingerprint_preset'] ?? 'desktop_chrome',
                'extra' => array_merge(
                    is_array($data['extra'] ?? null) ? $data['extra'] : [],
                    [
                        'enable_scroll' => array_key_exists('enableScroll', $data)
                            ? !empty($data['enableScroll'])
                            : (array_key_exists('enable_scroll', $data) ? !empty($data['enable_scroll']) : true),
                        'homepage_delay_min_ms' => (int) ($data['homepageDelayMinMs'] ?? $data['homepage_delay_min_ms'] ?? 800),
                        'homepage_delay_max_ms' => (int) ($data['homepageDelayMaxMs'] ?? $data['homepage_delay_max_ms'] ?? 2500),
                    ]
                ),
            ]
        );

        PremiumFeatureUsageLog::create([
            'tenant_id' => $tenantId,
            'feature_key' => 'human_behavior',
            'feature_label' => '爬虫真人行为配置',
            'detail' => '更新行为参数',
            'used_at' => now(),
        ]);

        return $row;
    }

    public function getHumanBehavior(int $tenantId, ?int $taskId = null): ?array
    {
        PackageQuota::assertFeatureEnabled($tenantId, 'enable_human_behavior', '爬虫高级真人行为配置');
        $row = CrawlerHumanBehavior::query()
            ->where('tenant_id', $tenantId)
            ->where('crawler_task_id', $taskId)
            ->first();

        return $row?->toFrontendArray();
    }

    public function listCrmReminders(int $tenantId): array
    {
        PackageQuota::assertFeatureEnabled($tenantId, 'enable_crm_auto_remind', 'CRM 线索自动提醒');

        return CrmFollowReminder::query()
            ->with('lead')
            ->where('tenant_id', $tenantId)
            ->orderBy('remind_at')
            ->limit(100)
            ->get()
            ->map->toFrontendArray()
            ->values()
            ->all();
    }

    public function saveCrmReminder(int $tenantId, array $data): CrmFollowReminder
    {
        PackageQuota::assertFeatureEnabled($tenantId, 'enable_crm_auto_remind', 'CRM 线索自动提醒');
        $leadId = (int) ($data['crmLeadId'] ?? $data['crm_lead_id'] ?? 0);
        $lead = CrmLead::query()->where('tenant_id', $tenantId)->findOrFail($leadId);

        $row = CrmFollowReminder::create([
            'tenant_id' => $tenantId,
            'crm_lead_id' => $lead->id,
            'title' => $data['title'] ?? ('跟进提醒：'.($lead->nickname ?? $lead->id)),
            'remind_at' => $data['remindAt'] ?? $data['remind_at'] ?? now()->addDay(),
            'status' => 'pending',
            'channel' => $data['channel'] ?? 'panel',
        ]);

        PremiumFeatureUsageLog::create([
            'tenant_id' => $tenantId,
            'feature_key' => 'crm_remind',
            'feature_label' => 'CRM 定时回访提醒',
            'detail' => $row->title,
            'used_at' => now(),
        ]);

        return $row->fresh('lead');
    }

    public function completeCrmReminder(int $tenantId, int $id): void
    {
        PackageQuota::assertFeatureEnabled($tenantId, 'enable_crm_auto_remind', 'CRM 线索自动提醒');
        CrmFollowReminder::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $id)
            ->update(['status' => 'done']);
    }

    public function updateIpPoolFlags(int $tenantId, array $data): Tenant
    {
        $tenant = Tenant::query()->findOrFail($tenantId);
        PackageQuota::assertPackageActive($tenant);
        $setting = PackageQuota::settingForTenant($tenant);

        if (array_key_exists('dedicatedIpPoolEnabled', $data) || array_key_exists('dedicated_ip_pool_enabled', $data)) {
            if ((int) ($setting->enable_dedicated_ip_pool ?? 0) !== 1) {
                throw new RuntimeException('当前套餐未开通专属隔离 IP 池');
            }
            $tenant->dedicated_ip_pool_enabled = !empty($data['dedicatedIpPoolEnabled'] ?? $data['dedicated_ip_pool_enabled']) ? 1 : 0;
        }
        if (array_key_exists('ipRotateEnabled', $data) || array_key_exists('ip_rotate_enabled', $data)) {
            if ((int) ($setting->enable_ip_rotate ?? 0) !== 1) {
                throw new RuntimeException('当前套餐未开通 IP 自动轮换');
            }
            $tenant->ip_rotate_enabled = !empty($data['ipRotateEnabled'] ?? $data['ip_rotate_enabled']) ? 1 : 0;
        }
        $tenant->allow_self_proxy = 0;
        $tenant->save();

        return $tenant->fresh();
    }
}
