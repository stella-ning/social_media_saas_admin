<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * 列表查询辅助：统一 page/size/keyword/status 筛选
 */
class ListQuery
{
    /**
     * @return array{page:int,size:int}
     */
    public static function pageParams(Request $request): array
    {
        $page = max(1, (int) $request->input('page', 1));
        $size = min(100, max(1, (int) $request->input('size', 10)));

        return compact('page', 'size');
    }

    /**
     * 应用关键词模糊搜索
     */
    public static function applyKeyword(Builder $query, ?string $keyword, array $columns): Builder
    {
        $keyword = trim((string) $keyword);
        if ($keyword === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($keyword, $columns) {
            foreach ($columns as $i => $col) {
                $i === 0
                    ? $q->where($col, 'like', "%{$keyword}%")
                    : $q->orWhere($col, 'like', "%{$keyword}%");
            }
        });
    }

    /**
     * 分页结果转为前端友好结构（camelCase 由 Resource/Service 负责）
     *
     * @return array{list:array,total:int,page:int,size:int}
     */
    public static function paginate(Builder $query, Request $request): array
    {
        ['page' => $page, 'size' => $size] = self::pageParams($request);
        $paginator = $query->paginate($size, ['*'], 'page', $page);

        return [
            'list' => $paginator->items(),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'size' => $paginator->perPage(),
        ];
    }
}
