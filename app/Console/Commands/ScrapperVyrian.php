<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Lib\Crawler\VyrianCrawler;

class ScrapperVyrian extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scrapper:vyrian';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get/Update electronic parts from vyrian website';

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
        $vyrianCrawler = new VyrianCrawler();
        $vyrianCrawler->setUrlParameters(['size' => 1000]);
        $vyrianCrawler->run('http://173.201.38.7/api/products/');
    }
}
