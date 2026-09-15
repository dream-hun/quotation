<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Attachment;
use App\Models\QuotationRevision;
use App\Models\RevisionAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RevisionAttachment> */
final class RevisionAttachmentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'attachment_id' => Attachment::factory(),
            'revision_id' => QuotationRevision::factory(),
        ];
    }
}
