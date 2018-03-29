<?php
namespace app\Lib\Crawler;

use App\Lib\Crawler\Crawler;
use App\Lib\Log\Log;
use App\Models\Part;
use KHR\React\Curl\Curl;
use KHR\React\Curl\Result;
use Sunra\PhpSimple\HtmlDomParser;

/**
 * Oemstrade crawler
 */
class OemstradeCrawler extends Crawler
{
    private $origin = 1;
    private $logger = null;

    public function __construct()
    {
        $this->logger = new Log('oemstrade Crawler', 'logs/oemstrade.log');
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

        $links = [];
        $dom = HtmlDomParser::str_get_html($result);

        foreach ($dom->find('a[href^="/search/"]') as $a) {
            // look for links starting with "item"
            $href = trim($a->href);

            // Get part number from href
            $part_number = $this->getPartNumberFromHref($href);

            // Check if part number was found from href
            if ($part_number) {
                // Check if pn already exists in DB and is complete and is enabled
                $is_part_in_db = Part::where('part_number', $part_number)
                    ->where('is_complete', 'true')
                    ->where('enabled', true)->exists();

                // If it is not a complete part in db then continue
                if (!$is_part_in_db) {
                    // Logging
                    $this->logger->info("Getting $part_number");

                    // Get part information
                    $this->curl->get('http://www.oemstrade.com' . $href, $this->getProxyOption())->then(
                        // promise resolved, parse part page
                        array($this, 'parsePartInfo'),
                        // promise rejected, i.e. some error occurred
                        function ($exception) {
                            $this->logger->error('Error loading url ' . $this->resultGetUrl($exception->result) . ': ' . $exception->getMessage());
                        }
                    );
                } else {
                    $this->logger->info("Skipped because the $part_number is complete!");
                }
            }
        }

        $dom->clear();
    }

    /**
     * Parse the part info webpage to get the part info
     *
     * @param  string $result The html text gotten from the proxy curl
     */
    public function parsePartInfo($result)
    {
        // Logging
        $this->logger->info('Part info successfully loaded ' . $this->resultGetUrl($result));

        $dom = HtmlDomParser::str_get_html($result);
        $parts = [];

        // setting counters
        $contUpdatedParts = 0;
        $contCreatedParts = 0;
        $contSkippedParts = 0;

        if (method_exists($dom, 'find')) {
            // get all partnumbers based in anchor class
            foreach ($dom->find('td.td-part-number a') as $a) {
                // Logging
                $this->logger->info("part=" . trim($a->innertext));

                // get main row from anchor class
                $row = $a->parent()->parent();

                // get every cell of the row
                foreach ($row->find('td') as $cell) {
                    switch ($cell->class) {
                        case 'td-part-number':
                            $pn = $cell->plaintext;
                            break;

                        case 'td-distributor-name':
                            $manufacturer = $cell->plaintext;
                            break;

                        case 'td-description':
                            $description = $cell->plaintext;
                            break;

                        case 'td-stock':
                            $stock = $cell->plaintext;
                            break;

                        case 'td-price':
                            $price = $cell->plaintext;
                            break;

                        default:
                            # code...
                            break;
                    }
                }

                $partInfo = [
                    self::PART_NUMBER => $pn,
                    self::MANUFACTURER => $manufacturer,
                    self::DESCRIPTION => $description,
                    self::STOCK => $stock,
                    self::PRICE => $price,
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
            } // end main foreach

            $total = $contUpdatedParts + $contCreatedParts + $contSkippedParts;

            $this->logger->info("Total parts processed:$total. Updated[$contUpdatedParts] Created[$contCreatedParts] Skipped[$contSkippedParts]");
            $this->logger->info("----------------------------------------------------------");

            // Reset main variables/properties
            $parts = [];
            $dom->clear();

            // end main if
        } else {
            // Logging
            $this->logger->info("DOM is empty!");
        }
    }

    /**
     * Update part information
     *
     * @param  array  $products
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
}
