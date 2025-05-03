<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PaymentController;

Route::get('/', function () {
    return view('welcome');
});


// Payment Routes
Route::prefix('payments')->name('payments.')->group(function () {
    // Payment Options
    Route::get('options/{orderId}', [PaymentController::class, 'showPaymentOptions'])
        ->name('options');
    
    // EcoCash Routes
    Route::prefix('ecocash')->name('ecocash.')->group(function () {
        Route::post('process/{orderId}', [PaymentController::class, 'processEcocash'])
            ->name('process');
        Route::get('check-status', [PaymentController::class, 'checkEcocashStatus'])
            ->name('check-status');
        Route::post('callback', [PaymentController::class, 'ecocashCallback'])
            ->name('callback');
        Route::get('return', [PaymentController::class, 'ecocashReturn'])
            ->name('return');
    });
    
    // PayPal Routes
    Route::prefix('paypal')->name('paypal.')->group(function () {
        Route::post('process/{orderId}', [PaymentController::class, 'processPaypal'])
            ->name('process');
        Route::get('return', [PaymentController::class, 'paypalReturn'])
            ->name('return');
        Route::get('cancel', [PaymentController::class, 'paypalCancel'])
            ->name('cancel');
        Route::post('webhook', [PaymentController::class, 'paypalWebhook'])
            ->name('webhook');
    });
    
    // Cash on Delivery Routes
    Route::post('cod/{orderId}', [PaymentController::class, 'processCod'])
        ->name('cod');
    
    // Bank Transfer Routes
    Route::post('bank-transfer/{orderId}', [PaymentController::class, 'processBankTransfer'])
        ->name('bank-transfer');
});
