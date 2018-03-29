<?php

namespace app\Lib\Crawler;

use App\Lib\Crawler\Crawler;
use App\Models\Part;
use React\EventLoop\Factory;
use KHR\React\Curl\Curl;
use KHR\React\Curl\Result;
use App\Lib\Log\Log;

/**
 * Verical crawler
 */
class VericalCrawler extends Crawler
{
    private $origin = 2; // verical.com (check metadata_origin table)
    private $logger = null;
    private $page = 0;

    public function __construct()
    {
        $this->logger = new Log('vyrian Crawler', 'logs/verical.log');
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
        if (isset($parts->resultsReturned)) {
            $total_parts = $parts->resultsReturned;
            $size = $parts->maxResults;

            $total_pages = (int)($total_parts / $size);
        } else {
            $this->logger->error('The process was aborted! The main api result does not have a resultsReturned property.');
            exit;
        }

        $this->logger->info("Total parts[$total_parts] Total Pages[$total_pages]");

        // Add/Update parts from the main api call done by run() method
        if (isset($parts->views)) {
            $this->updateParts($parts->views);
        } else {
            $this->logger->error('The main api result does not contains views[].');
        }

        // Get all parts by looping trhough all the pages. Start with page 1
        // because page 0 was done in the first call by run()
        for ($page=1; $page <= $total_pages; $page++) {
            // Setup main url to make call passing parameters
            $this->page = $page;

            // Add page to the url params array
            $this->url_params['startIndex'] = $page;
            // Compose main url using url decode to avoid html special characters
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
        if (isset($parts->views)) {
            $this->updateParts($parts->views);
        } else {
            $this->logger->error("The api result from page {$this->page} does not contains views[].");
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
            // Store pn from api result to reuse and reset variables
            $pn = $product->mpn;
            $quantity = 0;
            $price = 0;
            $description = '';
            $manufacturer = '';

            // Check if pn already is stored in db as incomplete
            $part_db = Part::where('part_number', $pn)
                            ->where('is_complete', false)
                            ->where('enabled', true)->first();


            // Update if part exist in db
            if ($part_db) {
                // Get missing attributes
                $missing_attributes = explode(",", $part_db->missing_attributes);

                // Remove the stock missing attribute if product has a value for qty
                if (isset($product->availableQuantity)) {
                    if ($product->availableQuantity > 0) {
                        $quantity = $product->availableQuantity;

                        $position = array_search("stock", $missing_attributes);
                        if ($position !== false) {
                            unset($missing_attributes[$position]);
                        }
                    }
                }

                // Remove the manufacturer missing attribute if product has a value for manufacturer
                if (isset($product->manufacturer)) {
                    $manufacturer = $product->manufacturer;

                    $position = array_search("manufacturer", $missing_attributes);
                    if ($position !== false) {
                        unset($missing_attributes[$position]);
                    }
                }

                // Remove the description missing attribute if product has a value for description
                if (isset($product->partDescription)) {
                    $description = $product->partDescription;

                    $position = array_search("description", $missing_attributes);
                    if ($position !== false) {
                        unset($missing_attributes[$position]);
                    }
                }

                // Remove the price missing attribute if product has a value for price
                if (isset($product->salePrice)) {
                    if ($product->salePrice > 0) {
                        $price = $this->sanitizePrice($product->salePrice);

                        $position = array_search("price", $missing_attributes);
                        if ($position !== false) {
                            unset($missing_attributes[$position]);
                        }
                    }
                }

                $str_missing_attributes = implode(',', $missing_attributes);
                $is_complete = count(array_filter($missing_attributes)) > 0 ? false : true;

                // Save
                if ($manufacturer != '') {
                    $part_db->manufacturer = $manufacturer;
                }
                if ($description != '') {
                    $part_db->description = $description;
                }

                if ($price > 0) {
                    $part_db->price = $price;
                }

                if ($quantity > 0) {
                    $part_db->stock = $quantity;
                }

                $part_db->missing_attributes = $str_missing_attributes;
                $part_db->is_complete = $is_complete;
                $part_db->save();

                // Update counter
                $contUpdatedParts++;

                $this->logger->info("Part: $pn was updated.");



            // Insert If part does not exist in DB
            } else {
                // Skip create if PN exist in DB with attribute is_complete=true
                if (!Part::where('part_number', $pn)->where('is_complete', true)->exists()) {
                    // Getting missing attributes
                    $missing_attributes = ['stock', 'manufacturer','description', 'price'];

                    if (isset($product->availableQuantity)) {
                        if ($product->availableQuantity > 0) {
                            unset($missing_attributes[0]);
                        }
                    }

                    if (isset($product->manufacturer)) {
                        if ($product->manufacturer != '') {
                            unset($missing_attributes[1]);
                        }
                    }

                    if (isset($product->partDescription)) {
                        if ($product->partDescription != '') {
                            unset($missing_attributes[2]);
                        }
                    }

                    if (isset($product->salePrice)) {
                        if ($product->salePrice > 0) {
                            unset($missing_attributes[3]);
                        }
                    }

                    $str_missing_attributes = implode(',', $missing_attributes);
                    $is_complete = count(array_filter($missing_attributes)) > 0 ? false : true;

                    Part::create([
                        'part_number' => $pn,
                        'manufacturer' => $product->manufacturer,
                        'description' => $product->partDescription,
                        'stock' => $product->availableQuantity,
                        'price' => $this->sanitizePrice($product->salePrice),
                        'is_complete' => false,
                        'missing_attributes' => $str_missing_attributes,
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
