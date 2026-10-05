@extends('layouts.app')

@section('title', 'Página <inicial>')

@section('content')
{{-- comentário não aparece --}}
<h1>Olá, {{ $user }}!</h1>
@if(count($items) > 0)
<ul>
@foreach($items as $item)
<li>{{ $item }}</li>
@endforeach
</ul>
@else
<p>Nada aqui.</p>
@endif
@endsection
