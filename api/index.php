<?php

// Vercel serverless entry point (vercel-php runtime). Everything except static
// assets is routed here by vercel.json and handed to Laravel's front controller.

// The deployment filesystem is read-only apart from /tmp.
foreach (['/tmp/storage/framework/views', '/tmp/storage/framework/cache', '/tmp/storage/framework/sessions', '/tmp/storage/logs', '/tmp/storage/app'] as $dir) {
    is_dir($dir) || mkdir($dir, 0755, true);
}

require __DIR__.'/../public/index.php';
