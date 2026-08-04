<?php

/**
 * 角色权限常量（与前端 utils/auth.js 保持一致）
 */
return [
    'roles' => [
        'super_admin' => '超级管理员',
        'tenant_admin' => '租户管理员',
        'operator' => '业务员',
    ],

    /**
     * 角色可访问的路由 name 前缀 / 模块 key
     */
    'permissions' => [
        'super_admin' => [
            'dashboard', 'social-accounts', 'crawler-tasks', 'proxy-ips',
            'tenants', 'package-setting', 'ai-config', 'crm-leads', 'messages', 'settings', 'users',
        ],
        'tenant_admin' => [
            'dashboard', 'social-accounts', 'crawler-tasks',
            'ai-config', 'crm-leads', 'messages',
        ],
        'operator' => [
            'dashboard', 'crm-leads', 'messages',
        ],
    ],
];
