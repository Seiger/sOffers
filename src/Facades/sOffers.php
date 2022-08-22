<?php namespace Seiger\sOffers\Facades;

use Illuminate\Support\Facades\Facade;

class sOffers extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'sOffers';
    }
}