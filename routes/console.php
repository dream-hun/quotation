<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

Schedule::command('procurement:process')->everyMinute()->withoutOverlapping(10);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
