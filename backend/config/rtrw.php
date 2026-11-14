<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Frontend
    |--------------------------------------------------------------------------
    | Origin(s) of the separate SPA. Used by CORS (credentials are allowed only
    | for these origins). Comma separated.
    */
    'frontend_urls' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('FRONTEND_URL', 'http://localhost:5173'))
    ))),

    /*
    |--------------------------------------------------------------------------
    | Queues
    |--------------------------------------------------------------------------
    | Named queues so workers can be scaled/prioritised per workload:
    |   php artisan queue:work --queue=notifications,default,reports
    */
    'queues' => [
        'default' => 'default',
        'notifications' => 'notifications',
        'reports' => 'reports',
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    | Channels enabled for domain notifications. Only "database" exists today;
    | add "mail", "whatsapp" or "broadcast" here once a channel is implemented.
    */
    'notifications' => [
        'channels' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('NOTIFICATION_CHANNELS', 'database'))
        ))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Files
    |--------------------------------------------------------------------------
    | Business code never touches Storage::disk() directly; it goes through
    | App\Domain\File\Services\FileStorage which resolves disks from here.
    | Production can point FILES_PRIVATE_DISK/FILES_PUBLIC_DISK at S3-compatible
    | disks without changing any module.
    */
    'files' => [
        'private_disk' => env('FILES_PRIVATE_DISK', 'private'),
        'public_disk' => env('FILES_PUBLIC_DISK', 'public'),
        'max_kilobytes' => (int) env('FILES_MAX_KILOBYTES', 5120),

        // Allowed extension => allowed (server-detected) MIME types. The MIME type is
        // detected from file contents; the client-supplied type is never trusted.
        'allowed' => [
            'pdf' => ['application/pdf'],
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Idempotency
    |--------------------------------------------------------------------------
    */
    'idempotency' => [
        'header' => 'Idempotency-Key',
        'ttl_hours' => (int) env('IDEMPOTENCY_TTL_HOURS', 24),
    ],

];
