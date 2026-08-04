<?php

namespace App\Support;

use App\Models\AccountKnowledgeRel;
use App\Models\AiParamGlobal;
use App\Models\AiParamTemplate;
use App\Models\AiPromptTemplate;
use App\Models\KnowledgeDoc;
use App\Models\SocialAccount;
use App\Models\Tenant;
use App\Support\PlatformEnum;
use Illuminate\Support\Facades\Log;

/**
 * AI 配置三层优先级合并工具
 *
 * 优先级（高 → 低）：
 * 1. 小红书账号自定义绑定（参数模板 / Prompt / 知识库）
 * 2. 租户选定 AI 参数模板（current_ai_param_template_id，兼容 is_default）
 * 3. 平台全局默认配置
 *
 * 注意：返回给爬虫的 api_key 为解密明文，调用方用完应立即销毁；
 * 本类日志禁止打印 api_key / prompt 全文 / 账号凭据。
 */
class AiConfigResolver
{
    /**
     * 为社媒账号（或仅租户）拼装完整 AI 请求参数
     *
     * @return array{
     *   source:string,
     *   tenant_id:int|null,
     *   social_account_id:int|null,
     *   params:array{ai_model:string,api_key:?string,api_base_url:?string,temperature:float,max_tokens:int,top_p:float,daily_call_quota:int},
     *   prompt:?array{id:int,name:string,category:string,role:?string,rules:?string,source:string},
     *   knowledge_docs:array<int,array{id:int,name:string,file_path:?string}>,
     *   knowledge_source:string
     * }
     */
    public static function resolveForAccount(?SocialAccount $account, ?int $tenantId = null): array
    {
        $tenantId = $account?->tenant_id ?? $tenantId;

        Log::info('ai_config.resolve', [
            'account_id' => $account?->id,
            'tenant_id' => $tenantId,
            'platform' => $account?->platform,
            // 禁止敏感明文
        ]);

        $params = self::resolveParams($account, $tenantId);
        $prompt = self::resolvePrompt($account, $tenantId);
        [$docs, $kbSource] = self::resolveKnowledge($account, $tenantId);

        return [
            'source' => $params['source'],
            'tenant_id' => $tenantId,
            'social_account_id' => $account?->id,
            'params' => $params['params'],
            'prompt' => $prompt,
            'knowledge_docs' => $docs,
            'knowledge_source' => $kbSource,
        ];
    }

    /**
     * 仅解析参数层（账号绑定 → 租户默认 → 全局）
     */
    public static function resolveParams(?SocialAccount $account, ?int $tenantId): array
    {
        // 1) 小红书账号绑定参数模板
        if ($account && (int) $account->platform === PlatformEnum::XHS && $account->bind_param_template_id) {
            $tpl = AiParamTemplate::query()
                ->where('id', $account->bind_param_template_id)
                ->where('tenant_id', $account->tenant_id)
                ->first();
            if ($tpl) {
                return [
                    'source' => 'account_param_template',
                    'params' => self::paramArrayFromTemplate($tpl),
                ];
            }
        }

        // 2) 租户选定模板（current_ai_param_template_id 优先，其次 is_default）
        if ($tenantId) {
            $tenant = Tenant::query()->find($tenantId);
            if ($tenant?->current_ai_param_template_id) {
                $tpl = AiParamTemplate::query()
                    ->where('id', $tenant->current_ai_param_template_id)
                    ->where('tenant_id', $tenantId)
                    ->first();
                if ($tpl) {
                    return [
                        'source' => 'tenant_selected_param',
                        'params' => self::paramArrayFromTemplate($tpl),
                    ];
                }
            }

            $tpl = AiParamTemplate::query()
                ->where('tenant_id', $tenantId)
                ->where('is_default', 1)
                ->first();
            if ($tpl) {
                return [
                    'source' => 'tenant_default_param',
                    'params' => self::paramArrayFromTemplate($tpl),
                ];
            }
        }

        // 3) 平台全局
        $global = AiParamGlobal::current();

        return [
            'source' => 'platform_global',
            'params' => [
                'ai_model' => $global?->ai_model ?: 'gpt-4o-mini',
                'api_key' => self::safeDecrypt($global?->encrypt_api_key),
                'api_base_url' => $global?->api_base_url,
                'temperature' => (float) ($global?->temperature ?? 0.7),
                'max_tokens' => (int) ($global?->max_tokens ?? 2048),
                'top_p' => (float) ($global?->top_p ?? 1.0),
                'daily_call_quota' => (int) ($global?->daily_call_quota ?? 1000),
            ],
        ];
    }

    /**
     * Prompt：账号绑定 → 租户默认 → null（爬虫侧可回退硬编码）
     */
    public static function resolvePrompt(?SocialAccount $account, ?int $tenantId): ?array
    {
        if ($account && (int) $account->platform === PlatformEnum::XHS && $account->bind_prompt_id) {
            $tpl = AiPromptTemplate::query()
                ->where('id', $account->bind_prompt_id)
                ->where('tenant_id', $account->tenant_id)
                ->first();
            if ($tpl) {
                return self::promptArray($tpl, 'account_prompt');
            }
        }

        if ($tenantId) {
            $tpl = AiPromptTemplate::query()
                ->where('tenant_id', $tenantId)
                ->where('is_default', 1)
                ->first();
            if ($tpl) {
                return self::promptArray($tpl, 'tenant_default_prompt');
            }
            // 无默认标记时取该租户最新一条作为弱回退
            $tpl = AiPromptTemplate::query()->where('tenant_id', $tenantId)->orderByDesc('id')->first();
            if ($tpl) {
                return self::promptArray($tpl, 'tenant_latest_prompt');
            }
        }

        return null;
    }

    /**
     * 知识库：账号专属勾选 → 租户全部文档
     *
     * @return array{0: array, 1: string}
     */
    public static function resolveKnowledge(?SocialAccount $account, ?int $tenantId): array
    {
        if (
            $account
            && (int) $account->platform === PlatformEnum::XHS
            && (int) $account->enable_account_knowledge === 1
        ) {
            $ids = AccountKnowledgeRel::query()
                ->where('social_account_id', $account->id)
                ->pluck('knowledge_id')
                ->all();
            $docs = KnowledgeDoc::query()
                ->where('tenant_id', $account->tenant_id)
                ->whereIn('id', $ids ?: [0])
                ->where('status', 'ready')
                ->get()
                ->map(fn (KnowledgeDoc $d) => [
                    'id' => $d->id,
                    'name' => $d->name,
                    'file_path' => $d->file_path,
                ])
                ->values()
                ->all();

            return [$docs, 'account_knowledge'];
        }

        if ($tenantId) {
            $docs = KnowledgeDoc::query()
                ->where('tenant_id', $tenantId)
                ->where('status', 'ready')
                ->get()
                ->map(fn (KnowledgeDoc $d) => [
                    'id' => $d->id,
                    'name' => $d->name,
                    'file_path' => $d->file_path,
                ])
                ->values()
                ->all();

            return [$docs, 'tenant_knowledge'];
        }

        return [[], 'none'];
    }

    private static function paramArrayFromTemplate(AiParamTemplate $tpl): array
    {
        return [
            'ai_model' => $tpl->ai_model,
            'api_key' => self::safeDecrypt($tpl->encrypt_api_key),
            'api_base_url' => $tpl->api_base_url,
            'temperature' => (float) $tpl->temperature,
            'max_tokens' => (int) $tpl->max_tokens,
            'top_p' => (float) $tpl->top_p,
            'daily_call_quota' => (int) $tpl->daily_call_quota,
            'template_id' => $tpl->id,
            'template_name' => $tpl->template_name,
        ];
    }

    private static function promptArray(AiPromptTemplate $tpl, string $source): array
    {
        return [
            'id' => $tpl->id,
            'name' => $tpl->name,
            'category' => $tpl->category,
            'role' => $tpl->role,
            'rules' => $tpl->rules,
            'source' => $source,
        ];
    }

    private static function safeDecrypt(?string $cipher): ?string
    {
        if ($cipher === null || $cipher === '') {
            return null;
        }
        try {
            return AesCrypto::decrypt($cipher);
        } catch (\Throwable) {
            Log::warning('ai_config.api_key_decrypt_failed');

            return null;
        }
    }
}
