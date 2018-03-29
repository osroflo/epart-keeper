<?php
namespace app\Lib\Crawler;

use App\Lib\Crawler\Crawler;
use App\Lib\Log\Log;
use App\Models\Octopart;
use App\Models\Settings;
use KHR\React\Curl\Curl;
use KHR\React\Curl\Result;
use \Httpful\Request as Httpful;

/**
 * Octoparts manual crawler
 */
class OctopartSeparateCrawler extends Crawler
{
    private $origin = 6;
    protected $logger = null;
    protected $page = 0;
    protected $page_file_path = 'logs/octopar_separate_page.txt';

    public function __construct()
    {
        $this->logger = new Log('octopart separate Crawler', 'logs/octopart_separate.log');
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
    }

    /**
     * Useful to call the api next page
     *
     * It is commented because the octoparts api does not allow multiple calls.
     * The code is kept just in case it can be useful in the future. Also the
     * method definition is required by the abstract parent class.
     *
     * @param  String $result The result gotten from the proxy curl
     */
    public function parsePartInfo($result)
    {

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

    /**
     * Update the part in db
     *
     * @param array $rowData
     * @return array $counter
     */
    protected function updatePartInDB(array $rowData = [])
    {

        $updatedCont = 0;
        $skippedCont = 0;
        $createdCont = 0;
        $pn = $rowData[self::PART_NUMBER];

        // Check if pn was stored in array
        if ($pn != '') {
            // Getting current missing attributes as comma separated string to store later
            $str_missing_attributes = implode(',', $this->missing_attributes);

            // Define if is_complete is true or false based on missing attribures array
            $is_complete = count(array_filter($this->missing_attributes)) > 0 ? false : true;
            $missing_attributes_count = count(array_filter($this->missing_attributes));

            // Check if pn already is stored in db as incomplete
            $part_db = Octopart::where('part_number', $pn)
                ->where('is_complete', false)
                ->where('enabled', true)->first();

            // Update If part exist in db
            if ($part_db) {
                // Get db part missing attributes if any
                $missing_attributes_db = explode(',', $part_db->missing_attributes);
                $missing_attributes_db_count = count(array_filter($missing_attributes_db));

                // Double check if the part in DB has missing attributes
                if ($missing_attributes_db_count > 0) {
                    // If missing attributes DB is greater than current missing attribute then allow update
                    // part should not be updated if current missing attributes are greater than those in DB.
                    if ($missing_attributes_db_count > $missing_attributes_count) {
                        // Check that stock
                        $part_db->manufacturer = $rowData[self::MANUFACTURER];
                        $part_db->description = $rowData[self::DESCRIPTION];
                        $part_db->stock = $rowData[self::STOCK];
                        $part_db->price = $rowData[self::PRICE];
                        $part_db->missing_attributes = $str_missing_attributes;
                        $part_db->is_complete = $is_complete;
                        $part_db->save();

                        // Set updated cont
                        $updatedCont = 1;
                        // Logging

                        $this->logger->info("Part was updated.");
                    } else {
                        $skippedCont = 1;
                        $this->logger->info("Skipped because missing attributes are greater than DB.");
                    }
                }

            // Insert If part does not exist in DB
            } else {
                // Skip create if PN exist in DB with attribute is_complete=true
                if (!Octopart::where('part_number', $rowData[self::PART_NUMBER])->where('is_complete', true)->exists()) {
                    Octopart::create([
                        'part_number' => $rowData[self::PART_NUMBER],
                        'manufacturer' => $rowData[self::MANUFACTURER],
                        'description' => $rowData[self::DESCRIPTION],
                        'stock' => $rowData[self::STOCK],
                        'price' => $rowData[self::PRICE],
                        'is_complete' => $is_complete,
                        'missing_attributes' => $str_missing_attributes,
                        'origin' => $this->origin,
                    ]);

                    // Logging
                    $createdCont = 1;
                    $logMessage = ($is_complete) ? 'Part was CREATED as complete.' : "Part was CREATED as incomplete.";
                    $this->logger->info($logMessage);
                } else {
                    // Logging
                    $skippedCont = 1;
                    $this->logger->info('Skip because PN is complete!');
                }
            }
        } else {
            $this->logger->error('The PN was not found in the array of part info!');
        }

        // Reset loop variables
        $this->missing_attributes = [];

        return [
            'updatedCont' => $updatedCont,
            'createdCount' => $createdCont,
            'skippedCount' => $skippedCont,
        ];
    }

    public function getOrigin()
    {
        return $this->origin;
    }

    /**
     * Execute crawler by passing an specific url
     *
     * @param  string $url
     */
    public function runByCurl($url)
    {
        // Get the page
        $full_url = $url . "?" . http_build_query($this->url_params);
        $response = Httpful::get($full_url)->send();

        $this->logger->info('Loaded ' . $full_url);

        // Get json
        $parts = json_decode($response->raw_body);

        // Get api response info to create loop and get all pages data.
        if (!isset($parts->hits)) {
            $this->logger->error('The process was aborted! The main api result does not have a hits property.');
            exit;
        }

        $total_parts = $parts->hits;
        $size = $this->url_params['limit'];
        $total_pages = (int) ($total_parts / $size);
        $this->total_pages = $total_pages;

        $this->logger->info("Total parts[$total_parts], Total Pages[$total_pages]");

        // Get the items
        if (isset($parts->results)) {
            $this->updateParts($parts->results);
        } else {
            $this->logger->error('The main api result does not contains results[].');
        }
    }
}
