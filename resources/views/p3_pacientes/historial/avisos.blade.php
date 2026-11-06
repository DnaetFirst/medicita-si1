@extends('layouts.app')
@section('content')
<div class="cycle-page"><h1>Mis avisos</h1><p>Los avisos permanecen aquí aunque no recibas el correo.</p>
@forelse($avisos as $aviso)<article class="card cycle-card"><h2>{{ $aviso->titulo }}</h2><small>{{ $aviso->created_at->format('d/m/Y H:i') }} · {{ $aviso->leido ? 'Leído' : 'Sin leer' }}</small><p>{{ $aviso->mensaje }}</p><div class="cycle-actions">@if($aviso->cita_id)<a class="btn btn-secondary" href="{{ route('citas.show', $aviso->cita_id) }}">Ver cita</a>@endif @unless($aviso->leido)<form method="POST" action="{{ route('avisos.leer', $aviso) }}">@csrf<button class="btn btn-primary">Marcar como leído</button></form>@endunless</div></article>@empty<p class="card cycle-card">No tienes avisos.</p>@endforelse
{{ $avisos->links() }}</div>
@endsection
