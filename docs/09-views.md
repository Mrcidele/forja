# Views e JSON resources

## Templates

Views ficam em `resources/views` com a extensão `.forja.php`; o nome usa
pontos para subpastas (`tasks.board` → `tasks/board.forja.php`). Injete
`Forja\View\Engine`:

```php
public function board(Engine $views): string
{
    return $views->render('tasks.board', ['tasks' => $tasks, 'csrf_token' => $token]);
}
```

O template é compilado para PHP puro e guardado em `var/cache/views`;
alterações no arquivo fonte forçam a recompilação.

```blade
@extends('layouts.app')

@section('title', 'Tarefas')

@section('content')
{{-- comentário que não vai para o HTML --}}
<h1>Olá, {{ $user->name }}</h1>        {{-- escapado com htmlspecialchars --}}
{!! $html !!}                          {{-- sem escape: só para conteúdo confiável --}}

@if(count($tasks) > 0)
    @foreach($tasks as $task)
        @include('tasks.item', ['task' => $task])
    @endforeach
@elseif($canCreate)
    <p>Crie a primeira tarefa.</p>
@else
    <p>Nada aqui.</p>
@endif

<form method="post">@csrf ...</form>
@{{ fica literal, útil para frameworks JS }}
@endsection
```

```blade
{{-- layouts/app.forja.php --}}
<title>@yield('title', 'Padrão')</title>
<main>@yield('content')</main>
```

Diretivas: `@if/@elseif/@else/@endif`, `@unless`, `@isset`, `@foreach`,
`@for`, `@while`, `@php ... @endphp` (ou `@php(expr)`), `@include`,
`@extends`, `@section/@endsection`, `@yield` e `@csrf` (usa a variável
`$csrf_token`). `$engine->share('chave', $valor)` disponibiliza um valor em
todas as views (use só para dados globais, nunca de uma requisição).

## JSON resources

```php
final class TaskResource extends JsonResource
{
    public function toArray(): array
    {
        return ['id' => $this->resource->id, 'titulo' => $this->resource->title];
    }

    public function with(): array      // opcional: dados extras no nível superior
    {
        return ['versao' => '1'];
    }
}

return TaskResource::make($task);                          // {"data": {...}, "versao": "1"}
return TaskResource::collection($tasks);                   // {"data": [...]}
return TaskResource::collection($paginator);               // {"data": [...], "meta": {...}}
return TaskResource::make($task)->response(201, ['Location' => '/api/tasks/1']);
```

Defina `protected ?string $wrap = null;` para devolver sem o envelope `data`.
