<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BkashController;


/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

// Checkout (IFrame) User Part
Route::get('/pay', [BkashController::class, 'payment'])->name('bkash-payment');
Route::get('/bkash/create', [BkashController::class, 'createPayment'])->name('bkash-create');
Route::get('/bkash/execute', [BkashController::class, 'executePayment'])->name('bkash-execute');
Route::get('/success', [BkashController::class, 'successPayment'])->name('bkash-success');
Route::get('/fail', [BkashController::class, 'failPayment'])->name('bkash-fail');

// Checkout (IFrame) Refund Admin Part
Route::get('/bkash/refund', [BkashController::class, 'getRefund'])->name('get-refund');
Route::post('/bkash/refund', [BkashController::class, 'refundPayment'])->name('bkash-refund');
