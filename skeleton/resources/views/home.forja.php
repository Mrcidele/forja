@extends('layouts.app')

@section('title', $name)

@section('content')
<h1>{{ $name }}</h1>
<p>Sua aplicação está no ar. Edite <code>app/Http/Controllers/HomeController.php</code> para começar.</p>
@endsection
