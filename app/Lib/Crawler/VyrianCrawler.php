<?php
namespace app\Lib\Crawler;

use App\Lib\Crawler\Crawler;
use App\Models\Part;
use React\EventLoop\Factory;
use KHR\React\Curl\Curl;
use KHR\React\Curl\Result;
use App\Lib\Log\Log;

/**
 * Vyrian crawler
 */
class VyrianCrawler extends Crawler
{
    private $origin = 3;
    private $logger = null;
    private $page = 0;

    public function __construct()
    {
        $this->logger = new Log('vyrian Crawler', 'logs/vyrian.log');
        $this->logger->setLogging(env('CRAWLER_LOG_ENABLE'));
    }

    /**
     * Parse the main call to the webpage.
     *
     * @param string $result The text that contains the website requested
     */
    public function parseMainPage($result)
    {
        // Logging
        $this->logger->info('Loaded ' . $result->getOptions()[CURLOPT_URL]);
        // Get json
        $parts = $result->json;
        // Get api response info to create loop and get all pages data.
        $total_parts = $parts->page->totalElements;
        if (isset($parts->page->totalPages)) {
            $total_pages = $parts->page->totalPages;
        } else {
            $this->logger->error('The process was aborted! The main api result does not have a totalPages property.');
            exit;
        }

        $this->logger->info("Total parts[$total_parts] Total Pages[$total_pages]");

        // Add/Update parts from the main api call done by run() method
        if (isset($parts->_embedded->products)) {
            $this->updateParts($parts->_embedded->products);
        } else {
            $this->logger->error('The main api result does not contains _embedded->products[].');
        }

        // Get all parts by looping trhough all the pages. Start with page 1
        // because page 0 was done in the first call by run()
        for ($page=1; $page <= $total_pages; $page++) {
            // Setup main url to make call passing parameters
            $this->page = $page;

            // Add page to the url params array
            $this->url_params['page'] = $page;
            // Compose main url
            $mainUrl = $this->passed_url . "?" . urldecode(http_build_query($this->url_params));

            // Make another api call to get next page of data
            $this->curl->get($mainUrl, $this->getProxyOption())->then(
                // promise resolved, parse part page
                array($this, 'parsePartInfo'),
                // promise rejected, i.e. some error occurred
                function ($exception) {
                    // Logger
                    $this->logger->error('Error loading url '.$this->resultGetUrl($exception->result).': '.$exception->getMessage());
                }
            );
        }
    }

    /**
     * Useful to call the api next page
     *
     * @param  String $result The result gotten from the proxy curl
     */
    public function parsePartInfo($result)
    {
        // Logger
        $this->logger->info(" ");
        $this->logger->info("Loading page:{$this->page}");
        $this->logger->info("----------------------------------------------------------");

        // Get json
        $parts = $result->json;

        // Add/Update parts from the main api call done by run() method
        if (isset($parts->_embedded->products)) {
            $this->updateParts($parts->_embedded->products);
        } else {
            $this->logger->error("The api result from page {$this->page} does not contains _embedded->products[].");
        }
    }

    /**
     * Add/Update parts to the main DB
     *
     * @param  array  $products Array with all the products or pn returned by the api call
     */
    private function updateParts(array $products)
    {
        $contUpdatedParts = 0;
        $contCreatedParts = 0;
        $contCompletedParts = 0;

        foreach ($products as $product) {
            // Store pn from api result to reuse
            $pn = $product->mfgPartNo;

            // Check if pn already is stored in db as incomplete
            $part_db = Part::where('part_number', $pn)
                            ->where('is_complete', false)
                            ->where('enabled', true)->first();

            // Update if part exist in db
            if ($part_db) {
                // Get missing attributes
                $missing_attributes = explode(",", $part_db->missing_attributes);

                // Remove the stock missing attribute if product has a value for qty
                if (isset($product->quantity)) {
                    $position = array_search("stock", $missing_attributes);
                    if ($position !== false) {
                        unset($missing_attributes[$position]);
                    }
                }

                // Remove the manufacturer missing attribute if product has a value for manufacturer
                if (isset($product->manufacturer)) {
                    $position = array_search("manufacturer", $missing_attributes);
                    if ($position !== false) {
                        unset($missing_attributes[$position]);
                    }
                }

                $str_missing_attributes = implode(',', $missing_attributes);
                $is_complete = count(array_filter($missing_attributes)) > 0 ? false : true;
                $part_db->manufacturer = $product->manufacturer;
                $part_db->stock = $product->quantity;
                $part_db->missing_attributes = $str_missing_attributes;
                $part_db->is_complete = $is_complete;
                $part_db->save();

                // Update counter
                $contUpdatedParts++;
                $this->logger->info("Part: $pn was updated.");


            // Insert if part does not exist in DB
            } else {
                // Skip create if PN exist in DB with attribute is_complete=true
                if (!Part::where('part_number', $pn)->where('is_complete', true)->exists()) {
                    Part::create([
                        'part_number' => $pn,
                        'manufacturer' => $product->manufacturer,
                        'stock' => $product->quantity,
                        'is_complete' => false, // Create part as incomplete becasue vyrian only provide pn, manufacturer, qty
                        'missing_attributes' => "description,price",
                        'origin' => $this->origin,
                    ]);

                    // Update counter
                    $contCreatedParts++;

                    // Logging
                    $this->logger->info("Part:$pn was CREATED as incomplete.");
                } else {
                    // Logging
                    $contCompletedParts++;
                    $this->logger->info("Skip because Part: $pn is complete!");
                }
            }
        }
        $total = $contUpdatedParts + $contCreatedParts + $contCreatedParts;

        $this->logger->info("Total parts processed:$total. Updated[$contUpdatedParts] Created[$contCreatedParts] Skipped[$contCompletedParts]");
        $this->logger->info("----------------------------------------------------------");
    }
}
