<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:ping-scheduler')]
#[Description('Proves the scheduler pipeline is wired up.')]
class PingSchedulerCommand extends Command
{
    public function handle(): int
    {
        $this->info('Scheduler is running.');

        return self::SUCCESS;
    }
}
