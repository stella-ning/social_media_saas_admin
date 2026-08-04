<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 租户模型
 */
class Tenant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'contact', 'phone', 'email', 'package', 'status',
        'concurrent', 'ai_quota', 'binds', 'kb', 'remark',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'integer',
            'concurrent' => 'integer',
            'ai_quota' => 'integer',
            'binds' => 'integer',
            'kb' => 'float',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function crawlerTasks(): HasMany
    {
        return $this->hasMany(CrawlerTask::class);
    }

    public function crmLeads(): HasMany
    {
        return $this->hasMany(CrmLead::class);
    }

    /** 转为前端 camelCase 结构 */
    public function toFrontendArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'contact' => $this->contact,
            'phone' => $this->phone,
            'email' => $this->email,
            'package' => $this->package,
            'createTime' => optional($this->created_at)?->format('Y-m-d'),
            'status' => $this->status,
            'concurrent' => $this->concurrent,
            'aiQuota' => $this->ai_quota,
            'binds' => $this->binds,
            'kb' => $this->kb,
            'remark' => $this->remark,
        ];
    }
}
