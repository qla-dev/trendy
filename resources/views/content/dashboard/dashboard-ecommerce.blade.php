
@extends('layouts/contentLayoutMaster')

@section('title', 'Kontrolna ploča')

@section('vendor-style')
  {{-- vendor css files --}}
  <link rel="stylesheet" href="{{ asset(mix('vendors/css/charts/apexcharts.css')) }}">
  <link rel="stylesheet" href="{{ asset(mix('vendors/css/extensions/toastr.min.css')) }}">
@endsection
@section('page-style')
  {{-- Page css files --}}
  <link rel="stylesheet" href="{{ asset(mix('css/base/pages/dashboard-ecommerce.css')) }}">
  <link rel="stylesheet" href="{{ asset(mix('css/base/plugins/charts/chart-apex.css')) }}">
  <link rel="stylesheet" href="{{ asset(mix('css/base/plugins/extensions/ext-component-toastr.css')) }}">
@endsection

@section('content')
<!-- Dashboard Ecommerce Starts -->
<section id="dashboard-ecommerce">
  <div class="row match-height">
    <!-- Medal Card -->
      <!-- Developer Meetup Card -->
  <div class="col-lg-4 col-md-6 col-12">
  <div class="card card-developer-meetup">
    <div class="meetup-img-wrapper rounded-top text-center">
      <img src="{{ asset('images/illustration/email.svg') }}" alt="CNC proizvodnja" height="170" />
    </div>

    <div class="card-body">
      <div class="meetup-header d-flex align-items-center">
        <div class="meetup-day">
          <h6 class="mb-0">ČET</h6>
          <h3 class="mb-0">24</h3>
        </div>
        <div class="my-auto">
          <h4 class="card-title mb-25">
            GROB-WERKE 
          </h4>
          <p class="card-text mb-0">
            Sastanak o saradnji vezanoj za CNC obradu metala – kapaciteti i rokovi.
          </p>
        </div>
      </div>

      <!-- Datum i vrijeme -->
      <div class="mt-0">
        <div class="avatar float-start bg-light-primary rounded me-1">
          <div class="avatar-content">
            <i data-feather="calendar" class="avatar-icon font-medium-3"></i>
          </div>
        </div>
        <div class="more-info">
          <h6 class="mb-0">Četvrtak, 24. decembar 2025.</h6>
          <small>09:00 – 10:30</small>
        </div>
      </div>

      <!-- Lokacija + šta mogu očekivati -->
      <div class="mt-2">
        <div class="avatar float-start bg-light-primary rounded me-1">
          <div class="avatar-content">
            <i data-feather="map-pin" class="avatar-icon font-medium-3"></i>
          </div>
        </div>
        <div class="more-info">
          <h6 class="mb-0">Online sastanak (Teams / Zoom)</h6>
          <small>
            Predstavljanje mašina (CNC glodanje i tokarenje), tipičnih serija, tolerancija,
            površinske obrade.
          </small>
        </div>
      </div>

      <!-- Učesnici: s kim imaju sastanak -->
      <div class="avatar-group mt-1">
        <div
          data-bs-toggle="tooltip"
          data-popup="tooltip-custom"
          data-bs-placement="bottom"
          title="Direktor proizvodnje – Trendy d.o.o."
          class="avatar pull-up"
        >
          <img src="{{ asset('images/portrait/small/avatar-s-9.jpg') }}" alt="Avatar" width="33" height="33" />
        </div>
        <div
          data-bs-toggle="tooltip"
          data-popup="tooltip-custom"
          data-bs-placement="bottom"
          title="Tehnički inženjer – Trendy d.o.o."
          class="avatar pull-up"
        >
          <img src="{{ asset('images/portrait/small/avatar-s-6.jpg') }}" alt="Avatar" width="33" height="33" />
        </div>
        <div
          data-bs-toggle="tooltip"
          data-popup="tooltip-custom"
          data-bs-placement="bottom"
          title="Predstavnik nabavke vašeg preduzeća"
          class="avatar pull-up"
        >
          <img src="{{ asset('images/portrait/small/avatar-s-8.jpg') }}" alt="Avatar" width="33" height="33" />
        </div>
        <h6 class="align-self-center cursor-pointer ms-50 mb-0">+ još učesnika po potrebi</h6>
      </div>
    </div>
  </div>
</div>

    <!--/ Developer Meetup Card -->
    <!--/ Medal Card -->

    <!-- Statistics Card -->
    <div class="col-xl-8 col-md-6 col-12">
      <div class="card card-statistics">
     
        <div class="card-body statistics-body">
          <div class="row">
            <div class="col-xl-3 col-sm-6 col-12 mb-2 mb-xl-0">
              <div class="d-flex flex-row">
                <div class="avatar bg-light-primary me-2">
                  <div class="avatar-content">
                    <i data-feather="trending-up" class="avatar-icon"></i>
                  </div>
                </div>
                <div class="my-auto">
                  <h4 class="fw-bolder mb-0">230k</h4>
                  <p class="card-text font-small-3 mb-0">Prodaja</p>
                </div>
              </div>
            </div>
            <div class="col-xl-3 col-sm-6 col-12 mb-2 mb-xl-0">
              <div class="d-flex flex-row">
                <div class="avatar bg-light-info me-2">
                  <div class="avatar-content">
                    <i data-feather="user" class="avatar-icon"></i>
                  </div>
                </div>
                <div class="my-auto">
                  <h4 class="fw-bolder mb-0">8.549k</h4>
                  <p class="card-text font-small-3 mb-0">Kupci</p>
                </div>
              </div>
            </div>
            <div class="col-xl-3 col-sm-6 col-12 mb-2 mb-sm-0">
              <div class="d-flex flex-row">
                <div class="avatar bg-light-danger me-2">
                  <div class="avatar-content">
                    <i data-feather="box" class="avatar-icon"></i>
                  </div>
                </div>
                <div class="my-auto">
                  <h4 class="fw-bolder mb-0">1.423k</h4>
                  <p class="card-text font-small-3 mb-0">Proizvodi</p>
                </div>
              </div>
            </div>
            <div class="col-xl-3 col-sm-6 col-12">
              <div class="d-flex flex-row">
                <div class="avatar bg-light-success me-2">
                  <div class="avatar-content">
                    <i data-feather="dollar-sign" class="avatar-icon"></i>
                  </div>
                </div>
                <div class="my-auto">
                  <h4 class="fw-bolder mb-0">9745 KM</h4>
                  <p class="card-text font-small-3 mb-0">Prihodi</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
          <!-- Revenue Report Card -->

      <div class="card card-revenue-budget">
        <div class="row mx-0">
          <div class="col-md-8 col-12 revenue-report-wrapper">
            <div class="d-sm-flex justify-content-between align-items-center mb-3">
              <h4 class="card-title mb-50 mb-sm-0">Izvještaj o prihodima</h4>
              <div class="d-flex align-items-center">
                <div class="d-flex align-items-center me-2">
                  <span class="bullet bullet-primary font-small-3 me-50 cursor-pointer"></span>
                  <span>Zarada</span>
                </div>
                <div class="d-flex align-items-center ms-75">
                  <span class="bullet bullet-warning font-small-3 me-50 cursor-pointer"></span>
                  <span>Troškovi</span>
                </div>
              </div>
            </div>
            <div id="revenue-report-chart"></div>
          </div>
          <div class="col-md-4 col-12 budget-wrapper">
            <div class="btn-group">
              <button
                type="button"
                class="btn btn-outline-primary btn-sm dropdown-toggle budget-dropdown"
                data-bs-toggle="dropdown"
                aria-haspopup="true"
                aria-expanded="false"
              >
                2026
              </button>
              <div class="dropdown-menu">
                <a class="dropdown-item" href="#">2026</a>
                <a class="dropdown-item" href="#">2025</a>
                <a class="dropdown-item" href="#">2024</a>
              </div>
            </div>
            <h2 class="mb-25">25.852 KM</h2>
            <div class="d-flex justify-content-center">
              <span class="fw-bolder me-25">Budžet:</span>
              <span>56.800 KM</span>
            </div>
            <div id="budget-chart"></div>
            <button type="button" class="btn btn-primary">Povećaj budžet</button>
          </div>
        </div>
      </div>
   
    </div>
    <!--/ Statistics Card -->
  </div>

  <div class="row match-height">
    <div class="col-lg-4 col-12">
      <div class="row match-height">
        <!-- Bar Chart - Orders -->
        <div class="col-lg-6 col-md-3 col-6">
          <div class="card">
            <div class="card-body pb-50">
              <h6>Narudžbe</h6>
              <h2 class="fw-bolder mb-1">2,76k</h2>
              <div id="statistics-order-chart"></div>
            </div>
          </div>
        </div>
        <!--/ Bar Chart - Orders -->

        <!-- Line Chart - Profit -->
        <div class="col-lg-6 col-md-3 col-6">
          <div class="card card-tiny-line-stats">
            <div class="card-body pb-50">
              <h6>Dobit</h6>
              <h2 class="fw-bolder mb-1">6,24k</h2>
              <div id="statistics-profit-chart"></div>
            </div>
          </div>
        </div>
        <!--/ Line Chart - Profit -->

        <!-- Earnings Card -->
        <div class="col-lg-12 col-md-6 col-12">
          <div class="card earnings-card">
            <div class="card-body">
              <div class="row">
                <div class="col-6">
                  <h4 class="card-title mb-1">Zarada</h4>
                  <div class="font-small-2">Ovaj mjesec</div>
                  <h5 class="mb-1">4055,56 KM</h5>
                  <p class="card-text text-muted font-small-2">
                    <span class="fw-bolder">68.2%</span><span> više zarade nego prošlog mjeseca.</span>
                  </p>
                </div>
                <div class="col-6">
                  <div id="earnings-chart"></div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <!--/ Earnings Card -->
      </div>
    </div>

    <div class="col-lg-8 col-12">
      <div class="card card-company-table">
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover borderless mb-0">
              <thead>
                <tr>
                  <th>Radni Nalog</th>
                  <th>Klijent</th>
                  <th>Početak</th>
                  <th>Status</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                @foreach ($latestOrders as $order)
                  <tr>
                    <td class="text-nowrap">
                      <div class="fw-bolder mb-0">{{ $order->work_order_number }}</div>
                      <small class="text-muted">{{ $order->linked_document }}</small>
                    </td>
                    <td>{{ $order->client_name }}</td>
                    <td>{{ optional($order->planned_start)->format('d.m.Y') ?? 'N/A' }}</td>
                    <td>
                      <span class="badge rounded-pill bg-light-primary text-primary">
                        {{ $order->status ?? 'N/A' }}
                      </span>
                    </td>
                    <td class="text-nowrap">
                      <a href="{{ url('app/invoice/preview/' . $order->id) }}" class="btn btn-sm btn-icon btn-outline-primary" data-bs-toggle="tooltip" data-bs-placement="top" title="Pregled radnog naloga">
                        <i data-feather="eye"></i>
                      </a>
                    </td>
                  </tr>
                @endforeach
                @if ($latestOrders->isEmpty())
                  <tr>
                    <td colspan="6" class="text-center text-muted">Nema dostupnih naloga.</td>
                  </tr>
                @endif
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
    <!--/ Revenue Report Card -->
  </div>


</section>
<!-- Dashboard Ecommerce ends -->
@endsection

@section('vendor-script')
  {{-- vendor files --}}
  <script src="{{ asset(mix('vendors/js/charts/apexcharts.min.js')) }}"></script>
  <script src="{{ asset(mix('vendors/js/extensions/toastr.min.js')) }}"></script>
@endsection
@section('page-script')
  {{-- Page js files --}}
  <script src="{{ asset(mix('js/scripts/pages/dashboard-ecommerce.js')) }}"></script>
@endsection
