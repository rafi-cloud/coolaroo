@if ($paginator->hasPages())
  <nav class="pagination-nav pagination-simple" role="navigation" aria-label="Pagination" data-testid="pagination-simple">
    <ul class="pagination-links">
      {{-- Previous Page Link --}}
      @if ($paginator->onFirstPage())
        <li class="page-item disabled" aria-disabled="true">
          <span class="page-link" aria-hidden="true">&lsaquo; Previous</span>
        </li>
      @else
        <li class="page-item">
          <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev" data-testid="pagination-prev">&lsaquo; Previous</a>
        </li>
      @endif

      {{-- Next Page Link --}}
      @if ($paginator->hasMorePages())
        <li class="page-item">
          <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next" data-testid="pagination-next">Next &rsaquo;</a>
        </li>
      @else
        <li class="page-item disabled" aria-disabled="true">
          <span class="page-link" aria-hidden="true">Next &rsaquo;</span>
        </li>
      @endif
    </ul>
  </nav>
@endif
