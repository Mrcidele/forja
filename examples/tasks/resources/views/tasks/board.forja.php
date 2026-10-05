@extends('layouts.app')

@section('title', 'Quadro de tarefas')

@section('content')
<h1>Quadro de tarefas</h1>

@isset($status)
<p class="flash">{{ $status }}</p>
@endisset

<form method="post" action="/tasks" class="new-task">
    @csrf
    <input name="title" placeholder="Nova tarefa" required minlength="3">
    <select name="projectId">
        @foreach($projects as $project)
        <option value="{{ $project->id }}">{{ $project->name }}</option>
        @endforeach
    </select>
    <button>Adicionar</button>
</form>

<section class="board">
@foreach($statuses as $column)
    <div class="column">
        <h2>{{ $column->label() }} ({{ count($board[$column->value]) }})</h2>
        @foreach($board[$column->value] as $task)
        <article>
            <strong>{{ $task->title }}</strong>
            <small>{{ $task->project?->name }}@if($task->dueDate) · até {{ $task->dueDate->format('d/m/Y') }}@endif</small>
        </article>
        @endforeach
    </div>
@endforeach
</section>
@endsection
