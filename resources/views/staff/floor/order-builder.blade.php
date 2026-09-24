<x-layouts.staff title="Take order — table {{ $table->table_number }}" page-title="Take order" page-sub="Table {{ $table->table_number }}">
<div data-testid="staff-order-builder">

  @if ($errors->any())
    <div class="auth-error" role="alert">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
      <span>{{ $errors->first() }}</span>
    </div>
  @endif

  <form method="POST" action="{{ route('staff.tables.order.store', $table) }}" id="staff-order-form">
    @csrf

    <div id="staff-order-lines">
      <div class="staff-order-line" data-testid="staff-order-line-0">
        <label for="line-0-item-size">Item</label>
        <select id="line-0-item-size" name="lines[0][item_size]" required data-testid="staff-order-line-item-0">
          @foreach ($items as $item)
            @foreach ($item->sizes as $size)
              <option value="{{ $item->item_id }}:{{ $size->size_id }}">{{ $item->item_name }} &mdash; {{ $size->size_name }} (@money($size->price))</option>
            @endforeach
          @endforeach
        </select>

        <label for="line-0-quantity">Qty</label>
        <input type="number" id="line-0-quantity" name="lines[0][quantity]" min="1" max="20" value="1" required data-testid="staff-order-line-quantity-0">

        <label for="line-0-request">Special request</label>
        <input type="text" id="line-0-request" name="lines[0][special_request]" maxlength="200" data-testid="staff-order-line-request-0">
      </div>
    </div>

    <button type="button" id="staff-order-add-line" class="btn btn-ghost" data-testid="staff-order-add-line">+ Add another item</button>
    <button type="submit" class="btn btn-solid" data-testid="staff-order-submit">Create order</button>
  </form>

</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const container = document.getElementById('staff-order-lines');
  const addButton = document.getElementById('staff-order-add-line');
  if (!container || !addButton) {
    return;
  }

  addButton.addEventListener('click', () => {
    const index = container.children.length;
    const template = container.children[0].cloneNode(true);

    template.dataset.testid = `staff-order-line-${index}`;
    template.querySelectorAll('[id], [name]').forEach((field) => {
      if (field.id) {
        field.id = field.id.replace(/-0(?=-|$)/, `-${index}`);
      }
      if (field.name) {
        field.name = field.name.replace('[0]', `[${index}]`);
      }
      if (field.tagName === 'INPUT' && field.type !== 'number') {
        field.value = '';
      }
    });
    template.querySelectorAll('label').forEach((label) => {
      if (label.htmlFor) {
        label.htmlFor = label.htmlFor.replace(/-0(?=-|$)/, `-${index}`);
      }
    });

    container.appendChild(template);
  });
});
</script>
@endpush
</x-layouts.staff>
