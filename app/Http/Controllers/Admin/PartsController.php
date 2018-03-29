<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Part;
use App\Models\MetadataOrigin;
use Illuminate\Database\Eloquent\Builder;
use App\Lib\Export\MysqlCSVExporter;

class PartsController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request, $page = 1)
    {
        // Array to compose  the where statement to export to csv
        $csv_export_where_statement = [];

        // Get filters
        $partNumber = $request->input('part_num', '');
        $startDate = $request->input('start_date', '');
        $endDate = $request->input('end_date', '');
        $dateType = $request->input('date_type', 'None');
        $manufacturers = $request->input('manufacturer', '');
        $manufacturerFilterType = $request->input('manufacturer_filter_type', 1);

        $isComplete = $request->input('is_complete', '');
        $origin = $request->input('origin', '');
        $isExportRequest = $request->input('export', '');

        // Assign default where statement
        $where_statement = [];

        // Add part number
        if ($partNumber != "") {
            $where_statement[] = " part_number LIKE \"%$partNumber%\" ";
        }

        // Add 'created_at' date filters, if passed
        if ($dateType != 'None') {
            $date_field = ($dateType == "Date Created") ? "created_at":"updated_at";

            // where statement to filter the export to CSV
            // improve this, only created_at or updated_at can be passed to the sql query not both
            $where_statement[] = " ($date_field BETWEEN '$startDate' AND '$endDate') ";
        }

        // Add 'is_complete' filter, if passed
        if ($isComplete != '') {
            $where_statement[] = " is_complete = $isComplete ";
        }

        // Add 'is_complete' filter, if passed
        if ($origin != '') {
            $where_statement[] = " origin = $origin ";
        }

        // Add manufacturer
        if ($manufacturers != '') {
            // If more than one manufacturer were passed format the string
            if (is_array($manufacturers)) {
                $manufacturers_string = "'" . implode("','", $manufacturers) . "'";
            } else {
                $manufacturers_string = "'$manufacturers'";
            }

            // Check if (1) exclude these or (2) only these
            $in_string = ($manufacturerFilterType == 1) ? " NOT IN " : " IN ";
            $where_statement[] = " manufacturer $in_string ($manufacturers_string) ";
        }

        // Export query to CSV file, if prompted
        if ($isExportRequest) {
            $csvEporter = new MysqlCSVExporter();
            $path_to_download_file = $csvEporter->getParts($where_statement);

            return response()->download($path_to_download_file);
        }


        // Fetch parts for view
        $string_where = implode(" AND ", $where_statement);
        error_log("DEBUG WHERE : $string_where");

        // Get total
        $total = Part::count();

        // Get results if any
        if(count($where_statement) > 0 ) {
            $parts = Part::whereRaw($string_where)->orderBy('id')->paginate(10);
            $result = $parts->appends($request->except('page'));
        } else {
            $result = Part::whereRaw("id = 0")->paginate(10);
        }

        // Get metadata for origin
        $origin_metadata = MetadataOrigin::all();

        // Render view
        return view('admin.parts', [
            'result' => $result,
            'origin_metadata' => $origin_metadata,
            'total' => $total
        ]);
    }

    /**
     * Export to csv
     */
    public function export(Builder $builder)
    {
        // Create the CSV file in memory
        $csv = \League\Csv\Writer::createFromFileObject(new \SplTempFileObject());

        // Create your headers
        $csv->insertOne(\Schema::getColumnListing('parts'));

        // Insert your rows
        $builder->each(function($person) use($csv) {
            $csv->insertOne($person->toArray());
        });

        // Output it to the user
        $csv->output('parts.csv');
        // without this, the HTML of the page will be added to the CSV
        die;
    }
}
