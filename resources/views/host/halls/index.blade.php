@extends('layouts.host')

@section('pageTitle')
{{ $accommodation ? 'سالن‌های '.$accommodation->name : 'سالن همایش' }}
@endsection

@section('content')
<div>
    @include('components.hall.index-list', ['panel' => 'host'])
</div>
@endsection
