<?php

use Illuminate\Support\Facades\Route;
use Seiger\sOffers\Controllers\sOfferController;

Route::middleware('mgr')->prefix('soffer/')->name('sOffer.')->group(function () {
    Route::get('', [sOfferController::class, 'index']);
    Route::post('upload-file', [sOfferController::class, 'uploadFile'])->name('upload-file');
    Route::post('upload-download', [sOfferController::class, 'uploadDownload'])->name('upload-download');
    Route::get('upload', [sOfferController::class, 'addYoutube'])->name('addyoutube');
    Route::post('sort', [sOfferController::class, 'sortGallery'])->name('sort');
    Route::post('delete', [sOfferController::class, 'delete'])->name('delete');
    Route::get('translate', [sOfferController::class, 'getTranslate'])->name('gettranslate');
    Route::post('translate', [sOfferController::class, 'setTranslate'])->name('settranslate');
});