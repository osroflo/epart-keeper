<?php

namespace App\Console\Commands;

use App\Lib\Crawler\OctopartCrawler;
use App\Models\Settings;
use Illuminate\Console\Command;

class ScrapperOctoparts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scrapper:octoparts {--pagenumber=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get/Update electronic parts from octoparts website';

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
        /**
         * The octoparts scrapper is called from a cron job every minute.
         * The next page to load in the api is stored in the settings table
         * database. Only one method to grab the main results is needed since
         * the octoparts api block multiple calls.
         */
        // octopart.com scrapping
        $octopartsCrawler = new OctopartCrawler();
        $octopartsCrawler->setCurlConnecttimeout(60);
        $octopartsCrawler->setCurlTimeout(60);
        // $octopartsCrawler->setLoadProxies(10);

        // Get next page number from optional argument if passed
        if (!empty($this->option('pagenumber'))) {
            $page = $this->option('pagenumber');
        } else {
            // get next page to load from db
            $octopart_settings = Settings::where('origin_id', $octopartsCrawler->getOrigin())->where('label', 'next')->first();
            $page = $octopart_settings->value;
        }

        $octopartsCrawler->setUrlParameters([
            'apikey' => env('OCTOPARTS_API_KEY'),
            'limit' => 50,
            'pretty_print' => true,
            'start' => $page,
        ]);

        $octopartsCrawler->run('http://octopart.com/api/v3/parts/search');
    }
}
