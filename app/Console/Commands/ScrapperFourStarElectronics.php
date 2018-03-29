<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Lib\Crawler\FourStarElectronicsCrawler;

class ScrapperFourStarElectronics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scrapper:4starelectronics {--partnumber=} {--fromfile=}';

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
        
        $options_passed = false;

        // If option is passed means that it should run a scrapper by pn instead of getting all
        if (!empty($this->option('partnumber'))) {
            $fourStarCrawler = new FourStarElectronicsCrawler();
            $pn = $this->option('partnumber');
            $fourStarCrawler->runByPartNumber("http://www.4starelectronics.com/part_detail/$pn.html");
            $options_passed = true;
        }

        if (!empty($this->option('fromfile'))) {
            $path = $this->option('fromfile');
            $options_passed = true;

            // Get parts from file
            FourStarElectronicsCrawler::fromFile($path);
        }

        // If no options passed then get all parts
        if (!$options_passed){
            $fourStarCrawler = new FourStarElectronicsCrawler();
            $fourStarCrawler->run('http://www.4starelectronics.com/part-types/index.asp');
        }
    }
}
