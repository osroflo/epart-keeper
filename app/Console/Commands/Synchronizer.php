<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Manufacturer;
use App\Models\Part;
use App\Lib\Log\Log;

class Synchronizer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'synchronizer:table {--name=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize database to update tables used for reporting';

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
        // Set logger
        $logger = new Log('Syncnronizer', 'logs/synchronizer.log');
        $logger->setLogging(true);

        $table = !empty($this->option('name')) ? $this->option('name') : false;

        // If option is passed means that it should run a scrapper by pn instead of getting all
        if ($table) {
            $logger->info("Starting synchronization for table:$table");

            // Main logic to synchronize table
            switch ($table) {
                case 'manufacturers':
                    // Get all distinct manufacturers
                    $logger->info("Getting distinct $table from table parts");
                    $parts = Part::distinct()->select('manufacturer')->get();

                    $logger->info("total parts:" . count($parts));
                    if (count($parts) > 0) {
                        // Truncate table
                        $logger->info("Truncating table:manufacturers to have only new manufacturers.");
                        Manufacturer::truncate();

                        $logger->info("Inserting new manufacturers in the table:$table.");
                        foreach ($parts as $part) {
                            Manufacturer::insert(['label'=>$part->manufacturer]);
                        }
                    }
                    $logger->info("Synchronization process done.");
                    $logger->info("-----------------------------");
                    break;

                default:
                    $logger->error("Table $table does not allowed!");
                    break;
            }


        } else {
            echo "No table name option was passed";
        }
    }
}
