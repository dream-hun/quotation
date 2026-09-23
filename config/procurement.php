<?php

declare(strict_types=1);

return [
    'require_quotation_attachment' => (bool) env('PROCUREMENT_REQUIRE_QUOTATION_ATTACHMENT', false),
    'scanner_binary' => env('PROCUREMENT_SCANNER_BINARY', ''),
    'max_upload_kilobytes' => (int) env('PROCUREMENT_MAX_UPLOAD_KILOBYTES', 10240),
    'upload_chunk_kilobytes' => 1024,
    'max_pending_uploads' => 5,
];
