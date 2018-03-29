<?php
namespace app\Lib\Crawler;

use App\Lib\Log\Log;
use App\Models\Part;
use KHR\React\Curl\Curl;
use KHR\React\Curl\Result;
use React\EventLoop\Factory;

abstract class Crawler
{
    public $missing_attributes = [];
    // Crawler
    protected $proxies = [];
    protected $parts = [];
    protected $curl = null;
    // Connection
    protected $curl_connecttimeout = 10;
    protected $curl_timeout = 10;
    protected $load_proxies = 3;
    protected $max_parallel_requests = 3;
    protected $max_requests_per_time = 3;
    protected $request_time = 3.0;
    // The base url returned by the url_parsed,
    protected $main_url;
    // The original url passed in the run() method
    protected $passed_url;
    protected $url_params = [];
    // Constants to define row data keys
    const MANUFACTURER = 'manufacturer';
    const DESCRIPTION = 'description';
    const STOCK = 'stock';
    const PART_NUMBER = 'part_number';
    const PRICE = 'price';

    /**
     * Load proxies
     * @param  integer $num The amount of concurrent connections
     */
    protected function loadProxies($num)
    {
        if (env('USE_PROXY_KEY')) {
            echo "Using proxy api key...\n";
            $proxy_url = 'http://gimmeproxy.com/api/getProxy?api_key=' . env('GIMME_PROXY_API_KEY');
        } else {
            echo "Using free proxy...\n";
            $proxy_url = 'http://gimmeproxy.com/api/getProxy';
        }

        echo "Load {$num} proxies\n";
        for ($i = 0; $i < $num; ++$i) {
            $data = json_decode(file_get_contents($proxy_url), 1);
            $this->proxies[] = $data['curl'];
        }
    }

    // Set proxy option
    protected function getProxyOption()
    {
        $key = array_rand($this->proxies);

        return [CURLOPT_PROXY => $this->proxies[$key]];
        //return [];
    }

    // Get url from result
    protected function resultGetUrl(Result $result)
    {
        return $result->getOptions()[CURLOPT_URL];
    }

    /**
     * Parse the main call to the webpage.
     *
     * @param string $result The text that contains the website requested
     */
    abstract public function parseMainPage($result);

    abstract public function parsePartInfo($result);

    /**
     * Get the PN from the url link.
     */
    public function getPartNumberFromHref($href)
    {
        $tokens = explode('/', $href);

        if (count($tokens) > 0) {
            $pn = end($tokens);
        } else {
            $pn = false;
        }

        return $pn;
    }

    /**
     * Remove un wanted words or characters from Manufactirer.
     *
     * @param string $string The string to sanitize
     *
     * @return string
     */
    public function sanitizeManufacturer($string)
    {
        // Invalid words
        $invalid_words = ['null', '?', '&nbsp;'];

        return trim(str_replace($invalid_words, '', $string));
    }

    /**
     * Remove un wanted words or characters from description.
     *
     * @param string $string The string to sanitize
     *
     * @return string
     */
    public function sanitizeDescription($string)
    {
        // Invalid words
        $invalid_words = [
            'Stock and Consignment - Pre-orders also. 1 year warranty on Stock Parts.',
            'null',
            '?',
            '&nbsp;',
        ];

        // Replace multiple spaces
        $string = preg_replace('!\s+!', ' ', $string);

        return trim(str_replace($invalid_words, '', $string));
    }

    /**
     * Remove anything that is not numeric.
     *
     * @param string $string The string that contains the stock info.
     *
     * @return string
     */
    public function sanitizeStock($string)
    {
        $stock = preg_replace('/[^0-9]/', '', trim($string));

        return ($stock != '') ? $stock : 0;
    }

    /**
     * Check if price column in the dom has at least one valid numeric
     * value. It is not needed to collect the real price, just check if
     * at minimun has one price set.
     *
     * @param string $string The string that contains the price(s) info.
     *
     * @return bool
     */
    public function sanitizePrice($string)
    {
        // Remove $
        $string = preg_replace('/[&#36;]/', '', $string);
        // Get just numbers
        $price = preg_replace('/[^0-9]/', '', trim($string));

        return (int) $price > 0 ? 1 : 0;
    }

    /**
     * Sanitize part number
     *
     * @param string $string The string that contains the part info.
     *
     * @return array
     */
    public function sanitizePartnumber($string)
    {
        $invalid_words = [
            'ROHS',
            '&#34;',
            '+',
            '|',
            ';',
            '~',
            '*',
            '%',
        ];
        // Remove invalid words
        $string = str_replace($invalid_words, '', $string);
        // Remove weird characters
        $string = preg_replace('/[?]/', '', trim($string));
        // If more than one pn is in the string split them out
        $parts = explode('D#:', $string);
        // If more than 1 part in same string just use whatever is in position 0
        return $parts[0];
    }

    /**
     * Check if it has missing attributes
     *
     * @param string $attribute_value The string that contains the attribute info.
     * @param string $attribute_key The field name of the missing attribute.
     *
     * @return array
     */
    public function isRowDataEmpty($attribute_value, $attribute_key)
    {
        if (strlen($attribute_value) <= 0 || $attribute_value == '0') {
            $this->missing_attributes[] = $attribute_key;
        }
    }

    /**
     * Set the base url and passed url from any url.
     */
    public function setUrls($passed_url)
    {
        $parse_url = parse_url($passed_url);
        // Set main url property
        $this->main_url = $parse_url["scheme"] . "://" . $parse_url["host"];
        // Set passed url (useful for api calls)
        $this->passed_url = $passed_url;
    }

    /**
     * Execute the crawling process
     *
     * @param  string  $passed_url
     * @param  boolean $set_url_params
     */
    public function run($passed_url, $set_url_params = true)
    {
        // Setup main crawler logger
        $this->crawler_logger = new Log('Crawler', 'logs/crawler.log');
        $this->crawler_logger->setLogging(env('CRAWLER_LOG_ENABLE'));

        // Set urls for future use
        $this->setUrls($passed_url);

        $this->loadProxies($this->load_proxies);
        $loop = Factory::create();
        $this->curl = new Curl($loop);

        $this->curl->client->setMaxRequest($this->max_parallel_requests);
        $this->curl->client->setSleep($this->max_requests_per_time, $this->request_time, false);
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

        // If url parameters are passed then add it to the main url
        if (count($this->url_params) > 0 && $set_url_params) {
            $passed_url = $passed_url . "?" . urldecode(http_build_query($this->url_params));
        }

        // Call $this->parseMainPage
        $this->curl->get($passed_url, $this->getProxyOption())->then(
            array($this, 'parseMainPage'),
            function ($exception) use ($passed_url) {
                $error_message = "Error loading main url: " . $this->resultGetUrl($exception->result) . ': ' . $exception->getMessage();
                $try_message = "Trying again $passed_url...";

                // Logger
                $this->crawler_logger->error($error_message);
                $this->crawler_logger->error($try_message);
                $this->crawler_logger->error("");

                // Print messages to user using CLI
                echo "\n";
                echo "$error_message\n";
                echo "$try_message\n";

                // Run the crawler again
                $this->run($passed_url, false);
            }
        );

        $this->curl->run();
        $loop->run();
    }

    /**
     * Make sure that row data have info to avoid DB errors
     */
    public function sanitizeRowData(array $rowData = [])
    {
        // Double check if array contains data to avoid problems. The website could provide or not
        // all expected information. Also mark information not available on site as missing attributes.
        if (empty($rowData[self::DESCRIPTION])) {
            $rowData[self::DESCRIPTION] = "";
            $this->isRowDataEmpty('', self::DESCRIPTION);
        }

        if (empty($rowData[self::PRICE])) {
            $rowData[self::PRICE] = 0;
            $this->isRowDataEmpty($rowData[self::PRICE], self::PRICE);
        }

        if (empty($rowData[self::STOCK])) {
            $rowData[self::STOCK] = 0;
            $this->isRowDataEmpty($rowData[self::STOCK], self::STOCK);
        }

        if (empty($rowData[self::MANUFACTURER])) {
            $rowData[self::MANUFACTURER] = "";
            $this->isRowDataEmpty("", self::MANUFACTURER);
        }

        return $rowData;
    }

    /**
     * Sets the value of curl_connecttimeout.
     *
     * @param mixed $curl_connecttimeout the curl connecttimeout
     *
     * @return self
     */
    public function setCurlConnecttimeout($curl_connecttimeout)
    {
        $this->curl_connecttimeout = $curl_connecttimeout;

        return $this;
    }

    /**
     * Sets the value of max_parallel_requests.
     *
     * @param mixed $max_parallel_requests the curl parallel requests
     *
     * @return self
     */
    public function setMaxParallelRequests($max_parallel_requests)
    {
        $this->max_parallel_requests = $max_parallel_requests;

        return $this;
    }

    /**
     * Sets the value of request_time.
     *
     * @param mixed $request_time the curl parallel seconds
     *
     * @return self
     */
    public function setRequestTime($request_time)
    {
        $this->request_time = $request_time;

        return $this;
    }

    /**
     * Sets the value of load_proxies.
     *
     * @param mixed $load_proxies the load proxies
     *
     * @return self
     */
    public function setLoadProxies($load_proxies)
    {
        $this->load_proxies = $load_proxies;

        return $this;
    }

    /**
     * Sets the value of curl_timeout.
     *
     * @param mixed $curl_timeout the curl timeout
     *
     * @return self
     */
    public function setCurlTimeout($curl_timeout)
    {
        $this->curl_timeout = $curl_timeout;

        return $this;
    }

    /**
     * Sets the value of max_requests_per_time.
     *
     * @param mixed $max_requests_per_time the max requests per time
     *
     * @return self
     */
    protected function setMaxRequestsPerTime($max_requests_per_time)
    {
        $this->max_requests_per_time = $max_requests_per_time;

        return $this;
    }

    /**
     * Set url parameters
     *
     * @param  array $params Array of parameters e.g ['size' => 20, 'page' => 0]
     */
    public function setUrlParameters($params)
    {
        $this->url_params = $params;
    }

    /**
     * Set the part info in the main array
     * also sanitize each info.
     * @param array $partInfo Array with the part info
     *                        [
     *                            'part_number' => '',
     *                            'manufacturer' => '',
     *                            'description' => '',
     *                            'stock' => '',
     *                            'price' => ''
     *                        ]
     * @return array Sanitize part info
     */
    protected function sanitizePartInfo(array $rowData = [])
    {
        // Sanitize array
        $rowData[self::PART_NUMBER] = $this->sanitizePartnumber($rowData[self::PART_NUMBER]);
        $rowData[self::MANUFACTURER] = $this->sanitizeManufacturer($rowData[self::MANUFACTURER]);
        $rowData[self::DESCRIPTION] = $this->sanitizeDescription($rowData[self::DESCRIPTION]);
        $rowData[self::STOCK] = $this->sanitizeStock($rowData[self::STOCK]);
        $rowData[self::PRICE] = $this->sanitizePrice($rowData[self::PRICE]);

        // If rowData has an empty property then add it to the missing attributes array
        $this->isRowDataEmpty($rowData[self::DESCRIPTION], self::DESCRIPTION);
        $this->isRowDataEmpty($rowData[self::STOCK], self::STOCK);
        $this->isRowDataEmpty($rowData[self::MANUFACTURER], self::MANUFACTURER);
        $this->isRowDataEmpty($rowData[self::PRICE], self::PRICE);

        return $rowData;
    }

    /**
     * Update the part in db
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
            $part_db = Part::where('part_number', $pn)
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
                        // Logging
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
}
