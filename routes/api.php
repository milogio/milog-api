<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
    
});

Route::group(['middleware' => 'auth:api'], function() {
    Route::apiResource('sites', 'SiteController');
    Route::apiResource('pages', 'PageController');
});

Route::prefix('v1')->middleware('milog.api_key')->group(function () {
    Route::post('events', 'Api\V1\EventController@store');
    Route::get('timeline', 'Api\V1\TimelineController@index');
});
