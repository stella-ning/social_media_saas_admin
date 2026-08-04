<?php

namespace App\Services;

use App\Models\CrmLead;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CrmLeadService
{
    public function list(array $filters, ?int $scopeTenantId = null): LengthAwarePaginator
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $size = min(100, max(1, (int) ($filters['size'] ?? 10)));

        $query = CrmLead::query()->with('tenant')->orderByDesc('id');

        if ($scopeTenantId) {
            $query->where('tenant_id', $scopeTenantId);
        } elseif (!empty($filters['tenant'])) {
            $query->whereHas('tenant', fn ($q) => $q->where('name', $filters['tenant']));
        }

        if (!empty($filters['keyword'])) {
            $kw = $filters['keyword'];
            $query->where(function ($q) use ($kw) {
                $q->where('nickname', 'like', "%{$kw}%")->orWhere('phone', 'like', "%{$kw}%");
            });
        }

        if (!empty($filters['intent'])) {
            $query->where('intent', $filters['intent']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate($size, ['*'], 'page', $page);
    }

    public function tag(CrmLead $lead, array $data): CrmLead
    {
        $level = $data['level'] ?? $data['intent'] ?? $lead->intent;
        $scoreMap = ['high' => 95, 'mid' => 80, 'low' => 60];

        $lead->update([
            'intent' => $level,
            'score' => $scoreMap[$level] ?? $lead->score,
            'status' => $data['status'] ?? $lead->status,
            'tags' => $data['tags'] ?? $lead->tags,
            'remark' => $data['remark'] ?? $lead->remark,
        ]);

        return $lead->fresh('tenant');
    }

    public function export(array $filters, ?int $scopeTenantId = null): StreamedResponse
    {
        $filters['size'] = 10000;
        $rows = $this->list($filters, $scopeTenantId);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', '昵称', '电话', '渠道', '租户', '意向分', '状态', '跟进人']);
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r->id, $r->nickname, $r->phone, $r->channel,
                    $r->tenant?->name, $r->score, $r->status, $r->follower,
                ]);
            }
            fclose($out);
        }, 'crm_leads_'.date('YmdHis').'.csv');
    }
}
