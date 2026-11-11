<?php

use Illuminate\Support\Facades\Route;
use Seiger\sOffers\Controllers\sOfferController;

Route::middleware('mgr')->prefix('soffer/')->name('sOffer.')->group(function () {
    Route::get('', [sOfferController::class, 'index']);
});
