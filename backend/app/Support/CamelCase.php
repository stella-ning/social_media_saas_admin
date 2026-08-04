<?php

namespace App\Support;

/**
 * 数组键名转 camelCase（适配前端 Vue mock 字段）
 */
class CamelCase
{
    public static function convert(mixed $data): mixed
    {
        if (is_array($data)) {
            $out = [];
            foreach ($data as $key => $value) {
                $newKey = is_string($key) ? self::toCamel($key) : $key;
                $out[$newKey] = self::convert($value);
            }

            return $out;
        }

        if (is_object($data)) {
            if (method_exists($data, 'toArray')) {
                return self::convert($data->toArray());
            }

            return self::convert((array) $data);
        }

        return $data;
    }

    public static function toCamel(string $key): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $key))));
    }
}
