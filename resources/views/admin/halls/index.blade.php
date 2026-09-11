@extends('layouts.admin')

@section('pageTitle')
{{ $accommodation ? 'سالن‌های '.$accommodation->name : 'سالن همایش' }}
@endsection

@section('content')
<div>
    @include('components.hall.index-list', ['panel' => 'admin'])
</div>
@endsection
