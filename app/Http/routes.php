<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the controller to call when that URI is requested.
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::auth();

Route::get('/home', 'HomeController@index');

// Admin page for managing parts
Route::get('/admin', 'Admin\PartsController@index');
Route::post('/admin', 'Admin\PartsController@index');
Route::get('/admin/{page}', 'Admin\PartsController@index');
Route::post('/admin/{page}', 'Admin\PartsController@index');
Route::get('/admin/manufacturers/search/{keyword}', 'Admin\ManufacturersController@search');

