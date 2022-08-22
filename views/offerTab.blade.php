<h3>{{(request()->i ?? 0) == 0 ? __('sOffers::global.add_help') : ($offer->pagetitle ?? __('sOffers::global.no_text'))}}</h3>
<div class="split my-3"></div>
<form id="form" name="form" method="post" enctype="multipart/form-data" action="{!!$url!!}&get=offerSave" onsubmit="documentDirty=false;">
    <input type="hidden" name="back" value="&get=offer&i={{request()->i ?? 0}}" />
    <input type="hidden" name="offer" value="{{request()->i ?? 0}}" />

    <div class="row form-row">
        <div class="row-col col-lg-3 col-md-3 col-12">
            <div class="row form-row form-row-checkbox">
                <div class="col-auto col-title">
                    <label for="publishedcheck" class="warning">@lang('global.resource_opt_published')</label>
                    <i class="fa fa-question-circle" data-tooltip="@lang('sOffers::global.published_help')"></i>
                </div>
                <div class="col">
                    <input type="checkbox" id="publishedcheck" class="form-checkbox form-control" name="publishedcheck" value="" onchange="documentDirty=true;" onclick="changestate(document.form.published);" @if(isset($offer->published) && $offer->published) checked @endif>
                    <input type="hidden" id="published" name="published" value="{{$offer->published ?? 0}}" onchange="documentDirty=true;">
                </div>
            </div>
        </div>

        <div class="row-col col-lg-3 col-md-3 col-12">
            <div class="row form-row form-row-date">
                <div class="col-auto col-title-9">
                    <label for="published_at" class="warning">@lang('global.publish_date')</label>
                    <i class="fa fa-question-circle" data-tooltip="@lang('sOffers::global.published_at_help')"></i>
                </div>
                <div class="col">
                    <input id="published_at" class="form-control DatePicker" name="published_at" value="{{$offer->published_at ?? ''}}" onblur="documentDirty=true;" placeholder="dd-mm-YYYY hh:mm:ss" autocomplete="off">
                    <span class="input-group-append">
                    <a class="btn text-danger" href="javascript:(0);" onclick="document.form.published_at.value='';documentDirty=true; return true;">
                        <i class="fa fa-calendar-times-o" title="@lang('global.remove_date')"></i>
                    </a>
                </span>
                </div>
            </div>
        </div>

        <div class="row-col col-lg-3 col-md-3 col-12">
            <div class="row form-row">
                <div class="col-auto col-title-7">
                    <label for="position" class="warning">@lang('sOffers::global.position')</label>
                    <i class="fa fa-question-circle" data-tooltip="@lang('sOffers::global.position_help')"></i>
                </div>
                <div class="input-group col">
                    <div class="input-group-prepend">
                        <span class="btn btn-secondary" onclick="var elm = document.form.position;var v=parseInt(elm.value+'')-1;elm.value=v>0? v:0;elm.focus();documentDirty=true;return false;" style="cursor: pointer;"><i class="fa fa-angle-left"></i></span>
                        <span class="btn btn-secondary" onclick="var elm = document.form.position;var v=parseInt(elm.value+'')+1;elm.value=v>0? v:0;elm.focus();documentDirty=true;return false;" style="cursor: pointer;"><i class="fa fa-angle-right"></i></span>
                    </div>
                    <input type="text" id="position" name="position" class="form-control" value="{{$offer->position ?? 0}}" onchange="documentDirty=true;">
                </div>
            </div>
        </div>

        <div class="row-col col-lg-3 col-md-3 col-12">
            <div class="row form-row">
                <div class="col-auto col-title-7">
                    <label for="rating" class="warning">@lang('sOffers::global.rating')</label>
                    <i class="fa fa-question-circle" data-tooltip="@lang('sOffers::global.rating_help')"></i>
                </div>
                <div class="col">
                    <select id="rating" class="form-control" name="rating" onchange="documentDirty=true;">
                        @foreach([0, 1, 2, 3, 4, 5] as $value)
                            <option value="{{$value}}" @if($offer->rating == $value) selected @endif>{{$value}}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="row-col col-lg-3 col-md-3 col-12">
            <div class="row form-row">
                <div class="col-auto col-title-7">
                    <label for="price" class="warning">@lang('sOffers::global.price')</label>
                    <i class="fa fa-question-circle" data-tooltip="@lang('sOffers::global.price_help')"></i>
                </div>
                <div class="input-group col">
                    <div class="input-group-prepend">
                        <span class="input-group-text">₴</span>
                    </div>
                    <input type="text" id="price" name="price" class="form-control" value="{{$offer->price ?? 0.00}}" onchange="documentDirty=true;">
                </div>
            </div>
        </div>

        <div class="row-col col-lg-3 col-md-3 col-12">
            <div class="row form-row">
                <div class="col-auto col-title">
                    <label for="features" class="warning">@lang('sOffers::global.features')</label>
                    <i class="fa fa-question-circle" data-tooltip="@lang('sOffers::global.features_help')"></i>
                </div>
                <div class="col">
                    @php($offer->feature = $offer->features->pluck('fid')->toArray())
                    <select id="features" class="form-control select2" name="features[]" onchange="documentDirty=true;">
                        @foreach($features as $feature)
                            <option value="{{$feature->fid}}" @if(in_array($feature->fid, $offer->feature)) selected @endif>{{$feature->base}}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="row-col col-lg-6 col-md-6 col-12">
            <div class="row form-row">
                <div class="col-auto col-title-7">
                    <label for="alias" class="warning">@lang('global.resource_alias')</label>
                    <i class="fa fa-question-circle" data-tooltip="@lang('global.resource_alias_help')"></i>
                </div>
                <div class="input-group col">
                    <input type="text" id="alias" class="form-control" name="alias" maxlength="255" value="{{$offer->alias ?? ''}}" onchange="documentDirty=true;" spellcheck="true">
                    <a id="preview" href="{{$offer->link ?? '/'}}" class="btn btn-outline-secondary form-control" type="button" target="_blank">@lang('global.preview')</a>
                </div>
            </div>
        </div>

        <div class="row-col col-lg-6 col-md-6 col-12">
            <div class="row form-row">
                <div class="col-auto col-title-7">
                    <label for="prg_link" class="warning">@lang('sOffers::global.prg_link')</label>
                    <i class="fa fa-question-circle" data-tooltip="@lang('sOffers::global.prg_link_help')"></i>
                </div>
                <div class="col">
                    <input type="text" id="prg_link" class="form-control" name="prg_link" maxlength="255" value="{{$offer->prg_link ?? ''}}" onchange="documentDirty=true;">
                </div>
            </div>
        </div>

        <div class="row-col col-lg-6 col-md-6 col-12">
            <div class="row form-row form-row-image">
                <div class="col-auto col-title-7">
                    <label for="cover" class="warning">@lang('sOffers::global.image')</label>
                    <i class="fa fa-question-circle" data-tooltip="@lang('sOffers::global.image_help')"></i>
                </div>
                <div class="col">
                    <input type="text" id="cover" class="form-control" name="cover" value="{{$offer->cover ?? ''}}" onchange="documentDirty=true;">
                    <input class="form-control" type="button" value="@lang('global.insert')" onclick="BrowseServer('cover')">
                    <div class="col-12">
                        <div id="image_for_cover" class="image_for_field" data-image="{{$offer->coverSrc ?? ''}}" onclick="BrowseServer('cover')" style="background-image: url('{{$offer->coverSrc ?? ''}}');"></div>
                        <script>document.getElementById('cover').addEventListener('change', evoRenderImageCheck, false);</script>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>
<div class="split my-3"></div>

@push('scripts.bot')
    <div id="actions">
        <div class="btn-group">
            <a id="Button1" class="btn btn-success" href="javascript:void(0);" onclick="saveForm('#form');">
                <i class="fa fa-floppy-o"></i>
                <span>@lang('global.save')</span>
            </a>
            <a id="Button5" class="btn btn-secondary" href="{!!$url!!}">
                <i class="fa fa-times-circle"></i><span>@lang('global.cancel')</span>
            </a>
            <a id="Button3" class="btn btn-danger" data-href="{{$url}}&get=offerDelete&i={{$offer->id}}" data-toggle="modal" data-target="#confirmDelete" data-id="{{$offer->id}}" data-name="{{$offer->pagetitle}}">
                <i class="fa fa-trash"></i> <span>@lang('global.remove')</span>
            </a>
        </div>
    </div>

    <div class="modal fade" id="confirmDelete" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">@lang('sOffers::global.confirm_delete')</div>
                <div class="modal-body">
                    @lang('sOffers::global.you_sure') <b id="confirm-name"></b> @lang('sOffers::global.with_id') <b id="confirm-id"></b>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">@lang('global.cancel')</button>
                    <a class="btn btn-danger btn-ok">@lang('global.remove')</a>
                </div>
            </div>
        </div>
    </div>
@endpush