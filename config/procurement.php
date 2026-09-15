<?php

declare(strict_types=1);

return [
    'require_quotation_attachment' => (bool) env('PROCUREMENT_REQUIRE_QUOTATION_ATTACHMENT', false),
    'scanner_binary' => env('PROCUREMENT_SCANNER_BINARY', ''),
];
