<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IndustryPrompt extends Model
{
    protected $table = 'industry_prompt';

    protected $fillable = [
        'title', 'industry', 'content', 'min_package_type',
        'template_level', 'is_published', 'sort',
    ];

    protected function casts(): array
    {
        return [
            'min_package_type' => 'integer',
            'template_level' => 'integer',
            'is_published' => 'integer',
            'sort' => 'integer',
        ];
    }

    public function toFrontendArray(int $tenantPackageType = 1): array
    {
        $unlocked = $tenantPackageType >= (int) $this->min_package_type;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'industry' => $this->industry,
            'content' => $unlocked ? $this->content : mb_substr($this->content, 0, 40).'…',
            'minPackageType' => (int) $this->min_package_type,
            'minPackageLabel' => PackageSetting::LABEL_MAP[(int) $this->min_package_type] ?? '',
            'templateLevel' => (int) $this->template_level,
            'unlocked' => $unlocked,
            'sort' => (int) $this->sort,
        ];
    }
}
