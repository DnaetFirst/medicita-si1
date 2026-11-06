<h1>{{ $aviso->titulo }}</h1><p>{{ $aviso->mensaje }}</p><a href="{{ route('citas.show', $aviso->cita_id) }}">Abrir el portal</a>
