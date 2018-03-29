<?php

namespace App\Console\Commands;

use App\Lib\Crawler\OctopartSeparateCrawler;
use App\Models\Settings;
use Illuminate\Console\Command;

class ScrapperOctopartsSeparate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scrapper:octopartsseparate {--pagenumber=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get electronic parts from octoparts and save in a separate table in db';

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
        $octopartsCrawler = new OctopartSeparateCrawler();
        $octopartsCrawler->setCurlConnecttimeout(300);
        $octopartsCrawler->setCurlTimeout(300);
        $octopartsCrawler->setLoadProxies(10);

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
            'limit' => 100,
            'pretty_print' => true,
            'start' => $page,
        ]);

        // scrape with curl no proxy
        $octopartsCrawler->runByCurl('http://octopart.com/api/v3/parts/search');
    }
}
