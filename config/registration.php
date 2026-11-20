<?php

declare(strict_types=1);

return [
    // 一つの値が、Redis キーと署名付き URL の両方を駆動します (AUTH-14)。
    'ttl_minutes' => (int) env('REGISTRATION_TTL_MINUTES', 20),

    'resend_cooldown_seconds' => (int) env('REGISTRATION_RESEND_COOLDOWN_SECONDS', 60),

    //別の Redis 接続を使用する場合は、ここで指定します。デフォルトでは、Laravel のデフォルトの Redis 接続が使用されます。
    'redis_connection' => env('REGISTRATION_REDIS_CONNECTION', 'default'),
];