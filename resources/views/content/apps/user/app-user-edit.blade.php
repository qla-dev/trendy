@extends('layouts/contentLayoutMaster')

@section('title', 'Uredi Korisnika')

@section('content')
<!-- users edit start -->
<section class="app-user-edit">
  <div class="card">
    <div class="card-header">
      <h4 class="card-title">Uredi Korisnika</h4>
    </div>
    <div class="card-body">
      <form action="{{ route('app-user-update', $user->id) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="row">
          <div class="col-md-6 mb-1">
            <label class="form-label" for="name">Ime i Prezime</label>
            <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $user->name) }}" required>
            @error('name')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>
          <div class="col-md-6 mb-1">
            <label class="form-label" for="username">Korisničko Ime</label>
            <input type="text" class="form-control @error('username') is-invalid @enderror" id="username" name="username" value="{{ old('username', $user->username) }}" required>
            @error('username')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>
        </div>
        <div class="row">
          <div class="col-md-12 mb-1">
            <label class="form-label" for="email">Email</label>
            <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email', $user->email) }}">
            @error('email')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>
        </div>
        <div class="row">
          <div class="col-md-12 mb-1">
            <label class="form-label" for="role">Uloga</label>
            @if($canManageRoles)
              <select class="form-select @error('role') is-invalid @enderror" id="role" name="role" required>
                <option value="">Odaberite ulogu</option>
                @foreach($roleOptions as $value => $label)
                  <option value="{{ $value }}" {{ old('role', $user->role) === $value ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
              </select>
            @else
              <input type="text" class="form-control" value="{{ $roleOptions[$user->role] ?? $user->role }}" disabled>
            @endif
            @error('role')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>
        </div>
        <div class="row">
          <div class="col-12 d-flex flex-sm-row flex-column mt-2">
            <button type="submit" class="btn btn-primary mb-1 mb-sm-0 me-0 me-sm-1">Ažuriraj Korisnika</button>
            <a href="{{ route('app-user-view-account', $user->id) }}" class="btn btn-outline-secondary">Otkaži</a>
          </div>
        </div>
      </form>
    </div>
  </div>
</section>
<!-- users edit ends -->
@endsection
