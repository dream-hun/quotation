<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Attachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Attachment> */
final class AttachmentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'storage_key' => 'procurement/'.Str::uuid7().'.pdf',
            'original_name' => 'specification.pdf',
            'detected_media_type' => 'application/pdf',
            'size_bytes' => 1024,
            'content_hash' => hash('sha256', 'sample file'),
            'scan_status' => 'pending',
            'uploaded_by' => User::factory(),
            'uploaded_at' => now(),
        ];
    }
}
