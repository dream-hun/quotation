<?php

declare(strict_types=1);

use App\Models\User;

test('user serialization hides authentication secrets', function (): void {
    $user = new User;
    $user->setRawAttributes([
        'name' => 'Test User',
        'password' => 'hashed-password',
        'two_factor_secret' => 'secret',
        'two_factor_recovery_codes' => 'recovery-codes',
        'remember_token' => 'token',
    ]);

    expect($user->toArray())->toBe(['name' => 'Test User']);
});
