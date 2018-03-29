<?php
namespace app\Lib\Crawler;

use App\Lib\Crawler\Crawler;
use App\Models\Part;
use React\EventLoop\Factory;
use KHR\React\Curl\Curl;
use KHR\React\Curl\Result;
use Sunra\PhpSimple\HtmlDomParser;
use App\Lib\Log\Log;

/**
 * Once source crawler
 */
class OneSourceComponentsCrawler extends Crawler
{
    private $origin = 5;
    protected $logger = null;

    public function __construct()
    {
        $this->logger = new Log('1sourcecomponents Crawler', 'logs/1sourcecomponents.log');
        $this->logger->setLogging(env('CRAWLER_LOG_ENABLE'));
    }

    /**
     * Parse the main call to the webpage.
     *
     * @param string $result The text that contains the website requested
     */
    public function parseMainPage($result)
    {
        $this->logger->info('Loaded ' . $result->getOptions()[CURLOPT_URL]);

        $links = [];
        $dom = HtmlDomParser::str_get_html($result);

        if (method_exists($dom, 'find')) {
            $links = $dom->find('#suckertree1 a');

            foreach ($links as $a) { // look for links to Part Types category pages
                // This line is for debug purposes
                $href = trim($a->href); // Add trim to avoid curl Recv failure: Connection reset by peer

                // Get part information
                $this->curl->get($href, $this->getProxyOption())->then(
                    // promise resolved, parse part page
                    array($this, 'parsePartInfo'),
                    // promise rejected, i.e. some error occurred
                    function ($exception) use ($result) {
                        $this->logger->error('Error loading url '.$this->resultGetUrl($exception->result).': '.$exception->getMessage());
                        $this->logger->error('Trying again...');
                        $this->parseMainPage($result);
                    }
                );
            }

            $dom->clear();
        } else {
            $this->logger->info("DOM is empty!");
        }
    }

    /**
     * Parse the part info webpage to get the part info
     *
     * @param  String $result The html text gotten from the proxy curl
     */
    public function parsePartInfo($result)
    {
        // Logging
        $this->logger->info('Part info successfully loaded ' . $this->resultGetUrl($result));

        $dom = HtmlDomParser::str_get_html($result);
        $parts = [];
        $rowData = [];

        if (method_exists($dom, 'find')) {
            // get all rows of parts and insert/update database
            $rows = $dom->find('.productListing tr[class^="productListing-"]');

            foreach ($rows as $row) {
                // Initialize values
                $partNumber = '';
                $manufacturer = '';
                $description = '';
                $stock = '';
                $price = '';

                // Getting values
                if (method_exists($row, 'find')) {
                    $columns = $row->find('.productListing-data');

                    if ($columns) {
                        $manufacturer = trim(html_entity_decode($columns[2]->plaintext));
                        $partNumber = trim(html_entity_decode($columns[3]->plaintext));
                        $description = trim(html_entity_decode($columns[4]->plaintext));
                    }
                } else {
                    $this->logger->error('Row has not a method find.');
                }

                // Get part details
                $rowData[self::PART_NUMBER] = $this->sanitizePartnumber($partNumber);
                $rowData[self::MANUFACTURER] = $this->sanitizeManufacturer($manufacturer);
                $rowData[self::DESCRIPTION] = $this->sanitizeDescription($description);
                $rowData[self::STOCK] = $this->sanitizeStock($stock);
                $rowData[self::PRICE] = $this->sanitizePrice($price);

                // Double check if array contaisn data to avoid problems. Teh website could provide or not
                // all expected information. Also mark information not available on site as missing attributes.
                $rowData = $this->sanitizeRowData($rowData);

                // Check if pn was stored in array
                if (!empty($rowData[self::PART_NUMBER])) {
                    // Getting current missing attributes as comma separated string to store later
                    $str_missing_attributes = implode(',', $this->missing_attributes);
                    // Define if is_complete is true or false based on missing attribures array
                    $is_complete = count(array_filter($this->missing_attributes)) > 0 ? false : true;
                    $missing_attributes_count = count(array_filter($this->missing_attributes));
                    // Check if pn already is stored in db as incomplete
                    $part_db = Part::where('part_number', $rowData[self::PART_NUMBER])
                                    ->where('is_complete', false)
                                    ->where('enabled', true)->first();

                    // Update if part exist in db
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
                                // Logging
                                $this->logger->info("Part was updated.");
                            } else {
                                $this->logger->info("Skipped because missing attributes are greater than DB.");
                            }
                        }


                    // Insert if part does not exist in DB
                    } else {
                        // Skip create if PN exist in DB with attribute is_complete=true
                        if (!Part::where('part_number', $rowData[self::PART_NUMBER])->where('is_complete', true)->exists()) {
                            Part::create([
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
                            $logMessage = ($is_complete) ? 'Part was CREATED as complete.' : "Part was CREATED as incomplete.";
                            $this->logger->info($logMessage);
                        } else {
                            $this->logger->info('Skip because PN is complete!');
                        }
                    }
                }

                // Reset loop variables
                $this->missing_attributes = [];
                $parts[] = $rowData;
                $rowData = [];

                // Logging
                $this->logger->info("Total parts:" . count($parts));
                $this->logger->info("--------------------------------------------");

                // Reset main variables/properties
                $parts = [];
            }

            // Go to next page, if one exists
            $nextPage = $dom->find('.pageResults[title=" Next Page "]');

            if ($nextPage) {
                // Get href
                $href = trim($nextPage[0]->href);

                $this->curl->get($href, $this->getProxyOption())->then(
                    // promise resolved, parse part page
                    array($this, 'parsePartInfo'),
                    // promise rejected, i.e. some error occurred
                    function ($exception) use ($result) {
                        // Logger
                        $this->logger->error('Error loading url '.$this->resultGetUrl($exception->result).': '.$exception->getMessage());
                        $this->logger->error('Trying again...');
                        $this->parsePartInfo($result);
                    }
                );
            }

            // Clear
            $dom->clear();
        } else {
            // Logging
            $this->logger->info("DOM is empty!");
        }
    }
}
