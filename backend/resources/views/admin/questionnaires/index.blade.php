@extends('layouts.admin')
@section('title', 'Questionnaires')
@section('body')
<main class="content"><div class="actions"><a href="{{ route('admin.dashboard') }}">Dashboard</a><a class="button" href="{{ route('admin.questionnaires.create') }}">New questionnaire</a></div><h1>Questionnaires</h1>
@if(session('status'))<div class="status">{{ session('status') }}</div>@endif
<div class="table-wrap"><table><thead><tr><th>Title</th><th>Version</th><th>Status</th><th>Questions</th><th>Bands</th><th></th></tr></thead><tbody>
@forelse($questionnaires as $item)<tr><td>{{ $item->title }}</td><td>{{ $item->type }} v{{ $item->version }}</td><td>{{ $item->status }}{{ $item->is_active ? ' / active' : '' }}</td><td>{{ $item->questions_count }}</td><td>{{ $item->score_bands_count }}</td><td class="actions"><a href="{{ route('admin.questionnaires.edit', $item) }}">Edit</a><form method="POST" action="{{ route('admin.questionnaires.destroy', $item) }}">@csrf @method('DELETE')<button class="button button-danger" type="submit">Archive</button></form></td></tr>@empty<tr><td colspan="6">No questionnaires yet.</td></tr>@endforelse
</tbody></table></div>{{ $questionnaires->links() }}</main>
@endsection
