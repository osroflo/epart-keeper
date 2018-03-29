@extends('layouts.app')

@section('styles')
    <link type="text/css" rel="stylesheet" href="{{ asset('css/bootstrap-daterangepicker-2.1.23.css') }}">
    <link type="text/css" rel="stylesheet" href="{{ asset('css/admin/parts.css') }}">
    <link type="text/css" rel="stylesheet" href="{{ asset('css/selectize.bootstrap3.css') }}">
@endsection

@section('scripts')
    <script type="text/javascript" src="{{ asset('js/moment-2.14.1.min.js') }}"></script>
    <script type="text/javascript" src="{{ asset('js/bootstrap-daterangepicker-2.1.23.js') }}"></script>
    <script type="text/javascript" src="{{ asset('js/selectize.min.js') }}"></script>
    <script type="text/javascript" src="{{ asset('js/admin/parts.js') }}"></script>
@endsection

<!-- Main Content -->
@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-12">
            <div class="panel panel-default">
                <div class="panel-heading">Total Parts in Data Base: <b>{{number_format($total)}}</b>  </b></div>
                <div class="panel-body">

                    <div class="parts-filters">
                        <form action="/admin/1" method="GET" class="form-horizontal">

                            <div class="form-group col-md-12">
                                <label class="col-sm-2 control-label">Part Number:</label>
                                <div class="col-sm-10">
                                    <input type="text" name="part_num" class="form-control" value="{{ Request::input('part_num') }}" placeholder="Part Number...">
                                </div>
                            </div>
                            <div class="form-group col-md-12">
                                <label class="col-sm-2 control-label">Manufacturers:</label>

                                <div class="col-sm-10">
                                    <select id="select-manufacturers" name="manufacturer[]" multiple="multiple" placeholder="Type a manufacturer." >

                                        @foreach(Request::input('manufacturer', []) as $manufacturer)
                                            <option value="{{ $manufacturer }}" selected>{{ $manufacturer }}</option>
                                        @endforeach
                                    </select>

                                    <div class="pull-right">
                                        <input type="radio" name="manufacturer_filter_type" {{ Request::input('manufacturer_filter_type', 1) == 1 ? "checked" : ""}} value="1"> Exclude these
                                        <input type="radio" class="margin-left" name="manufacturer_filter_type" {{ Request::input('manufacturer_filter_type') == 2 ? "checked" : ""}} value="2">Only these
                                    </div>
                                </div>
                            </div>

                            <div class="form-group col-md-12">
                                <label class="col-sm-2 control-label">Date Range:</label>
                                <div class="col-sm-10">
                                    <div class="input-group">

                                        <input
                                            type="text"
                                            name="date_range"
                                            id="date_range"
                                            class="js-datepicker js-start-date datepicker form-control"
                                            value="{{ Request::input('date_range') }}">

                                        <div class="input-group-btn">
                                            <button
                                                type="button"
                                                id="dateTypeDropdownBtn"
                                                class="btn btn-default dropdown-toggle"
                                                data-toggle="dropdown"
                                                aria-haspopup="true"
                                                aria-expanded="false">
                                                {{ Request::input('date_type', 'None') }}
                                                <span class="caret"></span>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-right">
                                                <li><a href="#">None</a></li>
                                                <li role="separator" class="divider"></li>
                                                <li><a href="#">Date Created</a></li>
                                                <li><a href="#">Date Updated</a></li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>

                                <input type="hidden" id="start_date" name="start_date" value= "{{ Request::input('start_date') }}">
                                <input type="hidden" id="end_date" name="end_date" value="{{ Request::input('end_date') }}">
                                <input type="hidden" id="date_type" name="date_type" value="{{ Request::input('date_type', 'None') }}">

                            </div>



                            <div class="form-group col-md-12">
                                <label class="col-sm-2 control-label">Is Complete?:</label>
                                <div class="col-sm-10">
                                    <select name="is_complete" class="form-control">
                                        <option value="">All</option>
                                        <option value="1" {{ (Request::input('is_complete') == 1) ? 'selected' : '' }}>Yes</option>
                                        <option value="0" {{ (Request::input('is_complete') == '0') ? 'selected' : '' }}>No</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group col-md-12">
                                <label class="col-sm-2 control-label">Origin</label>
                                <div class="col-sm-10">
                                    <select name="origin" class="form-control">
                                        <option value="">All</option>
                                        @foreach ($origin_metadata as $origin)
                                            <option
                                                value="{{$origin->id}}"
                                                {{ (Request::input('origin') == $origin->id) ? 'selected' : '' }}>
                                            {{$origin->label}}
                                        </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="form-group text-center col-md-12">
                                <input type="submit" class="btn btn-primary pull-right" value="Search">
                                <input type="button" id="reset" class="btn pull-right btn-danger margin-right" value="Reset">

                            </div>
                        </form>
                    </div>
                    <div class="clearfix"></div>
                    <hr>

                    <div class="">
                        <div class="parts-export pull-left">
                            <form action="" method="POST">
                                <input type="hidden" name="_token" value="{{{ csrf_token() }}}">
                                <input type="hidden" name="export" value="Export to CSV">

                                <button type="submit" class="btn btn-default" {{ ($result->total() > 0) ? '' : 'disabled' }}>
                                  <span class="glyphicon glyphicon-floppy-disk" aria-hidden="true"></span> Export to CSV
                                </button>
                            </form>
                        </div>
                        <div class="pull-right">
                            Total Search: <b>{{  number_format($result->total()) }}</b>
                        </div>
                    </div>
                    <div class="clearfix"></div>
                    <hr>

                    <div class="parts-pagination text-right">
                        {!! $result->render() !!}
                    </div>

                    <div class="parts-results">
                        <div class="parts-table">
                            <div class="parts-table-header parts-table-row">
                                <div class="parts-table-column">Part Number</div>
                                <div class="parts-table-column">Manufacturer</div>
                                <div class="parts-table-column">Description</div>
                                <div class="parts-table-column">Stock</div>
                                <div class="parts-table-column">Price</div>
                                <div class="parts-table-column">Is Complete</div>
                                <div class="parts-table-column">Missing Attributes</div>
                                <div class="parts-table-column">Origin</div>
                                <div class="parts-table-column">Created</div>
                                <div class="parts-table-column">Updated</div>
                            </div>
                            @if (count($result->items()) > 0)
                                @foreach ($result->items() as $part)
                                    <div class="parts-table-row">
                                        <div class="parts-table-column">{{ $part->part_number }}</div>
                                        <div class="parts-table-column">{{ $part->manufacturer }}</div>
                                        <div class="parts-table-column">{{ $part->description }}</div>
                                        <div class="parts-table-column">{{ $part->stock }}</div>
                                        <div class="parts-table-column">{{ $part->price ? 'Yes': 'No' }}</div>
                                        <div class="parts-table-column">{{ $part->is_complete ? 'Yes': 'No' }}</div>
                                        <div class="parts-table-column">{{ $part->missing_attributes }}</div>
                                        <div class="parts-table-column">{{ $part->source->label }}</div>
                                        <div class="parts-table-column">{{ $part->created_at }}</div>
                                        <div class="parts-table-column">{{ $part->updated_at }}</div>
                                    </div>
                                @endforeach
                            @else
                                <div class="parts-table-message">
                                    <div class="text-center">There are no parts matching this search.</div>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="parts-pagination text-right">
                        {!! $result->render() !!}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
