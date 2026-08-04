<?php

namespace App\Services;

use App\Models\AiPromptTemplate;
use App\Models\KnowledgeDoc;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AiConfigService
{
    public function templates(int $tenantId): array
    {
        return AiPromptTemplate::query()
            ->where('tenant_id', $tenantId)
            ->orderByDesc('id')
            ->get()
            ->map->toFrontendArray()
            ->values()
            ->all();
    }

    public function saveTemplate(array $data): AiPromptTemplate
    {
        $payload = [
            'tenant_id' => $data['tenantId'] ?? $data['tenant_id'],
            'category' => $data['category'],
            'tag_type' => $data['tagType'] ?? $data['tag_type'] ?? '',
            'name' => $data['name'],
            'desc' => $data['desc'] ?? mb_substr($data['role'] ?? $data['name'], 0, 60).'...',
            'role' => $data['role'] ?? '',
            'rules' => $data['rules'] ?? '',
        ];

        if (!empty($data['id'])) {
            $tpl = AiPromptTemplate::findOrFail($data['id']);
            $tpl->update($payload);
            return $tpl->fresh();
        }

        return AiPromptTemplate::create($payload);
    }

    public function deleteTemplate(AiPromptTemplate $tpl): void
    {
        $tpl->delete();
    }

    /** AI 测试预览（模拟生成） */
    public function testPreview(string $input): string
    {
        return '这款看起来质地挺清爽的，有没有适合敏感肌的用法建议呀？最近刚好想入一瓶日常能用的。';
    }

    public function docs(int $tenantId): array
    {
        return KnowledgeDoc::query()
            ->where('tenant_id', $tenantId)
            ->orderByDesc('id')
            ->get()
            ->map->toFrontendArray()
            ->values()
            ->all();
    }

    public function uploadDoc(array $data): KnowledgeDoc
    {
        $name = $data['name'];
        $doc = KnowledgeDoc::create([
            'tenant_id' => $data['tenantId'] ?? $data['tenant_id'],
            'name' => $name,
            'size' => $data['size'] ?? '1.0 KB',
            'status' => 'processing',
            'tags' => $data['tags'] ?? null,
            'icon_color' => str_ends_with(strtolower($name), '.pdf') ? '#f56c6c' : '#409eff',
            'file_path' => $data['filePath'] ?? null,
        ]);

        // 模拟异步入库
        $doc->update(['status' => 'ready']);

        return $doc->fresh();
    }

    public function deleteDoc(KnowledgeDoc $doc): void
    {
        $doc->delete();
    }
}
