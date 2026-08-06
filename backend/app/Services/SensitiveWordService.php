<?php

namespace App\Services;

use App\Models\SensitiveWord;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class SensitiveWordService
{
    public function list(array $filters, ?int $scopeTenantId = null): LengthAwarePaginator
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $size = min(100, max(1, (int) ($filters['size'] ?? 50)));
        $q = SensitiveWord::query()->orderByDesc('id');

        $scope = $filters['scope'] ?? 'all'; // all|global|tenant
        if ($scopeTenantId) {
            // 租户：看全局 + 本租户
            if ($scope === 'global') {
                $q->whereNull('tenant_id');
            } elseif ($scope === 'tenant') {
                $q->where('tenant_id', $scopeTenantId);
            } else {
                $q->where(function ($inner) use ($scopeTenantId) {
                    $inner->whereNull('tenant_id')->orWhere('tenant_id', $scopeTenantId);
                });
            }
        } else {
            // 超管
            if ($scope === 'global') {
                $q->whereNull('tenant_id');
            } elseif ($scope === 'tenant' || !empty($filters['tenantId'])) {
                $tid = (int) ($filters['tenantId'] ?? $filters['tenant_id'] ?? 0);
                if ($tid) {
                    $q->where('tenant_id', $tid);
                } else {
                    $q->whereNotNull('tenant_id');
                }
            }
        }
        if (!empty($filters['keyword'])) {
            $q->where('word', 'like', '%'.$filters['keyword'].'%');
        }

        return $q->paginate($size, ['*'], 'page', $page);
    }

    public function create(array $data, ?int $scopeTenantId, bool $isAdmin): SensitiveWord
    {
        $word = trim((string) ($data['word'] ?? ''));
        if ($word === '') {
            throw ValidationException::withMessages(['word' => ['请输入敏感词']]);
        }
        $level = ($data['level'] ?? 'block') === 'warn' ? 'warn' : 'block';
        $tenantId = $isAdmin
            ? ($data['tenantId'] ?? $data['tenant_id'] ?? null)
            : $scopeTenantId;
        if (!$isAdmin && !$tenantId) {
            throw new RuntimeException('无法确定租户');
        }
        // 租户不可写全局词
        if (!$isAdmin) {
            $tenantId = $scopeTenantId;
        }
        if (!empty($data['asGlobal']) && $isAdmin) {
            $tenantId = null;
        }

        return SensitiveWord::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'word' => $word],
            ['level' => $level]
        );
    }

    public function delete(SensitiveWord $word, ?int $scopeTenantId, bool $isAdmin): void
    {
        if (!$isAdmin) {
            if (!$scopeTenantId || (int) $word->tenant_id !== (int) $scopeTenantId) {
                throw new RuntimeException('无权删除该敏感词');
            }
        }
        $word->delete();
    }
}
