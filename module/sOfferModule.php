<?php
/**
 * Offers management module
 */

use Illuminate\Support\Str;
use Seiger\sOffers\Controllers\sOfferController;
use Seiger\sOffers\Models\sOFeature;
use Seiger\sOffers\Models\sOffer;
use Seiger\sOffers\Models\sOfferTranslate;

if (!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') die("No access");

$sOfferController = new sOfferController();
$data['editor'] = '';
$data['tabs'] = [];
$data['get'] = request()->get ?? "offers";
$data['sOfferController'] = $sOfferController;
$data['lang_default'] = $sOfferController->langDefault();
$data['url'] = $sOfferController->url;

switch ($data['get']) {
    default:
        $data['tabs'] = ['offers', 'features'];
        if (evo()->hasPermission('settings')) {
            $data['tabs'][] = 'settings';
        }
        break;
    case "offer":
        $data['tabs'] = ['offers', 'offer', 'content'];
        $data['offer'] = sOffers::getOffer(request()->i);
        $data['offer_url'] = '&i='.request()->i;
        $data['content_url'] = '&i='.request()->i;
        $data['features'] = sOFeature::orderBy('base')->get();
        break;
    case "offerSave":
        $offer = sOffer::where('s_offers.id', (int)request()->offer)->firstOrNew();
        $offer->published = (int)request()->published;
        $offer->category = (int)request()->category;
        $offer->price = $sOfferController->validatePrice(request()->price);
        $offer->position = (int)request()->position;
        $offer->rating = (int)request()->rating;
        $offer->alias = $sOfferController->validateAlias(request()->alias, request()->offer);
        $offer->prg_link = request()->prg_link;
        $offer->cover = request()->cover;
        $offer->published_at = request()->published_at;
        $offer->save();
        $offer->features()->sync(request()->features ?? []);
        $back = str_replace('&i=0', '&i=' . $offer->id, (request()->back ?? '&get=offers'));
        return header('Location: ' . $sOfferController->url . $back);
    case "offerDelete":
        $offer = DB::table('s_offers')->whereId((int)request()->i)->delete();
        DB::table('s_offer_translates')->whereOffer((int)request()->i)->delete();
        $back = '&get=offers';
        return header('Location: ' . $sOfferController->url . $back);
    case "content":
        $data['tabs'] = ['offers', 'offer', 'content'];
        $content = sOfferTranslate::whereOffer((int)request()->i)->whereLang(request()->lang)->first();
        if (!$content && request()->lang == $sOfferController->langDefault()) {
            $content = sOfferTranslate::whereOffer((int)request()->i)->whereLang('base')->first();
        }
        $data['offer_url'] = '&i=' . request()->i;
        $data['content_url'] = '&i=' . request()->i;
        $data['constructor'] = [];
        $constructor = data_is_json($content->constructor ?? '', true);
        $settings = require MODX_BASE_PATH . 'core/custom/config/cms/settings/sOffer.php';
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
    case "features":
        $sOfferController->setModifyTables();
        $data['tabs'] = ['offers', 'features'];
        $data['features'] = sOFeature::orderBy('position')->get();
        break;
    case "featuresSave":
        if (request()->filled('features')) {
            $features = request()->features;
            $sOFeatures = sOFeature::all();
            if (count($features)) {
                $values = [];
                $fields = array_keys($features);

                foreach ($features['fid'] as $idx => $fid) {
                    $array = [];
                    foreach ($fields as $field) {
                        $array[$field] = $features[$field][$idx];
                    }
                    if (count(array_diff($array, [""]))) {
                        $array['position'] = $idx;
                        if ($sOfferController->langDefault() != 'base') {
                            $array['base'] = $array[$sOfferController->langDefault()];
                        }
                        $array['alias'] = $sOfferController->validateAlias(trim($array['alias'] ?? '') ?: $array['base'], $array['fid'] ?? 0, 'feature');
                        unset($array['fid']);
                        $values[$array['alias']] = $array;
                    }
                }

                foreach ($sOFeatures as $sOFeature) {
                    if (isset($values[$sOFeature->alias])) {
                        foreach ($values[$sOFeature->alias] as $field => $item) {
                            $sOFeature->{$field} = $item;
                        }
                        $sOFeature->update();

                        unset($values[$sOFeature->alias]);
                    } else {
                        $sOFeature->delete();
                    }
                }

                if (count($values)) {
                    foreach ($values as $value) {
                        $sOFeature = new sOFeature();
                        foreach ($value as $field => $item) {
                            $sOFeature->{$field} = $item;
                        }
                        $sOFeature->save();
                    }
                }
            } else {
                foreach ($sOFeatures as $sOFeature) {
                    $sOFeature->delete();
                }
            }
        }
        $back = request()->back ?? '&get=features';
        return header('Location: ' . $sOfferController->url . $back);
    case "settings":
        $data['tabs'] = ['offers', 'features'];
        if (evo()->hasPermission('settings')) {
            $data['tabs'][] = 'settings';
        } else {
            $back = request()->back ?? '&get=offers';
            return header('Location: ' . $sOfferController->url . $back);
        }
        break;
    case "settingsSave":
        $keys = request()->input('settings.key', []);
        $settings = [];
        if (count($keys)) {
            foreach ($keys as $idx => $key) {
                $key = Str::slug($key);
                $key = Str::lower($key);
                $settings[$key] = [
                    'key' => $key,
                    'name' => request()->input('settings')['name'][$idx],
                    'type' => request()->input('settings')['type'][$idx],
                ];
            }
        }

        $f = fopen(MODX_BASE_PATH . 'core/custom/config/cms/settings/sOffer.php', "w");
        fwrite($f, '<?php return [' . "\r\n");
        if (count($settings)) {
            foreach ($settings as $key => $setting) {
                if (trim($key)) {
                    fwrite($f, "\t'" . $key . "' => [" . "\r\n");
                    foreach ($setting as $k => $v) {
                        fwrite($f, "\t\t'" . $k . "' => '" . $v . "',\r\n");
                    }
                    fwrite($f, "\t]" . ",\r\n");
                }
            }
        }
        fwrite($f, "];");
        fclose($f);
        sleep(10);
        $back = request()->back ?? '&get=settings';
        return header('Location: ' . $sOfferController->url . $back);
}

echo $sOfferController->view('index', $data);