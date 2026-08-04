<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** 账号操作日志（脱敏） */
class AccountOperationLog extends Model
{
    protected $table = 'saas_account_operation_logs';

    public $timestamps = false;

    protected $fillable = [
        'social_account_id',
        'type',
        'content',
        'logged_at',
    ];

    protected function casts(): array
    {
        return [
            'logged_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class, 'social_account_id');
    }
}
