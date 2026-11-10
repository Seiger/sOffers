<?php namespace Seiger\sOffers\Controllers;

use EvolutionCMS\Facades\UrlProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Seiger\sOffers\Models\sOFeature;
use Seiger\sOffers\Models\sOffer;
use Seiger\sOffers\Support\ModuleAccess;

/**
 * Render the manager module and provide its offer, language and schema helpers.
 *
 * Mutating module branches authorize their own actions before invoking helpers.
 */
class sOfferController
{
    public $url;

    /**
     * Construct
     */
    public function __construct()
    {
        $this->url = $this->moduleUrl();
        //Paginator::defaultView('pagination');
    }

    /**
     * Render the package route entry for managers with sOffers access.
     *
     * @return View
     */
    public function index(): View
    {
        ModuleAccess::requirePermission('soffers');
        return $this->view('index');
    }

    /**
     * Default language
     *
     * @return string
     */
    public function langDefault(): string
    {
        return evo()->getConfig('s_lang_default', 'base');
    }

    /**
     * Languages list
     *
     * @return array
     */
    public function langList(): array
    {
        $lang = evo()->getConfig('s_lang_config', '');
        if (trim($lang)) {
            $lang = explode(',', $lang);
        } else {
            $lang = ['base'];
        }
        return $lang;
    }

    /**
     * Add missing feature translation columns through the explicit manager schema action.
     *
     * Requires sOffers settings permission and a token-protected POST even for direct callers.
     * Opening the features tab never invokes this DDL helper.
     *
     * @return void
     */
    public function setModifyTables(): void
    {
        ModuleAccess::requirePermission('soffers_settings');
        ModuleAccess::requirePost();
        $needs = [];
        $columns = [];
        $lang = $this->langList();

        if ($lang != [$this->langDefault()]) {
            $query = evo()->getDatabase()->query("DESCRIBE " . evo()->getDatabase()->getFullTableName('s_o_features'));

            if ($query) {
                $fields = evo()->getDatabase()->makeArray($query);

                foreach ($fields as $field) {
                    $columns[$field['Field']] = $field;
                }

                foreach ($lang as $item) {
                    if (!isset($columns[$item])) {
                        $needs[] = "ADD `{$item}` varchar(255) COMMENT '" . strtoupper($item) . " value version'";
                    }
                }
            }

            if (count($needs)) {
                $need = implode(', ', $needs);
                $query = "ALTER TABLE `".evo()->getDatabase()->getFullTableName('s_o_features')."` {$need}";
                evo()->getDatabase()->query($query);
            }
        }
    }

    /**
     * Generate offer list aliases
     *
     * @return void
     */
    public function setOfferListing(): void
    {
        $offerListing = [];
        $offers = sOffer::select('id', 'alias', 'parent')->wherePublished(1)->get();

        if ($offers) {
            foreach ($offers as $offer) {
                $parent = '';
                if ((int)$offer->parent > 0) {
                    $parent = UrlProcessor::makeUrl($offer->parent);
                    $parent = ltrim($parent, '/');
                }
                $offerListing[$parent.$offer->alias] = $offer->id;
            }
        }

        Cache::forever('offerListing', $offerListing);
    }

    /**
     * Connecting the visual editor to the required fields
     *
     * @param string $ids List of id fields separated by commas
     * @param string $height Window height
     * @param string $editor Which editor to use TinyMCE5, Codemirror
     * @return string
     */
    public function textEditor(string $ids, string $height = '500px', string $editor = ''): string
    {
        if (!trim($editor)) {
            $editor = evo()->getConfig('which_editor', 'TinyMCE5');
        }
        $elements = [];
        $ids = explode(",", $ids);

        foreach ($ids as $id) {
            $elements[] = trim($id);
        }

        return implode("", evo()->invokeEvent('OnRichTextEditorInit', [
            'editor' => $editor,
            'elements' => $elements,
            'height' => $height,
            'contentType' => 'htmlmixed'
        ]));
    }

    /**
     * Build the registered file-module URL with the CMS token required by action 112.
     *
     * @return string
     */
    protected function moduleUrl(): string
    {
        return 'index.php?a=112&id=' . md5(__('sOffers::global.offers')) . '&_token=' . rawurlencode(csrf_token());
    }

    /**
     * Price validation
     *
     * @param mixed $price
     * @return float
     */
    public function validatePrice(mixed $price): float
    {
        $validPrice = 0.00;
        $price = str_replace(',', '.', $price);

        if (is_int($price) || is_numeric($price)) {
            $price = floatval($price);
            $validPrice = floatval(number_format($price, 2, '.', ''));
        } elseif (is_float($price)) {
            $validPrice = floatval(number_format($price, 2, '.', ''));
        }

        return $validPrice;
    }

    /**
     * Alias validation
     *
     * @param $data
     * @param string $table
     * @return string
     */
    public function validateAlias($string = '', $id = 0, $key = 'offer'): string
    {
        if (trim($string)) {
            $alias = Str::slug(trim($string), '-');
        } else {
            $alias = $id;
        }

        switch ($key) {
            default :
                $aliases = sOffer::where('s_offers.id', '<>', $id)->get('alias')->pluck('alias')->toArray();
                break;
            case "feature" :
                $aliases = sOFeature::where('s_o_features.fid', '<>', $id)->get('alias')->pluck('alias')->toArray();
                break;
        }

        if (in_array($alias, $aliases)) {
            $cnt = 1;
            $tempAlias = $alias;
            while (in_array($tempAlias, $aliases)) {
                $tempAlias = $alias . $cnt;
                $cnt++;
            }
            $alias = $tempAlias;
        }
        return $alias;
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'title.required' => 'A title is required',
            'body.required' => 'A message is required',
        ];
    }

    /**
     * Display render
     *
     * @param string $tpl
     * @param array $data
     * @return bool
     */
    public function view(string $tpl, array $data = [])
    {
        return \View::make('sOffers::'.$tpl, $data);
    }
}
