<?php

namespace App\Support;

/**
 * 社媒平台枚举：库内存数字，接口/前端用中文名
 */
class PlatformEnum
{
    public const XHS = 1;
    public const DOUYIN = 2;
    public const CHANNELS = 3;

    public const MAP = [
        self::XHS => '小红书',
        self::DOUYIN => '抖音',
        self::CHANNELS => '视频号',
    ];

    public static function toCode(string|int $platform): int
    {
        if (is_numeric($platform)) {
            $code = (int) $platform;
            if (isset(self::MAP[$code])) {
                return $code;
            }
        }

        $name = trim((string) $platform);
        $flip = array_flip(self::MAP);
        if (!isset($flip[$name])) {
            throw new \InvalidArgumentException('不支持的平台：'.$name);
        }

        return $flip[$name];
    }

    public static function toLabel(int $code): string
    {
        return self::MAP[$code] ?? (string) $code;
    }

    /** 传给 Python 服务的英文标识 */
    public static function toPythonKey(int $code): string
    {
        return match ($code) {
            self::XHS => 'xiaohongshu',
            self::DOUYIN => 'douyin',
            self::CHANNELS => 'channels',
            default => throw new \InvalidArgumentException('未知平台'),
        };
    }

    /** 任意 key/中文/数字 → 中文展示名 */
    public static function labelFromAny(mixed $platform): string
    {
        if ($platform === null || $platform === '') {
            return '未知平台';
        }
        if (is_numeric($platform)) {
            return self::toLabel((int) $platform);
        }
        $key = strtolower(trim((string) $platform));
        return match ($key) {
            'xiaohongshu', 'xhs', '小红书' => '小红书',
            'douyin', '抖音' => '抖音',
            'channels', '视频号', 'wechat_channels' => '视频号',
            default => in_array((string) $platform, self::MAP, true)
                ? (string) $platform
                : (string) $platform,
        };
    }
}
