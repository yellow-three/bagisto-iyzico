<?php

use Illuminate\Support\Facades\Route;
use Webkul\Iyzico\Http\Controllers\IyzicoController;

Route::controller(IyzicoController::class)
    ->middleware('web')
    ->prefix('iyzico')
    ->group(function () {
        Route::get('redirect', 'redirect')->name('iyzico.standard.redirect');

        Route::match(['get', 'post'], 'callback', 'callback')->name('iyzico.standard.callback');

        Route::get('cancel', 'cancel')->name('iyzico.standard.cancel');
    });
