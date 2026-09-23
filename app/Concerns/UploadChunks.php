<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Models\User;

final class UploadChunks
{
    public const string ROOT = 'procurement-chunks';

    public const string DISK = 'local';

    /** The directory holding one user's pending chunked uploads. */
    public static function userDirectory(User $user): string
    {
        return self::ROOT.'/'.$user->uuid;
    }

    /** The directory holding the chunks of a single upload. The upload UUID must already be validated. */
    public static function directory(User $user, string $uploadUuid): string
    {
        return self::userDirectory($user).'/'.mb_strtolower($uploadUuid);
    }

    public static function maxChunks(): int
    {
        return (int) ceil(config()->integer('procurement.max_upload_kilobytes') / config()->integer('procurement.upload_chunk_kilobytes'));
    }
}
