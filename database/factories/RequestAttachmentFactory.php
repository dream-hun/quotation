<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Attachment;
use App\Models\PurchaseRequest;
use App\Models\RequestAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RequestAttachment> */
final class RequestAttachmentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'attachment_id' => Attachment::factory(),
            'request_id' => PurchaseRequest::factory(),
        ];
    }
}
