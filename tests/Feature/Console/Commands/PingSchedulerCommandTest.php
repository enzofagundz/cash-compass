<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;

it('reports success from the ping scheduler command', function () {
    $this->artisan('app:ping-scheduler')
        ->expectsOutput('Scheduler is running.')
        ->assertExitCode(0);
});

it('schedules the ping scheduler command daily', function () {
    $exitCode = Artisan::call('schedule:list');

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('app:ping-scheduler');
});

it('runs the ping scheduler command daily', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event) => str_contains((string) $event->command, 'app:ping-scheduler'));

    expect($events)->toHaveCount(1)
        ->and($events->first()->getExpression())->toBe('0 0 * * *');
});
