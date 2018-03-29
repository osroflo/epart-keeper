<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Lib\Crawler\OneSourceComponentsCrawler;

class ScrapperOneSourceComponents extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scrapper:1sourcecomponents';

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
        // 1sourcecomponents.com scraping
        $oneSourceCrawler = new OneSourceComponentsCrawler();
        $oneSourceCrawler->run('http://www.1sourcecomponents.com/store/index.php');
    }
}
