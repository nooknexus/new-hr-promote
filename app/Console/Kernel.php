<?php

namespace App\Console;

use App\Console\Commands\CleanTempFiles;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        CleanTempFiles::class,
    ];

    protected function schedule(Schedule $schedule)
    {
        // ตั้งเวลาลบไฟล์ PDF ทุกวันเวลา 01:00 น.
        $schedule->command('temp:clean-pdf --days=1')->dailyAt('01:00');
    }

    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}