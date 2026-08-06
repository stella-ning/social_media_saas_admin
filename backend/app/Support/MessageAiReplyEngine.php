<?php

namespace App\Support;

use App\Models\AiPromptTemplate;
use App\Models\MessageSession;
use App\Models\SocialAccount;
use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * 消息会话 AI 自动回复引擎
 * - 账号独立 Prompt 优先（小红书）
 * - 基础版仅 Level-1 Prompt
 * - 严禁输出 AI/机器人等身份词
 * - 消耗套餐每日 AI 调用配额
 */
class MessageAiReplyEngine
{
    /** 禁止出现的身份相关词 */
    public const FORBIDDEN_IDENTITY = [
        'AI', 'ai', 'Ai',
        '智能助手', '人工智能', '机器人', '系统客服',
        '聊天机器人', '智能客服', '虚拟助手', '大模型',
        '作为AI', '我是AI', 'AI助手',
    ];

    /**
     * 生成真人风格回复（本地规则引擎 + Prompt/知识库上下文，演示可替换为真实 LLM）
     *
     * @return array{content:string,intent:string,consult_product:?string,source:string,prompt_id:?int}
     */
    public static function generate(MessageSession $session, string $visitorMsg): array
    {
        $tenantId = (int) $session->tenant_id;
        PackageQuota::assertDailyAiAvailable($tenantId);

        $account = null;
        if ($session->social_account_id) {
            $account = SocialAccount::query()->find($session->social_account_id);
        }

        // 套餐平台白名单：会话来源平台必须在允许列表
        self::assertPlatformAllowed($tenantId, (string) $session->platform);

        $resolved = AiConfigResolver::resolveForAccount($account, $tenantId);
        $prompt = $resolved['prompt'];

        // 基础版仅允许 Level-1 相关 Prompt（用 template_level 约束参数；Prompt 无等级时按套餐卡控）
        $setting = PackageQuota::settingForTenant($tenantId);
        $maxLevel = (int) $setting->max_template_level;
        if ($maxLevel <= 1 && $prompt && ($prompt['category'] ?? '') === '私信智能问答') {
            // 基础版优先使用「国内评论生成」类 Level-1 风格话术模板
            $basicPrompt = AiPromptTemplate::query()
                ->where('tenant_id', $tenantId)
                ->where('is_default', 1)
                ->first();
            if ($basicPrompt) {
                $prompt = [
                    'id' => $basicPrompt->id,
                    'name' => $basicPrompt->name,
                    'category' => $basicPrompt->category,
                    'role' => $basicPrompt->role,
                    'rules' => $basicPrompt->rules,
                    'source' => 'tenant_default_level1',
                ];
            }
        }

        $kbNames = array_column($resolved['knowledge_docs'] ?? [], 'name');
        $intent = self::detectIntent($visitorMsg);
        $product = self::detectProduct($visitorMsg, $kbNames);

        $draft = self::composeHumanReply($visitorMsg, $intent, $product, $prompt, $kbNames);
        $draft = self::stripForbiddenIdentity($draft);

        // 敏感词
        $check = SensitiveWordFilter::check($draft, $tenantId);
        if (!$check['ok']) {
            SensitiveWordFilter::logAlert($tenantId, $session->id, null, $check['hits'], $draft, 'blocked');
            throw new RuntimeException('AI 回复命中敏感词（'.implode('、', $check['hits']).'），已拦截并记入告警日志');
        }

        PackageQuota::recordAiCallAndMaybeDisableAutoReply($tenantId);

        Log::info('message.ai_reply', [
            'session_id' => $session->id,
            'tenant_id' => $tenantId,
            'account_id' => $account?->id,
            'source' => $resolved['source'],
            'prompt_id' => $prompt['id'] ?? null,
            'intent' => $intent,
            // 禁止输出全文 / key
        ]);

        // 销毁可能含 key 的结构
        unset($resolved);

        return [
            'content' => $draft,
            'intent' => $intent,
            'consult_product' => $product,
            'source' => $prompt['source'] ?? 'tenant',
            'prompt_id' => $prompt['id'] ?? null,
        ];
    }

    public static function assertPlatformAllowed(int $tenantId, string $platformLabel): void
    {
        $setting = PackageQuota::settingForTenant($tenantId);
        $allowed = $setting->allow_platforms ?: [];
        if (!$allowed) {
            return;
        }
        try {
            $code = PlatformEnum::toPythonKey(PlatformEnum::toCode($platformLabel));
        } catch (\Throwable) {
            $code = strtolower($platformLabel);
        }
        if (!in_array($code, $allowed, true)) {
            throw new RuntimeException("当前套餐不支持接收「{$platformLabel}」平台会话消息，请升级套餐");
        }
    }

    /** none / normal / high */
    public static function detectIntent(string $msg): string
    {
        $high = ['代理', '加盟', '批发', '拿货', '合作', '一件代发', '进货', '经销', '招代理', '怎么代理', '多少钱一批'];
        $normal = ['怎么卖', '价格', '多少钱', '有货吗', '详情', '规格', '发货', '运费', '链接'];
        foreach ($high as $k) {
            if (mb_stripos($msg, $k) !== false) {
                return 'high';
            }
        }
        foreach ($normal as $k) {
            if (mb_stripos($msg, $k) !== false) {
                return 'normal';
            }
        }

        return 'none';
    }

    public static function detectProduct(string $msg, array $kbNames): ?string
    {
        $cands = ['护肤品', '面膜', '祛痘', '连衣裙', '穿搭', '美妆', '货源'];
        foreach ($cands as $c) {
            if (mb_stripos($msg, $c) !== false) {
                return $c;
            }
        }
        foreach ($kbNames as $name) {
            if (preg_match('/([\x{4e00}-\x{9fa5}]{2,8})/u', (string) $name, $m)) {
                return $m[1];
            }
        }

        return null;
    }

    private static function composeHumanReply(
        string $visitorMsg,
        string $intent,
        ?string $product,
        ?array $prompt,
        array $kbNames
    ): string {
        $product = $product ?: '这款';
        $kbHint = $kbNames ? '（参考店里资料）' : '';

        if ($intent === 'high') {
            $lines = [
                "姐妹好呀，看你对{$product}挺感兴趣的～我们这边源头拿货，代理政策也简单，方便说下你在哪个城市吗？{$kbHint}",
                "收到～想做{$product}代理的话，门槛不高，我先帮你问问档期和名额，你方便留个微信或者城市不？",
                "好的好的，{$product}现在有一波货源，适合想长期做的。你大概打算从哪个渠道出货呀？",
            ];
        } elseif ($intent === 'normal') {
            $lines = [
                "在的呀，{$product}现在有现货，发货也快。你想了解价格还是规格呀？",
                "哈喽，问得挺细的～{$product}这块我给你讲讲：质量稳定，复购也不错。你更关心价还是功效？",
                "收到，{$product}相关我这边都能答。你方便说下更想看哪一块不？",
            ];
        } else {
            $lines = [
                '在的，看到消息了～你想了解哪一块，直接说就行。',
                '哈喽，刚看到。有什么想问的随时说，我帮你看看。',
                '收到啦，你慢慢问，我这边都在。',
            ];
        }

        // 结合 Prompt 规则做轻量口语约束提示（不把 AI 身份写进回复）
        $idx = abs(crc32($visitorMsg.(string) ($prompt['id'] ?? 0))) % count($lines);

        return $lines[$idx];
    }

    public static function stripForbiddenIdentity(string $text): string
    {
        $out = $text;
        foreach (self::FORBIDDEN_IDENTITY as $w) {
            $out = str_ireplace($w, '', $out);
        }
        // 常见残留
        $out = preg_replace('/我是.*?助手/u', '我是店里客服', $out) ?: $out;
        $out = trim(preg_replace('/\s{2,}/u', ' ', $out) ?: $out);

        return $out !== '' ? $out : '在的，看到消息了，你想了解哪一块直接说就行。';
    }

    /**
     * 评论区引流：生活化闲聊口吻（禁止硬广 / AI 身份词）
     */
    public static function generateCasualCommentReply(int $tenantId, string $promptHint, string $comment): string
    {
        PackageQuota::assertDailyAiAvailable($tenantId);
        $fallbacks = [
            '哈哈同感，我之前也纠结过这个问题～',
            '哇这个我也在关注，求轻点安利哈哈哈',
            '听起来不错诶，我再蹲蹲看效果',
            '懂你，最近也在找类似的，一起交流呀',
            '姐妹说得对，我收藏了先观望一阵～',
        ];
        $idx = abs(crc32($comment.$promptHint.$tenantId)) % count($fallbacks);
        $draft = self::stripForbiddenIdentity($fallbacks[$idx]);
        // 去掉 strip 里误替换的「店里客服」
        $draft = str_replace(['店里客服', '客服'], '', $draft);

        return trim($draft) !== '' ? trim($draft) : $fallbacks[0];
    }
}
