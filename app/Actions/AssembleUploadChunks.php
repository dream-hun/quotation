<?php

declare(strict_types=1);

namespace App\Actions;

use App\Concerns\ProcurementGuard;
use App\Concerns\UploadChunks;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final readonly class AssembleUploadChunks
{
    /**
     * Join the stored chunks into a temporary file and discard the chunks.
     * The caller owns the returned file and must delete it once handled.
     */
    public function handle(User $actor, string $uploadUuid, int $totalChunks, string $fileName): UploadedFile
    {
        $disk = Storage::disk(UploadChunks::DISK);
        $directory = UploadChunks::directory($actor, $uploadUuid);
        $chunks = array_map(fn (int $index): string => $directory.'/'.$index, range(0, $totalChunks - 1));

        try {
            ProcurementGuard::require(array_all($chunks, fn (string $chunk): bool => $disk->exists($chunk)), 'The upload is incomplete. Try again.', 'file');
            ProcurementGuard::require(array_sum(array_map($disk->size(...), $chunks)) <= config()->integer('procurement.max_upload_kilobytes') * 1024,
                'The file may not be greater than '.config()->integer('procurement.max_upload_kilobytes').' kilobytes.', 'file');

            $path = tempnam(sys_get_temp_dir(), 'procurement-upload-');
            throw_if($path === false, RuntimeException::class, 'Unable to create a temporary upload file.');
            $target = fopen($path, 'wb');
            throw_if($target === false, RuntimeException::class, 'Unable to open the temporary upload file.');
            try {
                foreach ($chunks as $chunk) {
                    $source = $disk->readStream($chunk);
                    throw_if($source === null, RuntimeException::class, 'Unable to read an upload chunk.');
                    stream_copy_to_stream($source, $target);
                    fclose($source);
                }
            } finally {
                fclose($target);
            }
        } finally {
            $disk->deleteDirectory($directory);
        }

        return new UploadedFile($path, $fileName, null, UPLOAD_ERR_OK, true);
    }
}
