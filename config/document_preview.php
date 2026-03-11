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
];