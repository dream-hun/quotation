<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Attachment;
use App\Models\ClarificationMessage;
use App\Models\MessageAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MessageAttachment> */
final class MessageAttachmentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'attachment_id' => Attachment::factory(),
            'message_id' => ClarificationMessage::factory(),
        ];
    }
}
