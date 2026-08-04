# AI 参数模板与账号级配置

## 三层优先级（高 → 低）
1. **小红书账号**自定义绑定（参数模板 / Prompt / 知识库勾选）
2. **租户默认** AI 参数模板 / 默认 Prompt / 租户全部知识库
3. **平台全局** `saas_ai_param_global`

抖音、视频号仅使用 2→3，不支持账号级绑定。

## 表
- `saas_ai_param_template`：租户多套模型参数（API-Key AES）
- `saas_ai_param_global`：平台默认
- `saas_social_account.bind_*`：账号绑定字段
- `saas_account_knowledge_rel`：账号知识库关联（删账号级联）
- `ai_prompt_templates.is_default`：租户默认话术

## 核心接口
| 方法 | 路径 |
|------|------|
| GET | `/api/tenant/{tenantId}/ai-param-template-list` |
| POST | `/api/tenant/ai-param-template-save` |
| POST | `/api/tenant/ai-param-template-set-default` |
| DELETE | `/api/tenant/ai-param-template-del` |
| GET | `/api/tenant/{tenantId}/prompt-list` |
| GET | `/api/social-account/{id}/ai-config` |
| POST | `/api/social-account/save-ai-config` |

## 爬虫取配置
```php
use App\Support\AiConfigResolver;
$config = AiConfigResolver::resolveForAccount($socialAccount);
// $config['params']['api_key'] 为明文，用完立即 unset
```

## 前端
- 租户列表「AI配置」→ `/system/ai-config?tenantId=&tab=params`
- AI配置中心新增「AI参数模板管理」Tab
- 小红书账号「账号AI配置」弹窗：`AccountAiConfigDialog.vue`
