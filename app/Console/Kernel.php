<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        \App\Console\Commands\ScrapperNew::class,
        \App\Console\Commands\ScrapperVyrian::class,
        \App\Console\Commands\ScrapperVerical::class,
        \App\Console\Commands\ScrapperFourStarElectronics::class,
        \App\Console\Commands\ScrapperOneSourceComponents::class,
        \App\Console\Commands\Synchronizer::class,
        \App\Console\Commands\ScrapperOctoparts::class,
        \App\Console\Commands\ScrapperOctopartsSeparate::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('scrapper:new')->everyTenMinutes();
        $schedule->command('synchronizer:table --name="manufacturers"')->daily();
    }
}
