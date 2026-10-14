@extends('layouts/contentLayoutMaster')

@section('title', 'Uloge')

@section('page-style')
<style>
  #roleModal .modal-dialog, #roleModal .modal-content, #roleModal form { max-height: calc(100vh - 2rem); }
  #roleModal .modal-content { overflow: hidden; }
  #roleModal form { display: flex; flex-direction: column; min-height: 0; }
  #roleModal .modal-body { flex: 1 1 auto; min-height: 0; overflow-y: auto; overscroll-behavior: contain; }
  #roleModal .modal-header, #roleModal .modal-footer { flex-shrink: 0; }
  #roleModal .modal-header .btn-close, #roleModal .modal-header .btn-close:hover, #roleModal .modal-header .btn-close:focus { margin: 0; transform: none; }
  #roleModal .role-section-expand svg { transition: transform .2s ease; }
  #roleModal .role-section-expand[aria-expanded="true"] svg { transform: rotate(180deg); }
  .role-action-cell { width: 1%; white-space: nowrap; text-align: right; }
  .role-name-button:hover, .role-name-button:focus-visible { text-decoration: underline; }
</style>
@endsection

@section('content_header_right')
  <button type="button" class="btn btn-primary" id="addRoleButton" data-bs-toggle="modal" data-bs-target="#roleModal">
    <i data-feather="plus" class="me-50"></i> Dodaj ulogu
  </button>
@endsection

@section('content')
<section class="app-role-list">
  <div class="card">
    <div class="card-header border-bottom"><h4 class="card-title">Uloge</h4></div>
    <div class="table-responsive">
      <table class="table mb-0">
        <thead class="table-light">
          <tr><th>Naziv uloge</th><th>Korisnici</th><th>Pristup opcijama</th><th class="role-action-cell">Akcije</th></tr>
        </thead>
        <tbody>
          @foreach($roleList as $key => $role)
            <tr>
              <td>
                <button type="button" class="btn btn-link p-0 text-body fw-bolder role-name-button role-edit-button" data-role-key="{{ $key }}" data-bs-toggle="modal" data-bs-target="#roleModal">{{ $role['name'] }}</button>
                @if($role['builtin']) <span class="badge bg-light-secondary ms-50">Ugrađena</span> @endif
              </td>
              <td>{{ $userCounts[$key] ?? 0 }}</td>
              <td>{{ count($role['slugs'] ?? []) }} stavki</td>
              <td class="role-action-cell">
                <div class="app-table-action-group">
                  <button type="button" class="btn btn-sm app-table-action-btn app-table-action-btn--info role-edit-button" data-role-key="{{ $key }}" data-bs-toggle="modal" data-bs-target="#roleModal" title="Uredi ulogu" aria-label="Uredi ulogu {{ $role['name'] }}">
                    <i data-feather="edit-2" class="font-small-4"></i>
                  </button>
                  @if(!$role['builtin'])
                    <form action="{{ route('app-role-destroy', $key) }}" method="POST" class="d-inline role-delete-form" data-role-name="{{ $role['name'] }}">
                      @csrf @method('DELETE')
                      <button type="submit" class="btn btn-sm app-table-action-btn app-table-action-btn--danger" title="Obriši ulogu" aria-label="Obriši ulogu {{ $role['name'] }}">
                        <i data-feather="trash-2" class="font-small-4"></i>
                      </button>
                    </form>
                  @else
                    <button type="button" class="btn btn-sm app-table-action-btn app-table-action-btn--danger" disabled title="Ugrađenu ulogu nije moguće obrisati" aria-label="Ugrađenu ulogu nije moguće obrisati"><i data-feather="trash-2" class="font-small-4"></i></button>
                  @endif
                </div>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
</section>

<div class="modal fade" id="roleModal" tabindex="-1" aria-labelledby="roleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <form id="roleForm" action="{{ route('app-user-roles-store') }}" method="POST">
        @csrf
        <input type="hidden" name="_method" id="roleMethod" value="POST">
        <input type="hidden" name="role_items_submitted" value="1">
        <input type="hidden" name="editing_role_key" id="editingRoleKey" value="">
        <div class="modal-header">
          <h5 class="modal-title" id="roleModalLabel">Dodaj ulogu</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zatvori"></button>
        </div>
        <div class="modal-body">
          @if($errors->getBag('createRole')->any() || $errors->getBag('editRole')->any())
            <div class="alert alert-danger">
              @foreach(($errors->getBag('createRole')->any() ? $errors->getBag('createRole') : $errors->getBag('editRole'))->all() as $message)
                <div>{{ $message }}</div>
              @endforeach
            </div>
          @endif
          <label class="form-label" for="roleName">Naziv uloge</label>
          <input class="form-control" id="roleName" name="role_name" maxlength="100" required>
          <p class="mt-2 mb-1">Odaberite stavke bočnog menija dostupne ovoj ulozi. Radni nalozi su odabrani pri dodavanju nove uloge.</p>
          @foreach($roleSections as $section => $items)
            <div class="card border mb-1 role-section">
              <div class="card-body py-1">
                <div class="d-flex align-items-center justify-content-between gap-1">
                  <div class="form-check mb-0">
                    <input class="form-check-input role-section-toggle" type="checkbox" id="role-section-{{ $loop->index }}">
                    <label class="form-check-label fw-bold" for="role-section-{{ $loop->index }}">{{ $section }} — cijela sekcija</label>
                  </div>
                  <button class="btn btn-sm btn-link text-body p-0 role-section-expand" type="button" data-bs-toggle="collapse" data-bs-target="#role-section-items-{{ $loop->index }}" aria-expanded="false" aria-controls="role-section-items-{{ $loop->index }}">
                    Stavke <i data-feather="chevron-down" class="ms-25"></i>
                  </button>
                </div>
                <div class="collapse" id="role-section-items-{{ $loop->index }}">
                  <div class="row pt-1">
                    @foreach($items as $item)
                      <div class="col-md-6 mb-50">
                        <div class="form-check">
                          <input class="form-check-input role-item" type="checkbox" name="items[]" value="{{ $item['slug'] }}" id="role-item-{{ $loop->parent->index }}-{{ $loop->index }}">
                          <label class="form-check-label" for="role-item-{{ $loop->parent->index }}-{{ $loop->index }}">{{ $item['name'] }}</label>
                        </div>
                      </div>
                    @endforeach
                  </div>
                </div>
              </div>
            </div>
          @endforeach
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Odustani</button>
          <button type="submit" class="btn btn-primary">Sačuvaj ulogu</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection

@section('page-script')
<script>
  const roleList = @json($roleList);
  const defaultItems = @json(array_column($roleSections['Radni nalozi'] ?? [], 'slug'));
  const roleForm = document.getElementById('roleForm');
  const roleModal = document.getElementById('roleModal');

  function setRoleSelection(selected) {
    roleModal.querySelectorAll('.role-item').forEach(item => item.checked = selected.includes(item.value));
    roleModal.querySelectorAll('.role-section').forEach(section => {
      const items = Array.from(section.querySelectorAll('.role-item'));
      const toggle = section.querySelector('.role-section-toggle');
      toggle.checked = items.length > 0 && items.every(item => item.checked);
      toggle.indeterminate = items.some(item => item.checked) && !toggle.checked;
    });
  }

  function prepareRoleForm(key) {
    const role = key ? roleList[key] : null;
    document.getElementById('roleModalLabel').textContent = role ? 'Uredi ulogu' : 'Dodaj ulogu';
    document.getElementById('roleName').value = role ? role.name : '';
    document.getElementById('roleName').readOnly = Boolean(role && role.builtin);
    document.getElementById('editingRoleKey').value = key || '';
    document.getElementById('roleMethod').value = role ? 'PUT' : 'POST';
    roleForm.action = role ? @json(url('app/roles')) + '/' + encodeURIComponent(key) : @json(route('app-user-roles-store'));
    setRoleSelection(role ? role.slugs : defaultItems);
    roleModal.querySelectorAll('.collapse.show').forEach(panel => bootstrap.Collapse.getOrCreateInstance(panel, {toggle: false}).hide());
  }

  roleModal.addEventListener('show.bs.modal', event => {
    if (event.relatedTarget) prepareRoleForm(event.relatedTarget.dataset.roleKey || '');
  });
  roleModal.querySelectorAll('.role-section').forEach(section => {
    const toggle = section.querySelector('.role-section-toggle');
    const items = Array.from(section.querySelectorAll('.role-item'));
    toggle.addEventListener('change', () => { items.forEach(item => item.checked = toggle.checked); toggle.indeterminate = false; });
    items.forEach(item => item.addEventListener('change', () => {
      toggle.checked = items.every(child => child.checked);
      toggle.indeterminate = items.some(child => child.checked) && !toggle.checked;
    }));
  });
  document.querySelectorAll('.role-delete-form').forEach(form => form.addEventListener('submit', event => {
    if (!confirm('Obrisati ulogu "' + form.dataset.roleName + '"?')) event.preventDefault();
  }));

  @if($errors->getBag('createRole')->any() || $errors->getBag('editRole')->any())
    document.addEventListener('DOMContentLoaded', () => {
      const key = @json(old('editing_role_key', ''));
      prepareRoleForm(key);
      document.getElementById('roleName').value = @json(old('role_name', ''));
      setRoleSelection(@json((array) old('items', [])));
      bootstrap.Modal.getOrCreateInstance(roleModal).show();
    });
  @endif
</script>
@endsection
