@if ($paginator->hasPages())
    <nav style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem; padding: 0.75rem 0;">

        {{-- Info de registros --}}
        <div style="font-size: 0.825rem; color: var(--color-text-muted);">
            Mostrando
            <strong style="color: var(--color-text);">{{ $paginator->firstItem() }}</strong>
            –
            <strong style="color: var(--color-text);">{{ $paginator->lastItem() }}</strong>
            de
            <strong style="color: var(--color-text);">{{ $paginator->total() }}</strong>
            registros
        </div>

        {{-- Botones de página --}}
        <div style="display: flex; align-items: center; gap: 0.35rem; flex-wrap: wrap;">

            {{-- Anterior --}}
            @if ($paginator->onFirstPage())
                <span class="pagination-btn pagination-btn-disabled" aria-disabled="true">&laquo; Anterior</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="pagination-btn" rel="prev" aria-label="Anterior">&laquo; Anterior</a>
            @endif

            {{-- Páginas --}}
            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="pagination-btn pagination-btn-disabled">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="pagination-btn pagination-btn-active" aria-current="page">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="pagination-btn">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            {{-- Siguiente --}}
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="pagination-btn" rel="next" aria-label="Siguiente">Siguiente &raquo;</a>
            @else
                <span class="pagination-btn pagination-btn-disabled" aria-disabled="true">Siguiente &raquo;</span>
            @endif
        </div>
    </nav>
@endif
