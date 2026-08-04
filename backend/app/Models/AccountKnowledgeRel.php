<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 账号-知识库关联 saas_account_knowledge_rel
 * 删除账号时级联删除
 */
class AccountKnowledgeRel extends Model
{
    protected $table = 'saas_account_knowledge_rel';

    protected $fillable = [
        'social_account_id',
        'knowledge_id',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class, 'social_account_id');
    }

    public function knowledge(): BelongsTo
    {
        return $this->belongsTo(KnowledgeDoc::class, 'knowledge_id');
    }
}
