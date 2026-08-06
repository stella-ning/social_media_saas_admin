<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** 敏感词 saas_sensitive_words（tenant_id null = 平台全局） */
class SensitiveWord extends Model
{
    protected $table = 'saas_sensitive_words';

    protected $fillable = ['tenant_id', 'word', 'level'];

    public function toFrontendArray(): array
    {
        return [
            'id' => $this->id,
            'tenantId' => $this->tenant_id,
            'word' => $this->word,
            'level' => $this->level,
            'scope' => $this->tenant_id ? 'tenant' : 'global',
        ];
    }
}
