<?php
namespace app\Lib\Crawler;

use App\Lib\Crawler\Crawler;
use App\Lib\Log\Log;
use App\Models\Part;
use KHR\React\Curl\Curl;
use KHR\React\Curl\Result;
use React\EventLoop\Factory;
use Sunra\PhpSimple\HtmlDomParser;

/**
 * Four start electronics crawler
 */
class FourStarElectronicsCrawler extends Crawler
{
    private $origin = 4;
    private $logger = null;

    public function __construct()
    {
        $this->logger = new Log('4starelectronics Crawler', 'logs/4starelectronics.log');
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

        foreach ($dom->find('a[href^="/part-types/"]') as $a) {
            // look for links to Part Types category pages
            $href = trim($a->href);
            $url = $this->main_url . $href;

            // Get part information
            $this->curl->get($url, $this->getProxyOption())->then(
                // Promise resolved, parse part page
                array($this, 'parseCategoryPage'),
                // Promise rejected, i.e. some error occurred
                function ($exception) use ($result) {
                    // Logger
                    $this->logger->error('Error loading url(2) ' . $this->resultGetUrl($exception->result) . ': ' . $exception->getMessage());
                    $this->logger->error('Trying again...');
                    $this->parseMainPage($result);
                }
            );
        }

        $dom->clear();
    }

    /**
     * This is a special method to grab parts from a file. Since the
     * main process to crawl 4starelectronics is  very slow due to
     * many curl calls to get the part info.
     *
     * @param string $path
     */
    public static function fromFile($path = '/root/4starelectronics.log')
    {
        $lines = file($path);

        if (count($lines) > 0) {
            foreach ($lines as $line_number => $part) {
                $string_pn = str_replace(array("\r", "\n"), '', $part);
                echo "Getting [$line_number] $string_pn\n";
                $cmd = "php /var/www/epart-keeper/current/artisan scrapper:4starelectronics --partnumber=$string_pn";
                exec($cmd);
            }
        } else {
            echo "File is emty";
        }
    }

    /**
     * This get crawl a specific pn. Useful to crawl the parts from a file
     * using the php artisan scrapper:4starelectronic --partnumber=<the part number>
     *
     * @param  string $url The url for the part to crawl.
     */
    public function runByPartNumber($url)
    {
        // Set urls
        $this->setUrls($url);

        // Setup crawler
        $this->loadProxies($this->load_proxies); // load 10 proxies from GimmeProxy.com
        $loop = Factory::create();
        $this->curl = new Curl($loop);

        $this->curl->client->setMaxRequest($this->max_parallel_requests); // number of parallel requests
        $this->curl->client->setSleep($this->max_requests_per_time, $this->request_time, false); // make maximum 2 requests in 3 seconds
        $this->curl->client->setCurlOption([
            CURLOPT_AUTOREFERER => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/39.0.2171.95 Safari/537.36',
            CURLOPT_CONNECTTIMEOUT => $this->curl_connecttimeout,
            CURLOPT_TIMEOUT => $this->curl_timeout,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 9,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => 0,
        ]);

        // check that proxy server is working
        $this->curl->get('http://icanhazip.com/', $this->getProxyOption())->then(function ($result) {
            echo $result . "\n";
        });

        // Get part information
        $a = $this->curl->get($url, $this->getProxyOption())->then(
            // promise resolved, parse part page
            array($this, 'parsePartInfo'),
            // promise rejected, i.e. some error occurred
            function ($exception) use ($url) {
                // Logger
                $this->logger->error('Error loading url (run by pn) ' . $this->resultGetUrl($exception->result) . ': ' . $exception->getMessage());
                $this->logger->error('Trying again...');
                $this->runByPartNumber($url);
            }
        );

        $this->curl->run();
        $loop->run();
    }

    /**
     * Parse the call to the parts category page.
     *
     * @param string $result The text that contains the website requested
     */
    public function parseCategoryPage($result)
    {
        // Logging
        $this->logger->info('Loaded ' . $result->getOptions()[CURLOPT_URL]);

        $links = [];
        $dom = HtmlDomParser::str_get_html($result);

        // Look for links to paginated list of in-stock items for the category
        foreach ($dom->find('a[href*="STK"]') as $a) {
            $href = trim($a->href); // Add trim to avoid curl Recv failure: Connection reset by peer
            $url = $this->main_url . $href;

            // Get part information
            $this->curl->get($url, $this->getProxyOption())->then(
                // promise resolved, parse part page
                array($this, 'parseInStockListPage'),
                // Promise rejected, i.e. some error occurred
                function ($exception) use ($result) {
                    $this->logger->error('Error loading url (3) ' . $this->resultGetUrl($exception->result) . ': ' . $exception->getMessage());
                    $this->logger->error('Trying again...');
                    $this->parseCategoryPage($result);
                }
            );
        }

        $dom->clear();
    }

    /**
     * Parse listing of in-stock parts
     *
     * @param  String $result The text that contains the website requested
     */
    public function parseInStockListPage($result)
    {
        // Logging
        $this->logger->info('Loaded ' . $result->getOptions()[CURLOPT_URL]);

        $links = [];
        $dom = HtmlDomParser::str_get_html($result);

        foreach ($dom->find('a[href^="/part_detail/"]') as $a) {
            // look for links to Part Types category pages
            $href = trim($a->href); // Add trim to avoid curl Recv failure: Connection reset by peer
            // Get part number from href
            $part_number = $this->getPartNumberFromHref($href);
            // Check if pn already exists in DB and is complete and is enabled
            $is_part_in_db = Part::where('part_number', $part_number)
                ->where('is_complete', 'true')
                ->where('enabled', true)->exists();

            // If it is not a complete part in db then continue
            if (!$is_part_in_db) {
                $this->logger->info("Getting $part_number");
                $url = $this->main_url . $href;

                // Get part information
                $this->curl->get($url, $this->getProxyOption())->then(
                    // promise resolved, parse part page
                    array($this, 'parsePartInfo'),
                    // promise rejected, i.e. some error occurred
                    function ($exception) use ($result) {
                        // Logger
                        $this->logger->error('Error loading url (4)' . $this->resultGetUrl($exception->result) . ': ' . $exception->getMessage());
                        $this->logger->error('Trying again...');
                        $this->parseInStockListPage($result);
                    }
                );
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

        if (method_exists($dom, 'find')) {
            // get all partnumbers based in anchor class
            foreach ($dom->find('.tddetails2') as $detail) {
                // Get row
                $row = $detail->parent();

                // Reset
                $label = "";
                $value = "";

                // Getting values
                foreach ($row->find('td') as $cell) {
                    if ($cell->class == 'tddetails') {
                        $label = $cell->plaintext;
                    }

                    if ($cell->class == 'tddetails2') {
                        $value = $cell->plaintext;
                    }
                }

                // get part details
                switch ($label) {
                    case 'Mfg Part#':
                        $rowData[self::PART_NUMBER] = $this->sanitizePartnumber($value);
                        break;

                    case 'Manufacturer:':
                        $rowData[self::MANUFACTURER] = $this->sanitizeManufacturer($value);
                        // If rowData has an empty property then add it to the missing attributes array
                        $this->isRowDataEmpty($rowData[self::MANUFACTURER], self::MANUFACTURER);

                        break;

                    case 'Qty In-Stock:':
                        $rowData[self::STOCK] = $this->sanitizeStock($value);
                        // If rowData has an empty property then add it to the missing attributes array
                        $this->isRowDataEmpty($rowData[self::STOCK], self::STOCK);

                        break;

                    default:
                        break;
                }
            }

            // Double check if array contains data to avoid problems. The website could provide or not
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
                            $this->logger->info("Part was updated.");
                        } else {
                            $this->logger->info("Skipped because missing attributes are greater than DB.");
                        }
                    }

                    // Insert If part does not exist in DB
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

            // Getting additional components that start with
            $this->logger->info("Getting additional componets that start with:");

            foreach ($dom->find('a[href^="/part_detail/"]') as $a) {
                $href = trim($a->href);
                $url = $this->main_url . $href;
                $this->runByPartNumber($url);
            }

            // Reset main variables/properties
            $parts = [];
            $dom->clear();
        } else {
            $this->logger->info("DOM is empty!");
        }
    }

    /**
     * Get the PN from the url link.
     *
     * @param  string $href
     */
    public function getPartNumberFromHref($href)
    {
        preg_match('#/(\w+).html$#', $href, $matches);

        if (!empty($matches[1])) {
            $pn = $matches[1];
        } else {
            $pn = false;
        }

        return $pn;
    }
}
