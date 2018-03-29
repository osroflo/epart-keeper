<?php
namespace app\Lib\Export;

/**
 * This class export to CSV using a mysql query to
 * export directly from DB to CSV to make the process
 * faster.
 */
class MysqlCSVExporter
{
    /**
     * Export partnumbers.
     *
     * @param string $where_conditions     The filters that should be applied to the query.
     * @param string $file_name The file name.
     *
     * @return string $file     The filepath to be downloaded.
     */
    public function getParts($where_conditions = [], $file_name = "parts")
    {
        // Generate unique file name
        $file_name = $file_name ."-". time();
        $directory = "/tmp";

        $file_zip = "$directory/$file_name.zip";
        $file_csv = "$directory/$file_name.csv";

        // Convert the where conditions from array to string if any
        $whereString = $this->getWhereString($where_conditions);

        // The first select is to add the header. the second one is to reurn the query
        /**
         * IMPORTANT:
         * The CASE statement was added to improve performace in query. If join is used it takes a lot of
         * space in HDD, RAM to export 8 Million rows to csv (more than 10 minutes). If no join is added it
         * is really faster only 1.20 mins.
         */
        $query = sprintf("
            SELECT
                part_number,
                manufacturer,
                description,
                ROUND( (RAND() * (2)) * 100 + stock) as stock,
                price,
                is_complete,
                CASE
                    WHEN origin = 1 THEN 'oemstrade.com'
                    WHEN origin = 2 THEN 'verical.com'
                    WHEN origin = 3 THEN 'vyrian.com'
                    WHEN origin = 4 THEN '4starelectronics.com'
                    WHEN origin = 5 THEN '1sourcecomponents.com'
                END AS origin,
                missing_attributes
            FROM parts parts
            %s
                INTO OUTFILE '%s'
                FIELDS TERMINATED BY ','
                ENCLOSED BY '\"'
                LINES TERMINATED BY '\n'",
            $whereString,
            $file_csv
        );

        \DB::connection()->getpdo()->exec($query);

        // Insert header
        $header = "part_number, manufacturer, description, stock, price, is_complete, origin, missing_attributes\n";

        $handler = fopen($file_csv, "r+");
                fwrite($handler, $header);
        fclose($handler);

        // Compress the file
        exec("zip -j $file_zip $file_csv");

        return $file_zip;
    }

    public function getWhereString($where_conditions = [])
    {
        $whereString = "";

        if ( count($where_conditions) > 0) {
            $whereString = " WHERE " . implode(" AND ", $where_conditions);
        }

        return $whereString;
    }
}
