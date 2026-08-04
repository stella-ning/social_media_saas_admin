<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 账号 Cookie 存储表 saas_account_cookie
 * 注意：cookie_json 含敏感信息，禁止写入业务日志
 */
class AccountCookie extends Model
{
    protected $table = 'saas_account_cookie';

    public $timestamps = false;

    protected $fillable = [
        'social_account_id',
        'cookie_json',
        'expire_status',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'expire_status' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class, 'social_account_id');
    }
}
