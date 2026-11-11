<?php namespace Seiger\sOffers\Models;

use EvolutionCMS\Facades\UrlProcessor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Seiger\sOffers\Controllers\sOfferController;
use Seiger\sOffers\Models\sOFeature;

/**
 * Store offers and expose locale-aware content, cover URLs and frontend links.
 *
 * Asset paths and absolute URLs use the Evolution CMS bootstrap constants.
 */
class sOffer extends Model
{
    /**
     * Guard all attributes from mass assignment. Module writes set fields explicitly.
     *
     * @var array<int, string>
     */
    protected $guarded = ['*'];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['coverSrc', 'link'];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        $controller = new sOfferController();
        $locale = evo()->getConfig('lang', $controller->langDefault());

        static::addGlobalScope('translates', function (Builder $builder) use ($locale) {
            $grammar = $builder->getQuery()->getGrammar();
            $quotedLocale = DB::connection()->getPdo()->quote($locale);
            $quotedBase = DB::connection()->getPdo()->quote('base');
            $langOrderSql = 'CASE WHEN ' . $grammar->wrap('lang') . ' = ' . $quotedLocale
                . ' THEN 0 WHEN ' . $grammar->wrap('lang') . ' = ' . $quotedBase
                . ' THEN 1 ELSE 2 END';

            $builder->leftJoin('s_offer_translates', function ($leftJoin) use ($locale, $langOrderSql) {
                $leftJoin->on('s_offers.id', '=', 's_offer_translates.offer')
                    ->where('lang', function ($leftJoin) use ($locale, $langOrderSql) {
                        $leftJoin->select('lang')
                            ->from('s_offer_translates as sot')
                            ->whereColumn('sot.offer', 's_offers.id')
                            ->whereIn('lang', [$locale, 'base'])
                            ->orderByRaw($langOrderSql)
                            ->limit(1);
                    });
            });
        });
    }

    /**
     * Get the features for the Offer.
     */
    public function features()
    {
        return $this
            ->belongsToMany(sOFeature::class, 's_offer_features', 'offer', 'feature')
            ->orderBy('s_o_features.position');
    }

    /**
     * Only active offers
     *
     * @param Builder $builder
     * @return Builder
     */
    public function scopeActive($builder)
    {
        return $builder->where('s_offers.published', '1');
    }

    /**
     * Resolve a cover below the CMS base path or return the package placeholder URL.
     *
     * @return string Absolute asset URL using the CMS site URL.
     */
    public function getCoverSrcAttribute()
    {
        if (!empty($this->cover) && is_file(EVO_BASE_PATH . $this->cover)) {
            $coverSrc = EVO_SITE_URL . $this->cover;
        } else {
            $coverSrc = EVO_SITE_URL . 'assets/images/noimage.png';
        }

        return $coverSrc;
    }

    /**
     * Build a frontend link from the parent resource, alias and CMS URL suffix.
     *
     * A zero parent uses the site-start resource on this model instance. Relative
     * parent URLs are expanded against the CMS site URL before appending the alias.
     *
     * @return string Offer URL.
     */
    public function getLinkAttribute()
    {
        if ($this->parent == 0) {
            $this->parent = evo()->getConfig('site_start', 1);
        }
        $base_url = UrlProcessor::makeUrl($this->parent);
        if (str_starts_with($base_url, '/')) {
            $base_url = EVO_SITE_URL . ltrim($base_url, '/');
        }
        return $base_url.$this->alias.evo()->getConfig('friendly_url_suffix', '');
    }
}
