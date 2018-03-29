<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Manufacturer;
use Response;


class ManufacturersController extends Controller
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
     * Display the specified resource by name.
     *
     * @param  string  $keyword
     * @return Response
     */
    public function search($keyword)
    {
        $manufacturers = Manufacturer::where('label', 'LIKE', "{$keyword}%")->orderBy('label')->take(50)->get();

        return Response::json($manufacturers);
    }
}
