<?php

namespace Database\Seeders;

use App\Models\AiParamTemplate;
use App\Models\AiPromptTemplate;
use App\Models\KnowledgeDoc;
use App\Models\Tenant;
use App\Support\AesCrypto;
use Illuminate\Database\Seeder;

/**
 * AI 配置中心演示数据：按租户套餐分配参数模板 / Prompt / 知识库
 * 执行：php artisan db:seed --class=AiConfigDemoSeeder
 */
class AiConfigDemoSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = Tenant::query()->orderBy('id')->get();
        if ($tenants->isEmpty()) {
            $this->command?->warn('无租户数据，请先执行 DatabaseSeeder');

            return;
        }

        foreach ($tenants as $tenant) {
            $this->seedForTenant($tenant);
        }

        // 平台全局 Key（演示用，AES 加密）
        $global = \App\Models\AiParamGlobal::current();
        if ($global && empty($global->encrypt_api_key)) {
            $global->encrypt_api_key = AesCrypto::encrypt('sk-demo-platform-global-key');
            $global->ai_model = 'gpt-4o-mini';
            $global->api_base_url = 'https://api.openai.com/v1';
            $global->save();
        }

        $this->command?->info('AI 配置演示数据已写入各租户');
    }

    private function seedForTenant(Tenant $tenant): void
    {
        $package = $tenant->package ?: 'basic';

        // ----- AI 参数模板（配置中心可建任意等级；按租户准备多套） -----
        $paramDefs = match ($package) {
            'ent' => [
                ['name' => '基础-评论生成', 'level' => 1, 'model' => 'gpt-4o-mini', 'quota' => 3000, 'default' => false],
                ['name' => '专业-意向打分', 'level' => 2, 'model' => 'gpt-4o', 'quota' => 8000, 'default' => false],
                ['name' => '企业-私信问答', 'level' => 3, 'model' => 'gpt-4o', 'quota' => 20000, 'default' => true],
                ['name' => '企业-高并发采集', 'level' => 3, 'model' => 'gpt-4.1-mini', 'quota' => 50000, 'default' => false],
            ],
            'pro' => [
                ['name' => '基础-评论生成', 'level' => 1, 'model' => 'gpt-4o-mini', 'quota' => 2000, 'default' => false],
                ['name' => '专业-评论润色', 'level' => 2, 'model' => 'gpt-4o-mini', 'quota' => 5000, 'default' => true],
                ['name' => '专业-意向打分', 'level' => 2, 'model' => 'gpt-4o', 'quota' => 5000, 'default' => false],
                // 企业级也可在配置中心创建，但基础/专业套餐弹窗选不中
                ['name' => '企业-预留高阶模板', 'level' => 3, 'model' => 'gpt-4o', 'quota' => 10000, 'default' => false],
            ],
            default => [
                ['name' => '基础-评论生成', 'level' => 1, 'model' => 'gpt-4o-mini', 'quota' => 1000, 'default' => true],
                ['name' => '基础-简单问答', 'level' => 1, 'model' => 'gpt-4o-mini', 'quota' => 1000, 'default' => false],
                ['name' => '专业-预留模板', 'level' => 2, 'model' => 'gpt-4o', 'quota' => 3000, 'default' => false],
            ],
        };

        $defaultParamId = null;
        foreach ($paramDefs as $def) {
            $tpl = AiParamTemplate::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'template_name' => $def['name'],
                ],
                [
                    'ai_model' => $def['model'],
                    'encrypt_api_key' => AesCrypto::encrypt('sk-demo-'.$tenant->id.'-l'.$def['level']),
                    'api_base_url' => 'https://api.openai.com/v1',
                    'temperature' => $def['level'] >= 3 ? 0.5 : 0.7,
                    'max_tokens' => $def['level'] >= 2 ? 4096 : 2048,
                    'top_p' => 1.0,
                    'daily_call_quota' => $def['quota'],
                    'is_default' => $def['default'] ? 1 : 0,
                    'template_level' => $def['level'],
                ]
            );
            if ($def['default']) {
                $defaultParamId = $tpl->id;
            }
        }

        // 保证同租户仅一个 is_default
        if ($defaultParamId) {
            AiParamTemplate::query()
                ->where('tenant_id', $tenant->id)
                ->where('id', '!=', $defaultParamId)
                ->update(['is_default' => 0]);
            $tenant->current_ai_param_template_id = $defaultParamId;
            $tenant->save();
        }

        // ----- Prompt 话术模板 -----
        $prompts = [
            [
                'name' => '国内评论生成默认模板',
                'category' => '社媒评论生成',
                'is_default' => 1,
                'role' => '角色：源头工厂、实体商家、行业从业者，在小红书、抖音、视频号评论区友好互动。',
                'rules' => "1.贴合帖子内容自然回复；\n2.口语化、简短1-2句；\n3.软互动不硬广；\n4.不留联系方式。",
            ],
            [
                'name' => '客户意向打分模板',
                'category' => '客户意向打分',
                'is_default' => 0,
                'role' => '你是资深电商顾问，根据用户评论判断代理/采购意向。',
                'rules' => "输出意向等级 high/mid/low；\n给出 0-100 分；\n一句话理由。",
            ],
            [
                'name' => '私信智能问答模板',
                'category' => '私信智能问答',
                'is_default' => 0,
                'role' => '你是品牌客服，基于知识库回答货源、代理政策问题。',
                'rules' => "礼貌简洁；\n不确定时引导留资；\n禁止承诺无法兑现的政策。",
            ],
        ];

        foreach ($prompts as $p) {
            AiPromptTemplate::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'name' => $p['name'],
                ],
                [
                    'category' => $p['category'],
                    'tag_type' => $p['category'] === '客户意向打分' ? 'success' : ($p['category'] === '私信智能问答' ? 'warning' : ''),
                    'desc' => mb_substr($p['role'], 0, 60).'...',
                    'role' => $p['role'],
                    'rules' => $p['rules'],
                    'is_default' => $p['is_default'],
                ]
            );
        }

        AiPromptTemplate::query()
            ->where('tenant_id', $tenant->id)
            ->where('name', '!=', '国内评论生成默认模板')
            ->update(['is_default' => 0]);
        AiPromptTemplate::query()
            ->where('tenant_id', $tenant->id)
            ->where('name', '国内评论生成默认模板')
            ->update(['is_default' => 1]);

        // ----- 知识库文档 -----
        $docs = match ($package) {
            'ent' => [
                ['name' => '2026夏季服装货源手册.pdf', 'size' => '2.4 MB'],
                ['name' => '企业代理政策FAQ.docx', 'size' => '860 KB'],
                ['name' => '私信话术库.txt', 'size' => '128 KB'],
                ['name' => '竞品对比与卖点.md', 'size' => '96 KB'],
            ],
            'pro' => [
                ['name' => '晨风服饰批发价目表.pdf', 'size' => '1.1 MB'],
                ['name' => '穿搭评论话术.txt', 'size' => '64 KB'],
                ['name' => '退换货说明.docx', 'size' => '210 KB'],
            ],
            default => [
                ['name' => '基础产品介绍.pdf', 'size' => '520 KB'],
                ['name' => '常见问题FAQ.txt', 'size' => '32 KB'],
            ],
        };

        foreach ($docs as $d) {
            KnowledgeDoc::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'name' => $d['name'],
                ],
                [
                    'size' => $d['size'],
                    'status' => 'ready',
                    'tags' => '演示,知识库',
                    'icon_color' => str_ends_with($d['name'], '.pdf') ? '#f56c6c' : '#409eff',
                    'file_path' => null,
                ]
            );
        }

        $this->command?->line("  ✓ 租户 #{$tenant->id} {$tenant->name}（{$package}）已分配 AI 配置");
    }
}
