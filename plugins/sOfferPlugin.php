<?php
/**
 * Plugin for Seiger Offers Management Module for Evolution CMS admin panel.
 */

use Seiger\sOffers\Models\sOffer;

/**
 * Catch the offer by alias
 */
Event::listen('evolution.OnPageNotFound', function($params) {
    $aliasArr = request()->segments();
    if ($aliasArr[0] == evo()->getConfig('lang', 'uk')) {
        unset($aliasArr[0]);
    }
    $alias = implode('/', $aliasArr);

    if (Arr::exists(sOffers::documentListing(), $alias)) {
        evo()->sendForward(evo()->getConfig('s_offers_resource', 1));
        exit();
    }
});

/*
 * Get document fields and add to array of resource fields
 */
Event::listen('evolution.OnAfterLoadDocumentObject', function($params) {
    $aliasArr = request()->segments();
    if ($aliasArr[0] == evo()->getConfig('lang', 'uk')) {
        unset($aliasArr[0]);
    }
    $alias = implode('/', $aliasArr);
    $document = sOffers::documentListing()[$alias];
    $offer = sOffer::find($document)->toArray();

    evo()->documentObject = array_merge($params['documentObject'], $offer);
});