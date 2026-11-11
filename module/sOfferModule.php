<?php
/**
 * Offers management module
 */

use EvolutionCMS\Models\SiteContent;
use EvolutionCMS\Models\SiteTmplvar;
use EvolutionCMS\Models\SiteTmplvarTemplate;
use Illuminate\Support\Facades\DB;
use Seiger\sOffers\Controllers\sOfferController;
use Seiger\sOffers\Models\sOFeature;
use Seiger\sOffers\Models\sOffer;
use Seiger\sOffers\Models\sOfferTranslate;

if (!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') die("No access");

sOfferController::requirePermission('soffers');
$action = request()->input('get', 'offers');
if (!is_string($action)) {
    sOfferController::reject(400, 'Invalid offer action.');
}
if (in_array($action, ['offerSave', 'offerDelete', 'contentSave', 'tvsSave', 'featuresSave', 'featuresSchemaSave', 'settingsSave'], true)) {
    sOfferController::requirePost();
}

$sOfferController = new sOfferController();
$data['editor'] = '';
$data['tabs'] = [];
$data['get'] = $action;
$data['sOfferController'] = $sOfferController;
try {
    $data['lang_default'] = $sOfferController->langDefault();
} catch (\InvalidArgumentException $exception) {
    sOfferController::reject(422, $exception->getMessage());
}
$data['url'] = $sOfferController->url;

switch ($data['get']) {
    default:
        $data['tabs'] = ['offers', 'features'];
        if (evo()->hasPermission('soffers_settings', 'mgr')) {
            $data['tabs'][] = 'settings';
        }
        break;
    case "offer":
        $data['tabs'] = ['offers', 'offer', 'content'];
        $data['offer'] = sOffers::getOffer(request()->i);
        $data['offer_url'] = '&i='.request()->i;
        $data['content_url'] = '&i='.request()->i;
        $data['tvs_url'] = '&i='.request()->i;
        $data['features'] = sOFeature::orderBy('base')->get();

        $template = SiteContent::find(evo()->getConfig('s_offers_resource', 0))->template ?? null;
        if ($template && SiteTmplvarTemplate::whereTemplateid($template)->first()) {
            $data['tabs'][] = 'tvs';
        }
        break;
    case "offerSave":
        // Resolve creation versus editing from stored state, not a request flag.
        $offer = sOffer::where('s_offers.id', (int)request()->offer)->firstOrNew();
        sOfferController::requirePermission($offer->exists ? 'soffers_save' : 'soffers_create');
        if ((int)request()->published !== (int)($offer->published ?? 0)
            || (string)request()->published_at !== (string)($offer->published_at ?? '')
        ) {
            sOfferController::requirePermission('soffers_publish');
        }
        $offer->published = (int)request()->published;
        $offer->parent = (int)request()->parent;
        $offer->price = $sOfferController->validatePrice(request()->price);
        $offer->position = (int)request()->position;
        $offer->rating = (int)request()->rating;
        $offer->alias = $sOfferController->validateAlias(request()->alias, request()->offer);
        $offer->prg_link = request()->prg_link;
        $offer->website = request()->website;
        $offer->cover = request()->cover;
        $offer->published_at = request()->published_at;
        $offer->save();
        $offer->features()->sync(request()->features ?? []);

        $sOfferController->setOfferListing();

        $back = str_replace('&i=0', '&i=' . $offer->id, (request()->back ?? '&get=offers'));
        return header('Location: ' . $sOfferController->url . $back);
    case "offerDelete":
        sOfferController::requirePermission('soffers_delete');
        $offer = DB::table('s_offers')->whereId((int)request()->i)->delete();
        DB::table('s_offer_translates')->whereOffer((int)request()->i)->delete();

        $sOfferController->setOfferListing();

        $back = '&get=offers';
        return header('Location: ' . $sOfferController->url . $back);
    case "content":
        $data['tabs'] = ['offers', 'offer', 'content'];

        $template = SiteContent::find(evo()->getConfig('s_offers_resource', 0))->template ?? null;
        if ($template && SiteTmplvarTemplate::whereTemplateid($template)->first()) {
            $data['tabs'][] = 'tvs';
        }

        $content = sOfferTranslate::whereOffer((int)request()->i)->whereLang(request()->lang)->first();
        if (!$content && request()->lang == $sOfferController->langDefault()) {
            $content = sOfferTranslate::whereOffer((int)request()->i)->whereLang('base')->first();
        }
        $data['offer_url'] = '&i=' . request()->i;
        $data['content_url'] = '&i=' . request()->i;
        $data['tvs_url'] = '&i='.request()->i;
        $data['constructor'] = [];
        $constructor = data_is_json($content->constructor ?? '', true);
        $settings = require EVO_BASE_PATH . 'core/custom/config/cms/settings/sOffer.php';
        $editor = "introtext,content";
        if (is_array($settings)) {
            foreach ($settings as $setting) {
                $data['constructor'][] = array_merge($setting, ['value' => ($constructor[$setting['key']] ?? '')]);
                if ($setting['type'] == 'RichText') {
                    $editor .= ",".$setting['key'];
                }
            }
        }
        $data['editor'] = $sOfferController->textEditor($editor);
        $data['content'] = $content;
        break;
    case "contentSave":
        sOfferController::requirePermission('soffers_save');
        $content = sOfferTranslate::whereOffer((int)request()->offer)->whereLang(request()->lang)->firstOrNew();
        if (!$content->tid) {
            $content->offer = (int)request()->offer;
            $content->lang = request()->lang;
        }
        $content->pagetitle = request()->pagetitle;
        $content->longtitle = request()->longtitle;
        $content->introtext = request()->introtext;
        $content->content = request()->input('content');
        $content->seotitle = request()->seotitle;
        $content->seodescription = request()->seodescription;
        $content->seorobots = request()->seorobots;
        $content->constructor = json_encode(request()->constructor);
        $content->save();
        $back = str_replace('&i=0', '&i=' . $content->offer, (request()->back ?? '&get=offers'));
        return header('Location: ' . $sOfferController->url . $back);
    case "tvs":
        $data['tabs'] = ['offers', 'offer', 'content', 'tvs'];
        $data['offer'] = sOffers::getOffer(request()->i);
        $data['offer_url'] = '&i='.request()->i;
        $data['content_url'] = '&i='.request()->i;
        $template = SiteContent::find(evo()->getConfig('s_offers_resource', 0))->template ?? 0;
        $data['tvs'] = SiteTmplvar::query()
            ->select('site_tmplvars.*', 'site_tmplvar_templates.rank as tvrank', 'site_tmplvar_templates.rank', 'site_tmplvars.id', 'site_tmplvars.rank')
            ->join('site_tmplvar_templates', 'site_tmplvar_templates.tmplvarid', '=', 'site_tmplvars.id')
            ->orderBy('site_tmplvar_templates.rank', 'ASC')
            ->orderBy('site_tmplvars.rank', 'ASC')
            ->orderBy('site_tmplvars.id', 'ASC')
            ->where('site_tmplvar_templates.templateid', $template)
            ->get();
        $data['tvValues'] = data_is_json($data['offer']->tmplvars, true) ?? [];
        break;
    case "tvsSave":
        sOfferController::requirePermission('soffers_save');
        $offer = sOffers::getOffer((int)request()->offer);
        $template = SiteContent::find(evo()->getConfig('s_offers_resource', 0))->template ?? 0;
        $tvs = SiteTmplvar::query()
            ->select('site_tmplvars.*', 'site_tmplvar_templates.rank as tvrank', 'site_tmplvar_templates.rank', 'site_tmplvars.id', 'site_tmplvars.rank')
            ->join('site_tmplvar_templates', 'site_tmplvar_templates.tmplvarid', '=', 'site_tmplvars.id')
            ->orderBy('site_tmplvar_templates.rank', 'ASC')
            ->orderBy('site_tmplvars.rank', 'ASC')
            ->orderBy('site_tmplvars.id', 'ASC')
            ->where('site_tmplvar_templates.templateid', $template)
            ->get();
        $tvValues = [];

        if ($tvs) {
            foreach ($tvs as $tv) {
                if (request()->has('tv'.$tv->id)) {
                    $value = request()->input('tv'.$tv->id);
                    if (is_array($value)) {
                        $value = implode('||', $value);
                    }
                    $tvValues[$tv->name] = $value;
                }
            }
        }

        $offer->tmplvars = json_encode($tvValues);
        $offer->save();
        $back = str_replace('&i=0', '&i=' . $offer->id, (request()->back ?? '&get=tvs'));
        return header('Location: ' . $sOfferController->url . $back);
    case "features":
        $data['tabs'] = ['offers', 'features'];
        $data['features'] = sOFeature::orderBy('position')->get();
        break;
    case "featuresSchemaSave":
        sOfferController::requirePermission('soffers_settings');
        try {
            $sOfferController->setModifyTables();
        } catch (\InvalidArgumentException $exception) {
            sOfferController::reject(422, $exception->getMessage());
        }
        return header('Location: ' . $sOfferController->url . '&get=features');
    case "featuresSave":
        try {
            $sOfferController->saveFeatures(request()->input('features', []), request()->input('deleted_features', []));
        } catch (\InvalidArgumentException $exception) {
            sOfferController::reject(422, $exception->getMessage());
        }
        $back = request()->back ?? '&get=features';
        return header('Location: ' . $sOfferController->url . $back);
    case "settings":
        $data['tabs'] = ['offers', 'features'];
        if (evo()->hasPermission('soffers_settings', 'mgr')) {
            $data['tabs'][] = 'settings';
        } else {
            $back = request()->back ?? '&get=offers';
            return header('Location: ' . $sOfferController->url . $back);
        }
        break;
    case "settingsSave":
        sOfferController::requirePermission('soffers_settings');
        try {
            $settings = $sOfferController->normalizeSettings(request()->input('settings', []));
            $resource = request()->has('parent') ? $sOfferController->parseResourceId(request()->input('parent')) : null;
            if ($resource !== null && $resource > 0 && !SiteContent::whereKey($resource)->exists()) {
                throw new \InvalidArgumentException('The selected resource does not exist.');
            }
        } catch (\InvalidArgumentException $exception) {
            sOfferController::reject(422, $exception->getMessage());
        }

        $sOfferController->saveSettings(
            EVO_BASE_PATH . 'core/custom/config/cms/settings/sOffer.php',
            $settings,
            $resource
        );
        if ($resource !== null) {
            evo()->setConfig('s_offers_resource', $resource);
        }
        evo()->clearCache('full');

        $back = request()->back ?? '&get=settings';
        return header('Location: ' . $sOfferController->url . $back);
}

echo $sOfferController->view('index', $data);
