<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Preview Cache Disk
    |--------------------------------------------------------------------------
    |
    | Disk used to store generated preview PDFs. This should be private and
    | must never be exposed directly through public URLs.
    |
    */

    'preview_disk' => env('DOCUMENT_PREVIEW_DISK', 'private'),

    /*
    |--------------------------------------------------------------------------
    | Preview Cache Directory
    |--------------------------------------------------------------------------
    |
    | Base directory inside the preview disk where generated PDF previews
    | will be stored.
    |
    */

    'preview_directory' => env('DOCUMENT_PREVIEW_DIRECTORY', 'previews'),

    /*
    |--------------------------------------------------------------------------
    | Preview Cache TTL (Days)
    |--------------------------------------------------------------------------
    |
    | Generated previews older than this threshold, based on last access,
    | may be deleted by the cleanup command. Previews can be regenerated
    | automatically on the next preview request.
    |
    */

    'cache_ttl_days' => (int) env('DOCUMENT_PREVIEW_CACHE_TTL_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Office Extensions Eligible for Conversion
    |--------------------------------------------------------------------------
    |
    | Only these file types will be converted to PDF for secure inline preview.
    |
    */

    'office_extensions' => [
        'doc',
        'docx',
        'xls',
        'xlsx',
        'ppt',
        'pptx',
    ],

    /*
    |--------------------------------------------------------------------------
    | Direct Preview MIME Types
    |--------------------------------------------------------------------------
    |
    | Files with these MIME types may be previewed directly without conversion.
    |
    */

    'direct_preview_mimes' => [
        'application/pdf',
    ],

    /*
    |--------------------------------------------------------------------------
    | LibreOffice Binary
    |--------------------------------------------------------------------------
    |
    | Path or command name for the LibreOffice/soffice executable used for
    | headless document conversion.
    |
    */

    'soffice_binary' => env('LIBREOFFICE_BINARY', 'soffice'),

    /*
    |--------------------------------------------------------------------------
    | Conversion Timeout
    |--------------------------------------------------------------------------
    |
    | Maximum number of seconds allowed for a single Office-to-PDF conversion.
    |
    */

    'conversion_timeout' => (int) env('DOCUMENT_PREVIEW_CONVERSION_TIMEOUT', 120),

    /*
    |--------------------------------------------------------------------------
    | Preview Generation Locking
    |--------------------------------------------------------------------------
    |
    | These settings control Redis-based locking so multiple users cannot
    | generate the same preview simultaneously.
    |
    */

    'lock_timeout' => (int) env('DOCUMENT_PREVIEW_LOCK_TIMEOUT', 180),

    'lock_wait_seconds' => (int) env('DOCUMENT_PREVIEW_LOCK_WAIT_SECONDS', 15),

    'lock_poll_interval_ms' => (int) env('DOCUMENT_PREVIEW_LOCK_POLL_INTERVAL_MS', 250),

    /*
    |--------------------------------------------------------------------------
    | Global Office Conversion Limit
    |--------------------------------------------------------------------------
    |
    | These settings protect the server from too many simultaneous LibreOffice
    | conversions across different documents.
    |
    */

    'global_conversion_limit_enabled' => env('DOCUMENT_PREVIEW_GLOBAL_LIMIT_ENABLED', true),

    'global_conversion_lock_key' => env(
        'DOCUMENT_PREVIEW_GLOBAL_LOCK_KEY',
        'office-preview-global-conversion'
    ),

    'global_conversion_lock_timeout' => (int) env(
        'DOCUMENT_PREVIEW_GLOBAL_LOCK_TIMEOUT',
        180
    ),

    'global_conversion_wait_seconds' => (int) env(
        'DOCUMENT_PREVIEW_GLOBAL_WAIT_SECONDS',
        30
    ),
];