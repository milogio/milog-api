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
});

Route::get('v1/timeline', 'Api\V1\TimelineController@index')->middleware('milog.timeline_tenant');

Route::prefix('v1/signup')->group(function () {
    Route::post('/', 'Api\V1\SignupController@register')->middleware('throttle:milog-signup');
    Route::post('resend', 'Api\V1\SignupController@resend')->middleware('throttle:milog-signup');
    Route::post('verify', 'Api\V1\SignupController@verify')->middleware('throttle:milog-signup-verify');
});

Route::prefix('v1')->middleware(['auth:api', 'milog.ui_tenant'])->group(function () {
    Route::get('entitlement', 'Api\V1\ApiKeyController@entitlement');

    Route::middleware('milog.tenant_role:owner,admin')->group(function () {
        Route::get('api-keys', 'Api\V1\ApiKeyController@index');
        Route::post('api-keys', 'Api\V1\ApiKeyController@store')->middleware('throttle:milog-key-create');
        Route::delete('api-keys/{key}', 'Api\V1\ApiKeyController@destroy');
    });
});

Route::prefix('v1/auth')->group(function () {
    Route::post('login', 'Api\V1\AuthController@login')->middleware('throttle:milog-ui-login');
    Route::post('refresh', 'Api\V1\AuthController@refresh')->middleware('throttle:milog-ui-refresh');

    Route::middleware(['auth:api', 'milog.ui_tenant'])->group(function () {
        Route::get('me', 'Api\V1\AuthController@me');
        Route::post('logout', 'Api\V1\AuthController@logout');
        Route::post('logout-all', 'Api\V1\AuthController@logoutAll');
    });
});
