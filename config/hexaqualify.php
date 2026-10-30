<?php

return [
    'allowed_domains' => array_filter(array_map('trim', explode(',', env('HQ_ALLOWED_DOMAINS', 'hexamatics.com')))),

    // First admin, created by the seeder so someone can sign in and add other staff.
    'bootstrap_admin' => env('HQ_BOOTSTRAP_ADMIN'),

    'default_expiry_days' => (int) env('HQ_DEFAULT_EXPIRY_DAYS', 14),
    'sender_name' => env('MAIL_FROM_NAME', 'HexaQualify · Hexamatics'),
    'reply_to' => env('HQ_REPLY_TO', 'procurement@hexamatics.com'),
    'retention_years' => (int) env('HQ_RETENTION_YEARS', 3),

    'otp' => [
        'ttl_minutes' => 10,
        'max_attempts' => 5,
        'lock_minutes' => 15,
    ],

    'ai' => [
        'enabled' => (bool) env('HQ_AI_SCORING', true),
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('HQ_AI_MODEL', 'claude-sonnet-5-5'),
        'endpoint' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com').'/v1/messages',
        'timeout' => 30,
    ],

    // Bearer token for /ops/* endpoints (Vercel Cron sends CRON_SECRET automatically).
    'cron_secret' => env('CRON_SECRET'),

    // Allows the "Sign in as" form on the login page. Never enable in production.
    'dev_login' => (bool) env('HQ_DEV_LOGIN', false),

    'company' => [
        'legal_name' => 'Hexamatics Nepal Private Limited',
        'address' => 'Kumari Marg – 1, Bhagwati Bahal, Naxal, Kathmandu, Nepal',
        'website' => 'www.hexabusiness.com',
    ],
];
