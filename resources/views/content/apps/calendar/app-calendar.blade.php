@extends('layouts/contentLayoutMaster')

@section('title', 'Kalendar')

@section('vendor-style')
  <!-- Vendor css files -->
  <link rel="stylesheet" href="{{ asset(mix('vendors/css/calendars/fullcalendar.min.css')) }}">
  <link rel="stylesheet" href="{{ asset(mix('vendors/css/forms/select/select2.min.css')) }}">
  <link rel="stylesheet" href="{{ asset(mix('vendors/css/pickers/flatpickr/flatpickr.min.css')) }}">
@endsection

@section('page-style')
  <!-- Page css files -->
  <link rel="stylesheet" href="{{ asset(mix('css/base/plugins/forms/pickers/form-flat-pickr.css')) }}">
  <link rel="stylesheet" href="{{ asset(mix('css/base/pages/app-calendar.css')) }}">
  <link rel="stylesheet" href="{{ asset(mix('css/base/plugins/forms/form-validation.css')) }}">
  <style>
    .app-calendar .fc .fc-more-popover {
      max-height: min(72vh, 480px);
      display: flex;
      flex-direction: column;
    }
    .app-calendar .fc .fc-more-popover .fc-popover-header {
      flex: 0 0 auto;
    }
    .app-calendar .fc .fc-more-popover .fc-popover-body {
      max-height: min(62vh, 390px);
      overflow-y: auto;
      overflow-x: hidden;
      overscroll-behavior: contain;
      scrollbar-gutter: stable;
      padding-right: 0.15rem;
    }
    .app-calendar .fc .fc-list-event-title a.calendar-list-event-link {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 0.75rem;
      width: 100%;
      min-width: 0;
    }
    .app-calendar .fc .fc-list-event-title .calendar-list-event-title-text {
      min-width: 0;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
    .app-calendar .fc .fc-list-event-title .calendar-list-priority-badge {
      flex: 0 0 auto;
      font-size: 0.75rem;
      line-height: 1.2;
    }
  </style>
@endsection

@section('content')
<!-- Full calendar start -->
<section>
  <div
    class="app-calendar overflow-hidden border"
    data-work-orders-calendar-url="{{ route('api.work-orders.calendar') }}"
    data-work-order-preview-base-url="{{ url('app/invoice/preview') }}"
  >
    <div class="row g-0">
      <!-- Sidebar -->
      <div class="col app-calendar-sidebar flex-grow-0 overflow-hidden d-flex flex-column" id="app-calendar-sidebar">
        <div class="sidebar-wrapper">
          <div class="card-body d-grid gap-1">
            <button
              class="btn btn-primary btn-toggle-sidebar w-100"
              data-bs-toggle="modal"
              data-bs-target="#add-new-sidebar"
            >
              <span class="align-middle">Dodaj radni nalog</span>
            </button>
            <button type="button" class="btn btn-outline-primary btn-add-meeting w-100">
              <span class="align-middle">Dodaj sastanak</span>
            </button>
          </div>
          <div class="card-body pb-0 pt-0">
            <h5 class="section-label mb-1">
              <span class="align-middle">Filter prioriteta</span>
            </h5>
            <div class="form-check mb-1">
              <input type="checkbox" class="form-check-input select-all-priority" id="select-all-priority" checked />
              <label class="form-check-label d-flex justify-content-between align-items-center" for="select-all-priority">
                <span>Svi</span>
                <span class="calendar-priority-count" data-priority-key="svi">0</span>
              </label>
            </div>
            <div class="calendar-priority-filter">
              <div class="form-check form-check-danger mb-1">
                <input
                  type="checkbox"
                  class="form-check-input input-filter-priority"
                  id="priority-high"
                  data-value="1"
                  checked
                />
                <label class="form-check-label d-flex justify-content-between align-items-center" for="priority-high">
                  <span>1 - Visoki prioritet</span>
                  <span class="calendar-priority-count" data-priority-key="1">0</span>
                </label>
              </div>
              <div class="form-check form-check-warning mb-1">
                <input
                  type="checkbox"
                  class="form-check-input input-filter-priority"
                  id="priority-normal"
                  data-value="5"
                  checked
                />
                <label class="form-check-label d-flex justify-content-between align-items-center" for="priority-normal">
                  <span>5 - Uobič. prioritet</span>
                  <span class="calendar-priority-count" data-priority-key="5">0</span>
                </label>
              </div>
              <div class="form-check form-check-info mb-1">
                <input
                  type="checkbox"
                  class="form-check-input input-filter-priority"
                  id="priority-low"
                  data-value="10"
                  checked
                />
                <label class="form-check-label d-flex justify-content-between align-items-center" for="priority-low">
                  <span>10 - Niski prioritet</span>
                  <span class="calendar-priority-count" data-priority-key="10">0</span>
                </label>
              </div>
              <div class="form-check form-check-secondary mb-2">
                <input
                  type="checkbox"
                  class="form-check-input input-filter-priority"
                  id="priority-samples"
                  data-value="15"
                  checked
                />
                <label class="form-check-label d-flex justify-content-between align-items-center" for="priority-samples">
                  <span>15 - Uzorci</span>
                  <span class="calendar-priority-count" data-priority-key="15">0</span>
                </label>
              </div>
            </div>
            <h5 class="section-label mb-1">
              <span class="align-middle">Filter statusa</span>
            </h5>
            <div class="form-check mb-1">
              <input type="checkbox" class="form-check-input select-all-status" id="select-all-status" checked />
              <label class="form-check-label d-flex justify-content-between align-items-center" for="select-all-status">
                <span>Svi</span>
                <span class="calendar-filter-count" data-stat-key="svi">0</span>
              </label>
            </div>
            <div class="calendar-events-filter">
              <div class="form-check form-check-info mb-1">
                <input
                  type="checkbox"
                  class="form-check-input input-filter-status"
                  id="planiran"
                  data-value="planiran"
                  checked
                />
                <label class="form-check-label d-flex justify-content-between align-items-center" for="planiran">
                  <span>Planiran</span>
                  <span class="calendar-filter-count" data-stat-key="planiran">0</span>
                </label>
              </div>
              <div class="form-check form-check-success mb-1">
                <input
                  type="checkbox"
                  class="form-check-input input-filter-status"
                  id="otvoren"
                  data-value="otvoren"
                  checked
                />
                <label class="form-check-label d-flex justify-content-between align-items-center" for="otvoren">
                  <span>Otvoren</span>
                  <span class="calendar-filter-count" data-stat-key="otvoren">0</span>
                </label>
              </div>
              <div class="form-check form-check-warning mb-1">
                <input type="checkbox" class="form-check-input input-filter-status" id="rezerviran" data-value="rezerviran" checked />
                <label class="form-check-label d-flex justify-content-between align-items-center" for="rezerviran">
                  <span>Rezerviran</span>
                  <span class="calendar-filter-count" data-stat-key="rezerviran">0</span>
                </label>
              </div>
              <div class="form-check form-check-primary mb-1">
                <input
                  type="checkbox"
                  class="form-check-input input-filter-status"
                  id="u-radu"
                  data-value="u_radu"
                  checked
                />
                <label class="form-check-label d-flex justify-content-between align-items-center" for="u-radu">
                  <span>U radu</span>
                  <span class="calendar-filter-count" data-stat-key="u_radu">0</span>
                </label>
              </div>
              <div class="form-check form-check-warning mb-1">
                <input
                  type="checkbox"
                  class="form-check-input input-filter-status"
                  id="djelimicno-zakljucen"
                  data-value="djelimicno_zakljucen"
                  checked
                />
                <label class="form-check-label d-flex justify-content-between align-items-center" for="djelimicno-zakljucen">
                  <span>Djelimično zaključen</span>
                  <span class="calendar-filter-count" data-stat-key="djelimicno_zakljucen">0</span>
                </label>
              </div>
              <div class="form-check form-check-danger">
                <input type="checkbox" class="form-check-input input-filter-status" id="zakljucen" data-value="zakljucen" checked />
                <label class="form-check-label d-flex justify-content-between align-items-center" for="zakljucen">
                  <span>Zaključen</span>
                  <span class="calendar-filter-count" data-stat-key="zakljucen">0</span>
                </label>
              </div>
            </div>
          </div>
        </div>
        <div class="mt-auto">
          <img
            src="{{asset('images/pages/calendar-illustration.png')}}"
            alt="Calendar illustration"
            class="img-fluid" style="width:50%!important"
          />
        </div>
      </div>
      <!-- /Sidebar -->

      <!-- Calendar -->
      <div class="col position-relative">
        <div class="card shadow-none border-0 mb-0 rounded-0">
          <div class="card-body pb-0">
            <div id="calendar"></div>
          </div>
        </div>
      </div>
      <!-- /Calendar -->
      <div class="body-content-overlay"></div>
    </div>
  </div>
  <!-- Calendar Add/Update/Delete event modal-->
  <div class="modal modal-slide-in event-sidebar fade" id="add-new-sidebar">
    <div class="modal-dialog sidebar-lg">
      <div class="modal-content p-0">
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zatvori">×</button>
        <div class="modal-header mb-1">
          <h5 class="modal-title">Dodaj dogadjaj</h5>
        </div>
        <div class="modal-body flex-grow-1 pb-sm-0 pb-3">
          <form class="event-form needs-validation" data-ajax="false" novalidate>
            <div class="mb-1">
              <label for="title" class="form-label">Naslov</label>
              <input type="text" class="form-control" id="title" name="title" placeholder="Naslov dogadjaja" required />
            </div>
            <div class="mb-1">
              <label for="select-label" class="form-label">Oznaka</label>
              <select class="select2 select-label form-select w-100" id="select-label" name="select-label">
                <option data-label="primary" value="Business" selected>Poslovno</option>
                <option data-label="danger" value="Personal">Privatno</option>
                <option data-label="warning" value="Family">Porodicno</option>
                <option data-label="success" value="Holiday">Odmor</option>
                <option data-label="info" value="ETC">Ostalo</option>
              </select>
            </div>
            <div class="mb-1 position-relative">
              <label for="start-date" class="form-label">Datum pocetka</label>
              <input type="text" class="form-control" id="start-date" name="start-date" placeholder="Datum pocetka" />
            </div>
            <div class="mb-1 position-relative">
              <label for="end-date" class="form-label">Datum zavrsetka</label>
              <input type="text" class="form-control" id="end-date" name="end-date" placeholder="Datum zavrsetka" />
            </div>
            <div class="mb-1">
              <div class="form-check form-switch">
                <input type="checkbox" class="form-check-input allDay-switch" id="customSwitch3" />
                <label class="form-check-label" for="customSwitch3">Cijeli dan</label>
              </div>
            </div>
            <div class="mb-1">
              <label for="event-url" class="form-label">URL dogadjaja</label>
              <input type="url" class="form-control" id="event-url" placeholder="https://www.google.com" />
            </div>
            <div class="mb-1 select2-primary">
              <label for="event-guests" class="form-label">Dodaj goste</label>
              <select class="select2 select-add-guests form-select w-100" id="event-guests" multiple>
                <option data-avatar="1-small.png" value="Jane Foster">Jane Foster</option>
                <option data-avatar="3-small.png" value="Donna Frank">Donna Frank</option>
                <option data-avatar="5-small.png" value="Gabrielle Robertson">Gabrielle Robertson</option>
                <option data-avatar="7-small.png" value="Lori Spears">Lori Spears</option>
                <option data-avatar="9-small.png" value="Sandy Vega">Sandy Vega</option>
                <option data-avatar="11-small.png" value="Cheryl May">Cheryl May</option>
              </select>
            </div>
            <div class="mb-1">
              <label for="event-location" class="form-label">Lokacija</label>
              <input type="text" class="form-control" id="event-location" placeholder="Unesite lokaciju" />
            </div>
            <div class="mb-1">
              <label class="form-label">Opis</label>
              <textarea name="event-description-editor" id="event-description-editor" class="form-control"></textarea>
            </div>
            <div class="mb-1 d-flex">
              <button type="submit" class="btn btn-primary add-event-btn me-1">Dodaj</button>
              <button type="button" class="btn btn-outline-secondary btn-cancel" data-bs-dismiss="modal">Otkazi</button>
              <button type="submit" class="btn btn-primary update-event-btn d-none me-1">Azuriraj</button>
              <button class="btn btn-outline-danger btn-delete-event d-none">Obrisi</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
  <!--/ Calendar Add/Update/Delete event modal-->
</section>
<!-- Full calendar end -->
@endsection

@section('vendor-script')
  <!-- Vendor js files -->
  <script src="{{ asset(mix('vendors/js/calendar/fullcalendar.min.js')) }}"></script>
  <script src="{{ asset(mix('vendors/js/extensions/moment.min.js')) }}"></script>
  <script src="{{ asset(mix('vendors/js/forms/select/select2.full.min.js')) }}"></script>
  <script src="{{ asset(mix('vendors/js/forms/validation/jquery.validate.min.js')) }}"></script>
  <script src="{{ asset(mix('vendors/js/pickers/flatpickr/flatpickr.min.js')) }}"></script>
@endsection

@section('page-script')
  <!-- Page js files -->
  <script src="{{ asset(mix('js/scripts/pages/app-calendar-events.js')) }}"></script>
  <script src="{{ asset(mix('js/scripts/pages/app-calendar.js')) }}"></script>
@endsection
