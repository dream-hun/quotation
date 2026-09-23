<?php

declare(strict_types=1);

namespace App\Actions;

use App\Concerns\UploadChunks;
use Illuminate\Support\Facades\Storage;

final readonly class PruneUploadChunks
{
    /** Delete chunked uploads that were abandoned for more than a day. */
    public function handle(): int
    {
        $disk = Storage::disk(UploadChunks::DISK);
        $cutoff = now()->subDay()->getTimestamp();
        $pruned = 0;
        foreach ($disk->directories(UploadChunks::ROOT) as $userDirectory) {
            foreach ($disk->directories($userDirectory) as $upload) {
                if (array_all($disk->files($upload), fn (string $chunk): bool => $disk->lastModified($chunk) < $cutoff)) {
                    $disk->deleteDirectory($upload);
                    $pruned++;
                }
            }
        }

        return $pruned;
    }
}
