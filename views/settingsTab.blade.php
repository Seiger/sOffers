<h3>@lang('sOffers::global.management_additional_fields')</h3>
<form id="form" name="form" method="post" enctype="multipart/form-data" action="{!!$url!!}&get=settingsSave" onsubmit="documentDirty=false;">
    <input type="hidden" name="back" value="&get=settings" />

    <div class="row form-row widgets sortable">
        @php($settings = require MODX_BASE_PATH . 'core/custom/config/cms/settings/sOffer.php')
        @foreach($settings as $setting)
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <i style="cursor:pointer;font-size:x-large;" class="fas fa-sort"></i>&emsp; {{$setting['key']}}
                        <span class="close-icon"><i class="fa fa-times"></i></span>
                    </div>
                    <div class="card-block">
                        <div class="userstable">
                            <div class="card-body">
                                <div class="row form-row">
                                    <div class="col-auto col-title-6">
                                        <label class="warning">@lang('global.name')</label>
                                    </div>
                                    <div class="col">
                                        <input type="text" class="form-control" name="settings[name][]" maxlength="255" value="{{$setting['name']}}" onchange="documentDirty=true;" spellcheck="true">
                                    </div>
                                </div>
                                <div class="row form-row">
                                    <div class="col-auto col-title-6">
                                        <label class="warning">@lang('sOffers::global.field_type')</label>
                                    </div>
                                    <div class="col">
                                        <select id="rating" class="form-control" name="settings[type][]" onchange="documentDirty=true;">
                                            @foreach(['Text', 'Textarea', 'RichText'] as $value)
                                                <option value="{{$value}}" @if($setting['type'] == $value) selected @endif>{{$value}}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <input type="hidden" name="settings[key][]" value="{{$setting['key']}}" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</form>

@push('scripts.bot')
    <style>
        .close-icon{cursor:pointer;position:absolute;top:0;right:0;z-index:2;padding:0.6rem 1rem;}
        .draft-value{display:none;}
    </style>
    <script>
        $(document).on("click", ".close-icon", function () {$(this).closest('.card').remove();documentDirty=true;});
        function addItem() {$(".widgets").append($('.draft-value').html());}
    </script>
    <div id="actions">
        <div class="btn-group">
            <a id="Button2" class="btn btn-primary" href="javascript:void(0);" onclick="addItem();">
                <i class="fa fa-plus"></i><span>@lang('global.add')</span>
            </a>
            <a id="Button1" class="btn btn-success" href="javascript:void(0);" onclick="saveForm('#form');">
                <i class="fas fa-save"></i><span>@lang('global.save')</span>
            </a>
            <a id="Button5" class="btn btn-secondary" href="{!!$url!!}">
                <i class="fa fa-times-circle"></i><span>@lang('global.cancel')</span>
            </a>
        </div>
    </div>
    <div class="draft-value">
        <div class="col-sm-12">
            <div class="card">
                <div class="card-header">
                    <i style="cursor:pointer;font-size:x-large;" class="fas fa-sort"></i>&emsp; @lang('sOffers::global.new_field')
                    <span class="close-icon"><i class="fa fa-times"></i></span>
                </div>
                <div class="card-block">
                    <div class="userstable">
                        <div class="card-body">
                            <div class="row form-row">
                                <div class="col-auto col-title-6">
                                    <label class="warning">@lang('sOffers::global.key')</label>
                                </div>
                                <div class="col">
                                    <input type="text" class="form-control" name="settings[key][]" maxlength="255" value="" onchange="documentDirty=true;" spellcheck="true">
                                </div>
                            </div>
                            <div class="row form-row">
                                <div class="col-auto col-title-6">
                                    <label class="warning">@lang('global.name')</label>
                                </div>
                                <div class="col">
                                    <input type="text" class="form-control" name="settings[name][]" maxlength="255" value="" onchange="documentDirty=true;" spellcheck="true">
                                </div>
                            </div>
                            <div class="row form-row">
                                <div class="col-auto col-title-6">
                                    <label class="warning">@lang('sOffers::global.field_type')</label>
                                </div>
                                <div class="col">
                                    <select id="rating" class="form-control" name="settings[type][]" onchange="documentDirty=true;">
                                        @foreach(['Text', 'Textarea', 'RichText'] as $value)
                                            <option value="{{$value}}">{{$value}}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endpush