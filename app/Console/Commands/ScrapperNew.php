<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Lib\Crawler\OemstradeCrawler;

class ScrapperNew extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scrapper:new';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get new electronic parts';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // oemstrade.com scrapping
        $oemstradeCrawler = new OemstradeCrawler();
        $oemstradeCrawler->run('http://www.oemstrade.com/');
    }
}
