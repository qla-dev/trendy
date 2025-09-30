@extends('layouts/contentLayoutMaster')

@section('title', 'Uredi Korisnika - Račun')

@section('vendor-style')
  {{-- Page Css files --}}
  <link rel="stylesheet" href="{{ asset(mix('vendors/css/forms/select/select2.min.css')) }}">
  <link rel="stylesheet" href="{{ asset(mix('vendors/css/animate/animate.min.css')) }}">
  <link rel="stylesheet" href="{{ asset(mix('vendors/css/extensions/sweetalert2.min.css')) }}">
  <link rel="stylesheet" href="{{ asset(mix('vendors/css/tables/datatable/dataTables.bootstrap5.min.css')) }}">
  <link rel="stylesheet" href="{{ asset(mix('vendors/css/tables/datatable/responsive.bootstrap5.min.css')) }}">
  <link rel="stylesheet" href="{{ asset(mix('vendors/css/tables/datatable/buttons.bootstrap5.min.css')) }}">
  <link rel="stylesheet" href="{{ asset(mix('vendors/css/tables/datatable/rowGroup.bootstrap5.min.css')) }}">
@endsection

@section('page-style')
  {{-- Page Css files --}}
  <link rel="stylesheet" href="{{ asset(mix('css/base/plugins/forms/form-validation.css')) }}">
  <link rel="stylesheet" href="{{ asset(mix('css/base/plugins/extensions/ext-component-sweet-alerts.css')) }}">
@endsection

@section('content')
<section class="app-user-view-account">
  <div class="row">
    <!-- User Sidebar -->
    <div class="col-xl-4 col-lg-5 col-md-5 order-1 order-md-0">
      <!-- User Card -->
      <div class="card">
        <div class="card-body">
          <div class="user-avatar-section">
            <div class="d-flex align-items-center flex-column">
              <img
                class="img-fluid rounded mt-3 mb-2"
                src="{{asset('images/portrait/small/avatar-s-2.jpg')}}"
                height="110"
                width="110"
                alt="User avatar"
              />
              <div class="user-info text-center">
                <h4>{{ $user->name }}</h4>
                <span class="badge bg-light-{{ $user->role === 'admin' ? 'danger' : 'primary' }}">{{ ucfirst($user->role) }}</span>
              </div>
            </div>
          </div>
          <div class="d-flex justify-content-around my-2 pt-75">
            <div class="d-flex align-items-start me-2">
              <span class="badge bg-light-primary p-75 rounded">
                <i data-feather="check" class="font-medium-2"></i>
              </span>
              <div class="ms-75">
                <h4 class="mb-0">1.23k</h4>
                <small>Završeni Zadaci</small>
              </div>
            </div>
            <div class="d-flex align-items-start">
              <span class="badge bg-light-primary p-75 rounded">
                <i data-feather="briefcase" class="font-medium-2"></i>
              </span>
              <div class="ms-75">
                <h4 class="mb-0">568</h4>
                <small>Završeni Projekti</small>
              </div>
            </div>
          </div>
          <h4 class="fw-bolder border-bottom pb-50 mb-1">Detalji</h4>
          <form action="{{ route('app-user-update', $user->id) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="info-container">
              <div class="mb-3">
                <label class="form-label fw-bolder">Ime i Prezime:</label>
                <input type="text" class="form-control" name="name" value="{{ old('name', $user->name) }}" required>
                @error('name')
                  <div class="text-danger small">{{ $message }}</div>
                @enderror
              </div>
              <div class="mb-3">
                <label class="form-label fw-bolder">Korisničko Ime:</label>
                <input type="text" class="form-control" name="username" value="{{ old('username', $user->username) }}" required>
                @error('username')
                  <div class="text-danger small">{{ $message }}</div>
                @enderror
              </div>
              <div class="mb-3">
                <label class="form-label fw-bolder">Email:</label>
                <input type="email" class="form-control" name="email" value="{{ old('email', $user->email) }}" required>
                @error('email')
                  <div class="text-danger small">{{ $message }}</div>
                @enderror
              </div>
              <div class="mb-3">
                <label class="form-label fw-bolder">Uloga:</label>
                <select class="form-select" name="role" required>
                  <option value="admin" {{ old('role', $user->role) === 'admin' ? 'selected' : '' }}>Admin</option>
                  <option value="user" {{ old('role', $user->role) === 'user' ? 'selected' : '' }}>Korisnik</option>
                </select>
                @error('role')
                  <div class="text-danger small">{{ $message }}</div>
                @enderror
              </div>
              <div class="mb-3">
                <label class="form-label fw-bolder">Status:</label>
                <span class="badge bg-light-success">Aktivan</span>
              </div>
              <div class="mb-3">
                <label class="form-label fw-bolder">Datum Kreiranja:</label>
                <span>{{ $user->created_at->format('d.m.Y H:i') }}</span>
              </div>
            </div>
            <div class="d-flex justify-content-center pt-2">
              <button type="submit" class="btn btn-primary me-1">
                <i data-feather="save" class="me-25"></i>
                Sačuvaj Promjene
              </button>
              <a href="{{ route('app-user-list') }}" class="btn btn-outline-secondary">
                <i data-feather="arrow-left" class="me-25"></i>
                Nazad na Listu
              </a>
            </div>
          </form>
        </div>
      </div>
      <!-- /User Card -->
      <!-- Plan Card -->
      <div class="card border-primary">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start">
            <span class="badge bg-light-primary">Standardni</span>
            <div class="d-flex justify-content-center">
              <sup class="h5 pricing-currency text-primary mt-1 mb-0">$</sup>
              <span class="fw-bolder display-5 mb-0 text-primary">99</span>
              <sub class="pricing-duration font-small-4 ms-25 mt-auto mb-2">/mjesec</sub>
            </div>
          </div>
          <ul class="ps-1 mb-2">
            <li class="mb-50">10 Korisnika</li>
            <li class="mb-50">Do 10 GB prostora</li>
            <li>Osnovna Podrška</li>
          </ul>
          <div class="d-flex justify-content-between align-items-center fw-bolder mb-50">
            <span>Dani</span>
            <span>4 od 30 Dana</span>
          </div>
          <div class="progress mb-50" style="height: 8px">
            <div
              class="progress-bar"
              role="progressbar"
              style="width: 80%"
              aria-valuenow="65"
              aria-valuemax="100"
              aria-valuemin="80"
            ></div>
          </div>
          <span>4 dana preostalo</span>
          <div class="d-grid w-100 mt-2">
            <button class="btn btn-primary" data-bs-target="#upgradePlanModal" data-bs-toggle="modal">
              Nadogradi Plan
            </button>
          </div>
        </div>
      </div>
      <!-- /Plan Card -->
    </div>
    <!--/ User Sidebar -->

    <!-- User Content -->
    <div class="col-xl-8 col-lg-7 col-md-7 order-0 order-md-1">
      <!-- User Pills -->
      <ul class="nav nav-pills mb-2">
        <li class="nav-item">
          <a class="nav-link active" href="{{asset('app/user/view/account')}}">
            <i data-feather="user" class="font-medium-3 me-50"></i>
            <span class="fw-bold">Račun</span></a
          >
        </li>
        <li class="nav-item">
          <a class="nav-link" href="{{asset('app/user/view/security')}}">
            <i data-feather="lock" class="font-medium-3 me-50"></i>
            <span class="fw-bold">Sigurnost</span>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="{{asset('app/user/view/billing')}}">
            <i data-feather="bookmark" class="font-medium-3 me-50"></i>
            <span class="fw-bold">Naplata i Planovi</span>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="{{asset('app/user/view/notifications')}}">
            <i data-feather="bell" class="font-medium-3 me-50"></i><span class="fw-bold">Obavještenja</span>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="{{asset('app/user/view/connections')}}">
            <i data-feather="link" class="font-medium-3 me-50"></i><span class="fw-bold">Konekcije</span>
          </a>
        </li>
      </ul>
      <!--/ User Pills -->

      <!-- Project table -->
      <div class="card">
        <h4 class="card-header">Lista Projekata Korisnika</h4>
        <div class="table-responsive">
          <table class="table datatable-project">
            <thead>
              <tr>
                <th></th>
                <th>Projekat</th>
                <th class="text-nowrap">Ukupno Zadataka</th>
                <th>Napredak</th>
                <th>Sati</th>
              </tr>
            </thead>
          </table>
        </div>
      </div>
      <!-- /Project table -->

      <!-- Activity Timeline -->
      <div class="card">
        <h4 class="card-header">Timeline Aktivnosti Korisnika</h4>
        <div class="card-body pt-1">
          <ul class="timeline ms-50">
            <li class="timeline-item">
              <span class="timeline-point timeline-point-indicator"></span>
              <div class="timeline-event">
                <div class="d-flex justify-content-between flex-sm-row flex-column mb-sm-0 mb-1">
                  <h6>Prijava korisnika</h6>
                  <span class="timeline-event-time me-1">prije 12 min</span>
                </div>
                <p>Korisnik se prijavio u 14:12</p>
              </div>
            </li>
            <li class="timeline-item">
              <span class="timeline-point timeline-point-warning timeline-point-indicator"></span>
              <div class="timeline-event">
                <div class="d-flex justify-content-between flex-sm-row flex-column mb-sm-0 mb-1">
                  <h6>Sastanak sa John-om</h6>
                  <span class="timeline-event-time me-1">prije 45 min</span>
                </div>
                <p>React Project sastanak sa John-om @10:15</p>
                <div class="d-flex flex-row align-items-center mb-50">
                  <div class="avatar me-50">
                    <img
                      src="{{asset('images/portrait/small/avatar-s-7.jpg')}}"
                      alt="Avatar"
                      width="38"
                      height="38"
                    />
                  </div>
                  <div class="user-info">
                    <h6 class="mb-0">Leona Watkins (Klijent)</h6>
                    <p class="mb-0">CEO pixinvent-a</p>
                  </div>
                </div>
              </div>
            </li>
            <li class="timeline-item">
              <span class="timeline-point timeline-point-info timeline-point-indicator"></span>
              <div class="timeline-event">
                <div class="d-flex justify-content-between flex-sm-row flex-column mb-sm-0 mb-1">
                  <h6>Kreiranje novog React projekta za klijenta</h6>
                  <span class="timeline-event-time me-1">prije 2 dana</span>
                </div>
                <p>Dodavanje fajlova u novi design folder</p>
              </div>
            </li>
            <li class="timeline-item">
              <span class="timeline-point timeline-point-danger timeline-point-indicator"></span>
              <div class="timeline-event">
                <div class="d-flex justify-content-between flex-sm-row flex-column mb-sm-0 mb-1">
                  <h6>Kreiranje računa za klijenta</h6>
                  <span class="timeline-event-time me-1">prije 12 min</span>
                </div>
                <p class="mb-0">Kreiranje novih računa i slanje Leoni Watkins</p>
                <div class="d-flex flex-row align-items-center mt-50">
                  <img class="me-1" src="{{asset('images/icons/pdf.png')}}" alt="data.json" height="25" />
                  <h6 class="mb-0">Računi.pdf</h6>
                </div>
              </div>
            </li>
          </ul>
        </div>
      </div>
      <!-- /Activity Timeline -->

      <!-- Invoice table -->
      <div class="card">
        <table class="invoice-table table text-nowrap">
          <thead>
            <tr>
              <th></th>
              <th>#ID</th>
              <th><i data-feather="trending-up"></i></th>
              <th>UKUPNO Plaćeno</th>
              <th class="text-truncate">Datum Izdavanja</th>
              <th class="cell-fit">Akcije</th>
            </tr>
          </thead>
        </table>
      </div>
      <!-- /Invoice table -->
    </div>
    <!--/ User Content -->
  </div>
</section>

@include('content/_partials/_modals/modal-edit-user')
@include('content/_partials/_modals/modal-upgrade-plan')
@endsection

@section('vendor-script')
  {{-- Vendor js files --}}
  <script src="{{ asset(mix('vendors/js/forms/select/select2.full.min.js')) }}"></script>
  <script src="{{ asset(mix('vendors/js/forms/cleave/cleave.min.js')) }}"></script>
  <script src="{{ asset(mix('vendors/js/forms/cleave/addons/cleave-phone.us.js')) }}"></script>
  <script src="{{ asset(mix('vendors/js/forms/validation/jquery.validate.min.js')) }}"></script>
  {{-- data table --}}
  <script src="{{ asset(mix('vendors/js/extensions/moment.min.js')) }}"></script>
  <script src="{{ asset(mix('vendors/js/tables/datatable/jquery.dataTables.min.js')) }}"></script>
  <script src="{{ asset(mix('vendors/js/tables/datatable/dataTables.bootstrap5.min.js')) }}"></script>
  <script src="{{ asset(mix('vendors/js/tables/datatable/dataTables.responsive.min.js')) }}"></script>
  <script src="{{ asset(mix('vendors/js/tables/datatable/responsive.bootstrap5.js')) }}"></script>
  <script src="{{ asset(mix('vendors/js/tables/datatable/datatables.buttons.min.js')) }}"></script>
  <script src="{{ asset(mix('vendors/js/tables/datatable/jszip.min.js')) }}"></script>
  <script src="{{ asset(mix('vendors/js/tables/datatable/pdfmake.min.js')) }}"></script>
  <script src="{{ asset(mix('vendors/js/tables/datatable/vfs_fonts.js')) }}"></script>
  <script src="{{ asset(mix('vendors/js/tables/datatable/buttons.html5.min.js')) }}"></script>
  <script src="{{ asset(mix('vendors/js/tables/datatable/buttons.print.min.js')) }}"></script>
  <script src="{{ asset(mix('vendors/js/tables/datatable/dataTables.rowGroup.min.js')) }}"></script>
  <script src="{{ asset(mix('vendors/js/extensions/sweetalert2.all.min.js')) }}"></script>
  <script src="{{ asset(mix('vendors/js/extensions/polyfill.min.js')) }}"></script>
@endsection

@section('page-script')
  {{-- Page js files --}}
  <script src="{{ asset(mix('js/scripts/pages/modal-edit-user.js')) }}"></script>
  <script src="{{ asset(mix('js/scripts/pages/app-user-view-account.js')) }}"></script>
  <script src="{{ asset(mix('js/scripts/pages/app-user-view.js')) }}"></script>
  <script>
    // Auto-scroll to form if there are validation errors
    @if($errors->any())
      $(document).ready(function() {
        // Scroll to the form section if there are errors
        $('html, body').animate({
          scrollTop: $('form').offset().top - 100
        }, 500);
      });
    @endif
  </script>
@endsection
