<?php

declare(strict_types=1);

/**
 * 配置聚合读取器
 *
 * 负责范围：
 * - 定义 config 目录中有哪些配置文件参与加载
 * - 把多个配置文件聚合成一个总配置数组
 * - 提供 app_config('a.b.c') 这种点路径读取方式
 *
 * 修改影响：
 * - 会影响整个项目读取配置的方式
 * - 如果这里漏掉某个配置文件，该文件内容将不会被系统加载
 */

if (!function_exists('app_config_files')) {
    /**
     * 返回需要参与聚合的配置文件清单。
     *
     * 键名用途：
     * - 作为 app_config() 的一级命名空间，例如 app_config('database.host')
     * 修改影响：
     * - 调整这里会影响所有配置读取路径。
     */
    function app_config_files(): array
    {
        return [
            'app' => __DIR__ . '/app.php',
            'api' => __DIR__ . '/api.php',
            'auth' => __DIR__ . '/auth.php',
            'database' => __DIR__ . '/database.php',
            'enums' => __DIR__ . '/enums.php',
            'frontend' => __DIR__ . '/frontend.php',
            'upload' => __DIR__ . '/upload.php',
        ];
    }
}

if (!function_exists('app_config_all')) {
    /**
     * 一次性加载所有配置文件，并在当前请求中缓存结果。
     *
     * 用途：
     * - 避免同一请求反复 require 配置文件
     * 修改影响：
     * - 如果某个配置文件返回的不是数组，这里会自动兜底为空数组。
     */
    function app_config_all(): array
    {
        static $config = null;
        if ($config !== null) {
            return $config;
        }

        $config = [];
        foreach (app_config_files() as $key => $path) {
            $value = is_file($path) ? require $path : [];
            $config[$key] = is_array($value) ? $value : [];
        }

        return $config;
    }
}

if (!function_exists('app_config')) {
    /**
     * 按点路径读取配置项。
     *
     * 使用示例：
     * - app_config('database.host')
     * - app_config('upload.avatar.max_size', 0)
     * - app_config() 读取全部配置
     *
     * 修改影响：
     * - 是项目内最核心的配置读取入口，注释和行为应保持稳定。
     */
    function app_config(?string $key = null, $default = null)
    {
        $config = app_config_all();
        if ($key === null || $key === '') {
            return $config;
        }

        $value = $config;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}
