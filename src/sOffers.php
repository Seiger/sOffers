<?php namespace Seiger\sOffers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Seiger\sOffers\Controllers\sOfferController;
use Seiger\sOffers\Models\sOffer;

class sOffers
{
    public function __construct()
    {
        if (IN_MANAGER_MODE) {
            Paginator::defaultView('sOffers::partials.pagination');
            $this->url = $this->moduleUrl();
        } else {
            Paginator::defaultView('partials.pagination');
        }
    }

    /**
     * Get all offers
     *
     * @return object
     */
    public function all($paginate = 30): object
    {
        $order = 's_offers.position';
        $direc = 'asc';

        $query = sOffer::orderBy($order, $direc);

        if (!IN_MANAGER_MODE) {
            $query->active();
        }

        $offers = $query->paginate($paginate);
        return $offers;
    }

    /**
     * Get offer object with translation by ID
     *
     * @param int $offerId
     * @return object
     */
    public function getOffer(int $offerId): object
    {
        return sOffer::where('s_offers.id', $offerId)->first() ?? new sOffer();
    }

    /**
     * Get offer object with translation by Alias
     *
     * @param string $offerAlias
     * @return object
     */
    public function getOfferByAlias(string $offerAlias): object
    {
        return sOffer::where('s_offers.alias', $offerAlias)->first() ?? new sOffer();
    }

    /**
     * List offer aliases
     *
     * @return array
     */
    public function documentListing(): array
    {
        $offerListing = Cache::get('offerListing');

        if (!$offerListing) {
            $sOfferController = new sOfferController();
            $sOfferController->setOfferListing();
            $offerListing = Cache::get('offerListing');
        }

        return $offerListing ?? [];
    }

    /**
     * Module url
     *
     * @return string
     */
    public function moduleUrl(): string
    {
        $controller = new sOfferController();
        return $controller->url;
    }
}