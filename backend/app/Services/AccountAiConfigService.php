<?php

namespace App\Services;

use App\Models\AccountKnowledgeRel;
use App\Models\AiParamTemplate;
use App\Models\AiPromptTemplate;
use App\Models\KnowledgeDoc;
use App\Models\SocialAccount;
use App\Support\AiConfigResolver;
use App\Support\PlatformEnum;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * 小红书账号级 AI 配置绑定
 * 抖音/视频号不支持账号级独立配置
 */
class AccountAiConfigService
{
    /**
     * 读取账号 AI 绑定配置（含可选下拉数据）
     */
    public function getConfig(SocialAccount $account): array
    {
        if ((int) $account->platform !== PlatformEnum::XHS) {
            throw new RuntimeException('仅小红书账号支持账号级 AI 配置，抖音/视频号沿用租户默认');
        }

        // 套餐未开启独立 AI 配置时禁止进入绑定
        \App\Support\PackageQuota::assertAccountAiConfigEnabled((int) $account->tenant_id);

        $tenantId = (int) $account->tenant_id;
        $knowledgeIds = AccountKnowledgeRel::query()
            ->where('social_account_id', $account->id)
            ->pluck('knowledge_id')
            ->values()
            ->all();

        return [
            'accountId' => $account->id,
            'platform' => PlatformEnum::toLabel(PlatformEnum::XHS),
            'tenantId' => $tenantId,
            'bindParamTemplateId' => $account->bind_param_template_id,
            'bindPromptId' => $account->bind_prompt_id,
            'enableAccountKnowledge' => (int) $account->enable_account_knowledge === 1,
            'knowledgeIds' => $knowledgeIds,
            // 下拉选项（租户隔离）
            'paramTemplates' => AiParamTemplate::query()
                ->where('tenant_id', $tenantId)
                ->orderByDesc('is_default')
                ->get()
                ->map(fn (AiParamTemplate $t) => [
                    'id' => $t->id,
                    'label' => $t->template_name.((int) $t->is_default === 1 ? '（默认）' : ''),
                    'isDefault' => (int) $t->is_default === 1,
                ])
                ->values()
                ->all(),
            'promptTemplates' => AiPromptTemplate::query()
                ->where('tenant_id', $tenantId)
                ->orderByDesc('is_default')
                ->orderByDesc('id')
                ->get()
                ->map(fn (AiPromptTemplate $t) => [
                    'id' => $t->id,
                    'label' => $t->name.((int) ($t->is_default ?? 0) === 1 ? '（默认）' : ''),
                    'category' => $t->category,
                    'isDefault' => (int) ($t->is_default ?? 0) === 1,
                ])
                ->values()
                ->all(),
            'knowledgeDocs' => KnowledgeDoc::query()
                ->where('tenant_id', $tenantId)
                ->orderByDesc('id')
                ->get()
                ->map(fn (KnowledgeDoc $d) => [
                    'id' => $d->id,
                    'name' => $d->name,
                    'status' => $d->status,
                ])
                ->values()
                ->all(),
            // 当前生效预览（不含 api_key 明文）
            'resolvedPreview' => $this->resolvedPreview($account),
        ];
    }

    /**
     * 保存账号绑定；传 null/空数组表示继承租户默认
     *
     * @param  array{
     *   bind_param_template_id?:int|null,
     *   bind_prompt_id?:int|null,
     *   knowledge_ids?:array<int>,
     *   reset?:bool
     * }  $data
     */
    public function saveConfig(SocialAccount $account, array $data): array
    {
        if ((int) $account->platform !== PlatformEnum::XHS) {
            throw new RuntimeException('仅小红书账号支持账号级 AI 配置');
        }

        $tenantId = (int) $account->tenant_id;
        \App\Support\PackageQuota::assertAccountAiConfigEnabled($tenantId);

        return DB::transaction(function () use ($account, $data, $tenantId) {
            if (!empty($data['reset'])) {
                $account->bind_param_template_id = null;
                $account->bind_prompt_id = null;
                $account->enable_account_knowledge = 0;
                $account->save();
                AccountKnowledgeRel::query()->where('social_account_id', $account->id)->delete();

                return $this->getConfig($account->fresh());
            }

            $paramId = $data['bind_param_template_id'] ?? null;
            $promptId = $data['bind_prompt_id'] ?? null;
            $knowledgeIds = array_values(array_unique(array_map('intval', $data['knowledge_ids'] ?? [])));

            if ($knowledgeIds) {
                \App\Support\PackageQuota::assertAccountKnowledgeEnabled($tenantId);
            }

            if ($paramId) {
                $ok = AiParamTemplate::query()
                    ->where('id', $paramId)
                    ->where('tenant_id', $tenantId)
                    ->exists();
                if (!$ok) {
                    throw new RuntimeException('AI 参数模板不存在或不属于当前租户');
                }
            }

            if ($promptId) {
                $ok = AiPromptTemplate::query()
                    ->where('id', $promptId)
                    ->where('tenant_id', $tenantId)
                    ->exists();
                if (!$ok) {
                    throw new RuntimeException('Prompt 模板不存在或不属于当前租户');
                }
            }

            if ($knowledgeIds) {
                $count = KnowledgeDoc::query()
                    ->where('tenant_id', $tenantId)
                    ->whereIn('id', $knowledgeIds)
                    ->count();
                if ($count !== count($knowledgeIds)) {
                    throw new RuntimeException('知识库文档存在越权或不存在的项');
                }
            }

            $account->bind_param_template_id = $paramId ?: null;
            $account->bind_prompt_id = $promptId ?: null;
            $account->enable_account_knowledge = $knowledgeIds ? 1 : 0;
            $account->save();

            AccountKnowledgeRel::query()->where('social_account_id', $account->id)->delete();
            foreach ($knowledgeIds as $kid) {
                AccountKnowledgeRel::create([
                    'social_account_id' => $account->id,
                    'knowledge_id' => $kid,
                ]);
            }

            return $this->getConfig($account->fresh());
        });
    }

    /** 供爬虫调用：完整合并配置（含解密后的 api_key，调用方负责销毁） */
    public function resolveForCrawler(SocialAccount $account): array
    {
        return AiConfigResolver::resolveForAccount($account);
    }

    private function resolvedPreview(SocialAccount $account): array
    {
        $full = AiConfigResolver::resolveForAccount($account);
        $params = $full['params'];
        unset($params['api_key']); // 预览禁止回传 Key
        $params['hasApiKey'] = !empty($full['params']['api_key']);

        return [
            'source' => $full['source'],
            'params' => $params,
            'prompt' => $full['prompt'] ? [
                'id' => $full['prompt']['id'],
                'name' => $full['prompt']['name'],
                'source' => $full['prompt']['source'],
            ] : null,
            'knowledgeCount' => count($full['knowledge_docs']),
            'knowledgeSource' => $full['knowledge_source'],
        ];
    }
}
