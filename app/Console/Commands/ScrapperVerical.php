<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Lib\Crawler\VericalCrawler;

class ScrapperVerical extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scrapper:verical';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get/Update electronic parts from verical website';

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
        // verical.com scrapping
        $vericalCrawler = new VericalCrawler();
        $vericalCrawler->setUrlParameters([
            'searchTerm' => '*',
            'maxResults' => 15,
            'format' => 'json',
            'minQFilter' => 1,
            'startIndex' => 0
        ]);
        $vericalCrawler->run('http://www.verical.com/server-webapp/api/parametricSearch');
    }
}
