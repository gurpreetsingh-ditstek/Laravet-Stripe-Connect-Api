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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});


Route::namespace('Api\V1')->group(function () {
    Route::post("register", "UsersController@register");
    Route::post("login", "UsersController@login");
    Route::post("loginVerifyOtp", "UsersController@loginVerifyOtp");
});

Route::namespace("Api\V1")->middleware("auth:api")->group(function () {
    //Transaction API
    Route::get('get-stripe-client-id', 'TransactionController@getStripeClientId');
    Route::post('create-connect-account', 'TransactionController@createConnectAccount');
    Route::post('create-payment-intent', 'TransactionController@Payment');
    Route::post('create-connect-login-link', 'TransactionController@createConnectLoginLink');
    Route::post('verify-egift-coupon', 'TransactionController@verifyEgiftCoupon');
    Route::post('verify-egift-cash', 'TransactionController@verifyEgiftCash');
    Route::post('retrieve-all-cards', 'TransactionController@retrieveAllCards');
    Route::post('truncateTable', 'TransactionController@truncateTable');
    Route::post('checkOrderStatus', 'TransactionController@checkOrderStatus');
    Route::post('calculateEgiftTransferAmount', 'TransactionController@calculateEgiftTransferAmount');
    Route::get('create-token', 'TransactionController@createToken');
    Route::post('delete-connected-account', 'TransactionController@deleteConnectedAccount');
});
