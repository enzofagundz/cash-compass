<?php

namespace Tests\Feature\Console\Commands;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class PingSchedulerCommandTest extends TestCase
{
    public function test_ping_scheduler_command_reports_success(): void
    {
        $this->artisan('app:ping-scheduler')
            ->expectsOutput('Scheduler is running.')
            ->assertExitCode(0);
    }

    public function test_ping_scheduler_command_is_scheduled_daily(): void
    {
        $exitCode = Artisan::call('schedule:list');

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('app:ping-scheduler', Artisan::output());
    }
}
