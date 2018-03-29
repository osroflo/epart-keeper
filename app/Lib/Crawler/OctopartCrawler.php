<?php
namespace app\Lib\Crawler;

use App\Lib\Crawler\Crawler;
use App\Lib\Log\Log;
use App\Models\Part;
use App\Models\Settings;
use KHR\React\Curl\Curl;
use KHR\React\Curl\Result;

/**
 * Octoparts Crawler
 */
class OctopartCrawler extends Crawler
{
    // vyrian.com (check metadata_origin table)
    private $origin = 6;
    protected $logger = null;
    protected $page = 0;
    protected $page_file_path = 'logs/octopat_page.txt';

    public function __construct()
    {
        $this->logger = new Log('octopart Crawler', 'logs/octopart.log');
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
        if (!isset($parts->hits)) {
            $this->logger->error('The process was aborted! The main api result does not have a hits property.');
            exit;
        }

        $total_parts = $parts->hits;
        $size = $this->url_params['limit']; //$parts->limit;
        $total_pages = (int) ($total_parts / $size);
        $this->total_pages = $total_pages;

        $this->logger->info("Total parts[$total_parts], Total Pages[$total_pages]");

        // Get the items
        if (isset($parts->results)) {
            $this->updateParts($parts->results);
        } else {
            $this->logger->error('The main api result does not contains results[].');
        }

        // Get all parts by looping through all the pages. Start with page 1
        // because page 0 was done in the first call by run()
        // for ($page = 1; $page <= $total_pages; $page++) {
        //     $this->getPageResults($page);

        //     if ($page >= 10) {
        //         $this->logger->error("BREAKING LOOP INTENTIONALLY AFTER 10 PAGES");
        //         break;
        //     }
        // }
    }

    /**
     * This method was disabled becasue the octoparts api does not allow multiple requests.
     * Kept as backup just in case it can be useful.
     */
    // public function getPageResults($page)
    // {
    //     // Setup main url to make call passing parameters
    //     $this->page = $page;
    //     // Add page to the url params array
    //     $this->url_params['start'] = $page;
    //     // Compose main url
    //     $mainUrl = $this->main_url . "?" . urldecode(http_build_query($this->url_params));

    //     $this->curl->get($mainUrl, $this->getProxyOption())->then(
    //         // promise resolved, parse part page
    //         array($this, 'parsePartInfo'),
    //         // promise rejected, i.e. some error occurred
    //         function ($exception) use ($page, $mainUrl) {
    //             // Logger
    //             $this->logger->error("Error loading url. " . $this->resultGetUrl($exception->result) . ': ' . $exception->getMessage());
    //             $this->logger->error("Trying to get page: $page again...");
    //             $this->getPageResults($page);
    //         }
    //     );
    // }

    /**
     * Useful to call the api next page
     * It is commented because the octoparts api does not allow multiple calls.
     * The code is kept just in case it can be useful in the future. Also the
     * method definition is required by the abstract parent class.
     * @param  String $result The result gotten from the proxy curl
     */
    public function parsePartInfo($result)
    {
        //error_log(print_r($results, 1));
        // Logger
        //$this->logger->info(" ");
        //$this->logger->info("Loading page:{$this->page}");
        //$this->logger->info("----------------------------------------------------------");

        // Get json
        //$parts = $result->json;

        // Add/Update parts from the main api call done by run() method
        //if (isset($parts->results)) {
        //    $this->updateParts($parts->results);
        //} else {
        //    $this->logger->error("The api result from page {$this->page} does not contains results.");
        //}
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
        $contSkippedParts = 0;

        foreach ($products as $product) {
            // Get pn from api result to reuse
            $pn = (isset($product->item->mpn)) ? $product->item->mpn : "";
            // Get manufacturer
            $manufacturer = (isset($product->item->manufacturer->name)) ? $product->item->manufacturer->name : "";
            // Get Description
            $description = (isset($product->snippet)) ? $product->snippet : "";
            // Stock
            $stock = 0;
            // Price
            $prices = [];

            $offers = isset($product->item->offers) ? $product->item->offers : [];

            if (count($offers) > 1) {
                // Get quantity
                foreach ($offers as $offer) {
                    if ($offer->in_stock_quantity > 0) {
                        $stock = $offer->in_stock_quantity;
                        break;
                    }
                }

                // Get price (if price just set 1 or true)
                foreach ($offers as $offer) {
                    $prices = isset($offer->prices->USD) ? $offer->prices->USD : [];
                    if (count($prices) > 0) {
                        break;
                    }
                }
            }

            $partInfo = [
                self::PART_NUMBER => $pn,
                self::MANUFACTURER => $manufacturer,
                self::DESCRIPTION => $description,
                self::STOCK => $stock,
                self::PRICE => count($prices),
            ];

            // Sanitize part info
            $sanitizePartInfo = $this->sanitizePartInfo($partInfo);

            // Update the DB with part info
            $counters = $this->updatePartInDB($sanitizePartInfo);

            // Update counters
            $contUpdatedParts += $counters['updatedCont'];
            $contSkippedParts += $counters['skippedCount'];
            $contCreatedParts += $counters['createdCount'];

            // Reset missing attributes
            $this->missing_attributes = [];
        }

        $total = $contUpdatedParts + $contCreatedParts + $contSkippedParts;

        $octopart_settings = Settings::where('origin_id', $this->origin)->where('label', 'next')->first();
        $next = $octopart_settings->value + 1;

        // Update next page in db
        if ($next > $this->total_pages) {
            $next = 0;
        }

        $octopart_settings->value = $next;
        $octopart_settings->save();

        $this->logger->info("Total parts processed:$total. Updated[$contUpdatedParts] Created[$contCreatedParts] Skipped[$contSkippedParts]");
        $this->logger->info("----------------------------------------------------------");
    }

    public function getOrigin()
    {
        return $this->origin;
    }
}
