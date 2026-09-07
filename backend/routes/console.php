<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Permanently remove questionnaires whose trash recovery window has elapsed.
Schedule::command('shezen:purge-questionnaires')->dailyAt('03:15');
