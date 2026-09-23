<?php

declare(strict_types=1);

namespace App\Actions;

use App\Concerns\ProcurementGuard;
use App\Concerns\UploadChunks;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

final readonly class StoreUploadChunk
{
    public function handle(User $actor, string $uploadUuid, int $chunkIndex, UploadedFile $chunk): void
    {
        $disk = Storage::disk(UploadChunks::DISK);
        $directory = UploadChunks::directory($actor, $uploadUuid);
        if (! $disk->directoryExists($directory)) {
            ProcurementGuard::require(count($disk->directories(UploadChunks::userDirectory($actor))) < config()->integer('procurement.max_pending_uploads'),
                'Too many uploads are in progress. Finish or wait for them before starting another.', 'chunk');
        }

        ProcurementGuard::require($disk->putFileAs($directory, $chunk, (string) $chunkIndex) !== false, 'Unable to store this file. Try again.', 'chunk');
    }
}
