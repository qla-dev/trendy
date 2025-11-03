@extends('layouts/contentLayoutMaster')

@section('title', 'Pregled fakture')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('vendors/css/pickers/flatpickr/flatpickr.min.css')}}">
@endsection
@section('page-style')
<link rel="stylesheet" href="{{asset('css/base/plugins/forms/pickers/form-flat-pickr.css')}}">
<link rel="stylesheet" href="{{asset('css/base/pages/app-invoice.css')}}">
<style>
  .nav-tabs {
    margin-bottom: 0 !important;
  }
  .image-placeholder:hover {
    transform: scale(1.08);
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
  }
</style>
@endsection

@section('content')
<section class="invoice-preview-wrapper">
  <div class="row invoice-preview">
    <!-- Invoice -->
    <div class="col-xl-9 col-md-8 col-12">
      <div class="card invoice-preview-card">
        <div class="card-body invoice-padding pb-0">
          <!-- Header starts -->
          <div class="d-flex justify-content-between flex-md-row flex-column invoice-spacing mt-0">
            <div>
              <div class="logo-wrapper">
                <svg
                  viewBox="0 0 139 95"
                  version="1.1"
                  xmlns="http://www.w3.org/2000/svg"
                  xmlns:xlink="http://www.w3.org/1999/xlink"
                  height="24"
                >
                  <defs>
                    <linearGradient id="invoice-linearGradient-1" x1="100%" y1="10.5120544%" x2="50%" y2="89.4879456%">
                      <stop stop-color="#000000" offset="0%"></stop>
                      <stop stop-color="#FFFFFF" offset="100%"></stop>
                    </linearGradient>
                    <linearGradient
                      id="invoice-linearGradient-2"
                      x1="64.0437835%"
                      y1="46.3276743%"
                      x2="37.373316%"
                      y2="100%"
                    >
                      <stop stop-color="#EEEEEE" stop-opacity="0" offset="0%"></stop>
                      <stop stop-color="#FFFFFF" offset="100%"></stop>
                    </linearGradient>
                  </defs>
                  <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                    <g transform="translate(-400.000000, -178.000000)">
                      <g transform="translate(400.000000, 178.000000)">
                        <path
                          class="text-primary"
                          d="M-5.68434189e-14,2.84217094e-14 L39.1816085,2.84217094e-14 L69.3453773,32.2519224 L101.428699,2.84217094e-14 L138.784583,2.84217094e-14 L138.784199,29.8015838 C137.958931,37.3510206 135.784352,42.5567762 132.260463,45.4188507 C128.736573,48.2809251 112.33867,64.5239941 83.0667527,94.1480575 L56.2750821,94.1480575 L6.71554594,44.4188507 C2.46876683,39.9813776 0.345377275,35.1089553 0.345377275,29.8015838 C0.345377275,24.4942122 0.230251516,14.560351 -5.68434189e-14,2.84217094e-14 Z"
                          style="fill: currentColor"
                        ></path>
                        <path
                          d="M69.3453773,32.2519224 L101.428699,1.42108547e-14 L138.784583,1.42108547e-14 L138.784199,29.8015838 C137.958931,37.3510206 135.784352,42.5567762 132.260463,45.4188507 C128.736573,48.2809251 112.33867,64.5239941 83.0667527,94.1480575 L56.2750821,94.1480575 L32.8435758,70.5039241 L69.3453773,32.2519224 Z"
                          fill="url(#invoice-linearGradient-1)"
                          opacity="0.2"
                        ></path>
                        <polygon
                          fill="#000000"
                          opacity="0.049999997"
                          points="69.3922914 32.4202615 32.8435758 70.5039241 54.0490008 16.1851325"
                        ></polygon>
                        <polygon
                          fill="#000000"
                          opacity="0.099999994"
                          points="69.3922914 32.4202615 32.8435758 70.5039241 58.3683556 20.7402338"
                        ></polygon>
                        <polygon
                          fill="url(#invoice-linearGradient-2)"
                          opacity="0.099999994"
                          points="101.428699 0 83.0667527 94.1480575 130.378721 47.0740288"
                        ></polygon>
                      </g>
                    </g>
                  </g>
                </svg>
                <h3 class="text-primary invoice-logo">eNalog.app</h3>
              </div>
              <p class="card-text mb-25">Trendy d.o.o.</p>
              <p class="card-text mb-25">Bratstvo 11, 72290, NOVI TRAVNIK, BiH</p>
              <p class="card-text mb-0">+387 30 525 252</p>
            </div>
            <div class="mt-md-0 mt-2">
              <h4 class="invoice-title">
                RN
                <span class="invoice-number">#2401000000005</span>
              </h4>
              <div class="invoice-date-wrapper">
                <p class="invoice-date-title">Datum izdavanja:</p>
                <p class="invoice-date">25/08/2020</p>
              </div>
              <div class="invoice-date-wrapper">
                <p class="invoice-date-title">Datum dospijeća:</p>
                <p class="invoice-date">29/08/2020</p>
              </div>
            </div>
          </div>
          <!-- Header ends -->
        </div>

        <hr class="invoice-spacing" />

        <!-- Address and Contact starts -->
        <div class="card-body invoice-padding pt-0">
          <div class="row invoice-spacing">
            <div class="col-xl-8 p-0">
              <h6 class="mb-2">Naručitelj:</h6>
              <h6 class="mb-25">Metro d.o.o.</h6>
              <p class="card-text mb-25">Titova 45, 71000 Sarajevo, Bosna i Hercegovina</p>
              <p class="card-text mb-25">+387 33 123 456</p>
              <p class="card-text mb-0">info@metro.ba</p>
            </div>
            <div class="col-xl-4 p-0 mt-xl-0 mt-2">
              <h6 class="mb-2">Primatelj:</h6>
              <h6 class="mb-25">Bingo d.o.o.</h6>
              <p class="card-text mb-25">Zmaja od Bosne 12, 71000 Sarajevo, Bosna i Hercegovina</p>
              <p class="card-text mb-25">+387 33 789 012</p>
              <p class="card-text mb-0">info@bingo.ba</p>
            </div>
          </div>
        </div>
        <!-- Address and Contact ends -->

        <!-- Invoice Description starts -->
        <div class="nav-align-top">
          <ul class="nav nav-tabs" role="tablist">
            <li class="nav-item">
              <button type="button" class="nav-link active" role="tab" data-bs-toggle="tab" data-bs-target="#tab-sastavnica" aria-controls="tab-sastavnica" aria-selected="true">
                <i class="fa fa-list me-50"></i> Sastavnica
              </button>
            </li>
            <li class="nav-item">
              <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#tab-materijali" aria-controls="tab-materijali" aria-selected="false">
                <i class="fa fa-cube me-50"></i> Materijali
              </button>
            </li>
            <li class="nav-item">
              <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#tab-operacija" aria-controls="tab-operacija" aria-selected="false">
                <i class="fa fa-cog me-50"></i> Operacija
              </button>
            </li>
          </ul>
          <div class="tab-content">
            <!-- Sastavnica Tab -->
            <div class="tab-pane fade show active" id="tab-sastavnica" role="tabpanel">
              <div class="table-responsive">
                <table class="table" id="sastavnica-table">
                  <thead>
                    <tr>
                      <th class="py-1 text-center">Alternat...</th>
                      <th class="py-1 text-center">Pozicija</th>
                      <th class="py-1 text-center">Artikal</th>
                      <th class="py-1 text-center">Opis</th>
                      <th class="py-1 text-center">Slika</th>
                      <th class="py-1 text-center">Napo...</th>
                      <th class="py-1 text-center">Količina</th>
                      <th class="py-1 text-center">MJ</th>
                      <th class="py-1 text-center">Serija</th>
                      <th class="py-1 text-center">nor.os.</th>
                      <th class="py-1 text-center">Aktivno</th>
                      <th class="py-1 text-center">Završ...</th>
                      <th class="py-1 text-center">VA</th>
                      <th class="py-1 text-center">Prim.klas</th>
                      <th class="py-1 text-center">Sek.klas</th>
                    </tr>
                  </thead>
                  <tbody>
                  </tbody>
                </table>
              </div>
            </div>
            <!-- Materijali Tab -->
            <div class="tab-pane fade" id="tab-materijali" role="tabpanel">
              <div class="table-responsive">
                <table class="table" id="materijali-table">
                  <thead>
                    <tr>
                      <th class="py-1 text-center">Pozicija</th>
                      <th class="py-1 text-center">Materijal</th>
                      <th class="py-1 text-center">Naziv</th>
                      <th class="py-1 text-center">Količina</th>
                      <th class="py-1 text-center">Napomena</th>
                    </tr>
                  </thead>
                  <tbody>
                  </tbody>
                </table>
              </div>
            </div>
            <!-- Operacija Tab -->
            <div class="tab-pane fade" id="tab-operacija" role="tabpanel">
              <div class="table-responsive">
                <table class="table" id="operacija-table">
                  <thead>
                    <tr>
                      <th class="py-1 text-center">Alternativa</th>
                      <th class="py-1 text-center">Pozicija</th>
                      <th class="py-1 text-center">Operacija</th>
                      <th class="py-1 text-center">Naziv</th>
                      <th class="py-1 text-center">Napo...</th>
                      <th class="py-1 text-center">MJ</th>
                      <th class="py-1 text-center">MJ/vrij.</th>
                      <th class="py-1 text-center">nor.os.</th>
                      <th class="py-1 text-center">VA</th>
                      <th class="py-1 text-center">Prim.klas.</th>
                      <th class="py-1 text-center">Sek.klas.</th>
                    </tr>
                  </thead>
                  <tbody>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <div class="card-body invoice-padding pb-0">
          <div class="row invoice-sales-total-wrapper">
            <div class="col-md-6 order-md-1 order-2 mt-md-0 mt-3">
              <p class="card-text mb-0">
                <span class="fw-bold">Prodavač:</span> <span class="ms-75">Alfie Solomons</span>
              </p>
            </div>
            <div class="col-md-6 d-flex justify-content-end order-md-2 order-1">
              <div class="invoice-total-wrapper">
                <div class="invoice-total-item">
                  <p class="invoice-total-title">Međuzbir:</p>
                  <p class="invoice-total-amount">1800 KM</p>
                </div>
                <div class="invoice-total-item">
                  <p class="invoice-total-title">Popust:</p>
                  <p class="invoice-total-amount">28 KM</p>
                </div>
                <div class="invoice-total-item">
                  <p class="invoice-total-title">Porez:</p>
                  <p class="invoice-total-amount">21%</p>
                </div>
                <hr class="my-50" />
                <div class="invoice-total-item">
                  <p class="invoice-total-title">Ukupno:</p>
                  <p class="invoice-total-amount">1690 KM</p>
                </div>
              </div>
            </div>
          </div>
        </div>
        <!-- Invoice Description ends -->

        <hr class="invoice-spacing" />

        <!-- Invoice Note starts -->
        <div class="card-body invoice-padding pt-0">
          <div class="row">
            <div class="col-12">
              <span class="fw-bold">Napomena:</span>
              <span
                >Bilo nam je zadovoljstvo raditi sa vama i vašim timom. Nadamo se da ćete nas imati na umu za buduće projekte. Hvala vam!</span
              >
            </div>
          </div>
        </div>
        <!-- Invoice Note ends -->
      </div>
    </div>
    <!-- /Invoice -->

    <!-- Invoice Actions -->
    <div class="col-xl-3 col-md-4 col-12 invoice-actions mt-md-0 mt-2">
      <div class="card">
        <div class="card-body">
          <button class="btn btn-primary w-100 mb-75">
            <i class="fa fa-qrcode me-50"></i> Skeniraj radni nalog
          </button>
          <button class="btn btn-success w-100 mb-75">
            <i class="fa fa-qrcode me-50"></i> Skeniraj sirovinu
          </button>
          <button class="btn btn-outline-secondary w-100 mb-75" data-bs-toggle="modal" data-bs-target="#send-invoice-sidebar">
            <i class="fa fa-paper-plane me-50"></i> Pošalji
          </button>
          <a class="btn btn-outline-secondary w-100 mb-75" href="{{url('app/invoice/print')}}" target="_blank">
            <i class="fa fa-print me-50"></i> Isprintaj
          </a>
        </div>
      </div>
    </div>
    <!-- /Invoice Actions -->
  </div>
</section>

<!-- Send Invoice Sidebar -->
<div class="modal modal-slide-in fade" id="send-invoice-sidebar" aria-hidden="true">
  <div class="modal-dialog sidebar-lg">
    <div class="modal-content p-0">
      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zatvori">×</button>
      <div class="modal-header mb-1">
        <h5 class="modal-title">
          <span class="align-middle">Pošalji fakturu</span>
        </h5>
      </div>
      <div class="modal-body flex-grow-1">
        <form>
          <div class="mb-1">
            <label for="invoice-from" class="form-label">Od</label>
            <input
              type="text"
              class="form-control"
              id="invoice-from"
              value="shelbyComapny@email.com"
              placeholder="company@email.com"
            />
          </div>
          <div class="mb-1">
            <label for="invoice-to" class="form-label">Za</label>
            <input
              type="text"
              class="form-control"
              id="invoice-to"
              value="qConsolidated@email.com"
              placeholder="company@email.com"
            />
          </div>
          <div class="mb-1">
            <label for="invoice-subject" class="form-label">Predmet</label>
            <input
              type="text"
              class="form-control"
              id="invoice-subject"
              value="Faktura za kupljene Admin Template"
              placeholder="Faktura u vezi robe"
            />
          </div>
          <div class="mb-1">
            <label for="invoice-message" class="form-label">Poruka</label>
            <textarea
              class="form-control"
              name="invoice-message"
              id="invoice-message"
              cols="3"
              rows="11"
              placeholder="Poruka..."
            >
Poštovani,

Hvala vam na poslovanju, uvijek je zadovoljstvo raditi sa vama!

Generirali smo novu fakturu u iznosu od 95.59 KM

Cijenili bismo plaćanje ove fakture do 05/11/2019</textarea
            >
          </div>
          <div class="mb-1">
            <span class="badge badge-light-primary">
              <i data-feather="link" class="me-25"></i>
              <span class="align-middle">Faktura priložena</span>
            </span>
          </div>
          <div class="mb-1 d-flex flex-wrap mt-2">
            <button type="button" class="btn btn-primary me-1" data-bs-dismiss="modal">Pošalji</button>
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Otkaži</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<!-- /Send Invoice Sidebar -->

<!-- Add Payment Sidebar -->
<div class="modal modal-slide-in fade" id="add-payment-sidebar" aria-hidden="true">
  <div class="modal-dialog sidebar-lg">
    <div class="modal-content p-0">
      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zatvori">×</button>
      <div class="modal-header mb-1">
        <h5 class="modal-title">
          <span class="align-middle">Dodaj plaćanje</span>
        </h5>
      </div>
      <div class="modal-body flex-grow-1">
        <form>
          <div class="mb-1">
            <input id="balance" class="form-control" type="text" value="Stanje fakture: 5000.00 KM" disabled />
          </div>
          <div class="mb-1">
            <label class="form-label" for="amount">Iznos plaćanja</label>
            <input id="amount" class="form-control" type="number" placeholder="1000 KM" />
          </div>
          <div class="mb-1">
            <label class="form-label" for="payment-date">Datum plaćanja</label>
            <input id="payment-date" class="form-control date-picker" type="text" />
          </div>
          <div class="mb-1">
            <label class="form-label" for="payment-method">Način plaćanja</label>
            <select class="form-select" id="payment-method">
              <option value="" selected disabled>Odaberi način plaćanja</option>
              <option value="Cash">Gotovina</option>
              <option value="Bank Transfer">Bankovni transfer</option>
              <option value="Debit">Debitna kartica</option>
              <option value="Credit">Kreditna kartica</option>
              <option value="Paypal">Paypal</option>
            </select>
          </div>
          <div class="mb-1">
            <label class="form-label" for="payment-note">Interna napomena o plaćanju</label>
            <textarea class="form-control" id="payment-note" rows="5" placeholder="Interna napomena o plaćanju"></textarea>
          </div>
          <div class="d-flex flex-wrap mb-0">
            <button type="button" class="btn btn-primary me-1" data-bs-dismiss="modal">Pošalji</button>
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Otkaži</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<!-- /Add Payment Sidebar -->
@endsection

@section('vendor-script')
<script src="{{asset('vendors/js/forms/repeater/jquery.repeater.min.js')}}"></script>
<script src="{{asset('vendors/js/pickers/flatpickr/flatpickr.min.js')}}"></script>
@endsection

@section('page-script')
<script src="{{asset('js/scripts/pages/app-invoice.js')}}"></script>
<script>
  // Dummy data arrays
  const sastavnicaData = [
    {
      alternativa: '0',
      pozicija: '10',
      artikal: 'ALPL2017',
      opis: 'Alu pločevina 2017',
      slika: '',
      napomena: 'Osnovni materijal',
      kolicina: '2,0500',
      mj: 'KG',
      serija: '1,00',
      normativnaOsnova: '1 jedinica',
      aktivno: 'Da',
      zavrseno: 'Ne',
      va: 'VA1',
      primKlas: 'PK1',
      sekKlas: 'SK1'
    },
    {
      alternativa: '0',
      pozicija: '20',
      artikal: 'STL2024',
      opis: 'Čelična ploča 2024',
      slika: '',
      napomena: 'Sekundarni materijal',
      kolicina: '1,5000',
      mj: 'KG',
      serija: '2,00',
      normativnaOsnova: '1 jedinica',
      aktivno: 'Da',
      zavrseno: 'Ne',
      va: 'VA2',
      primKlas: 'PK2',
      sekKlas: 'SK2'
    },
    {
      alternativa: '1',
      pozicija: '30',
      artikal: 'PLST2023',
      opis: 'Plastična komponenta 2023',
      slika: '',
      napomena: 'Alternativni materijal',
      kolicina: '3,2500',
      mj: 'KG',
      serija: '1,50',
      normativnaOsnova: '1 jedinica',
      aktivno: 'Ne',
      zavrseno: 'Da',
      va: 'VA3',
      primKlas: 'PK3',
      sekKlas: 'SK3'
    }
  ];

  const materijaliData = [
    {
      pozicija: '10',
      materijal: 'ALPL2017',
      naziv: 'Alu pločevina 2017',
      kolicina: '2,0500',
      napomena: ''
    },
    {
      pozicija: '20',
      materijal: 'STL2024',
      naziv: 'Čelična ploča 2024',
      kolicina: '1,5000',
      napomena: 'Visokokvalitetni čelik'
    },
    {
      pozicija: '30',
      materijal: 'PLST2023',
      naziv: 'Plastična komponenta 2023',
      kolicina: '3,2500',
      napomena: ''
    },
    {
      pozicija: '40',
      materijal: 'ELK2025',
      naziv: 'Električna komponenta 2025',
      kolicina: '0,5000',
      napomena: 'Za montažu'
    },
    {
      pozicija: '50',
      materijal: 'GLASS2024',
      naziv: 'Staklena komponenta 2024',
      kolicina: '1,0000',
      napomena: 'Ostalo'
    }
  ];

  const operacijaData = [
    {
      alternativa: '0',
      pozicija: '10',
      operacija: 'OP001',
      naziv: 'Rezanje',
      napomena: 'Rezanje po dimenzijama',
      mj: 'KOM',
      mjVrij: '0,5',
      normativnaOsnova: 'Normativ 1',
      va: 'VA1',
      primKlas: 'PK1',
      sekKlas: 'SK1'
    },
    {
      alternativa: '0',
      pozicija: '20',
      operacija: 'OP002',
      naziv: 'Svaranje',
      napomena: 'Svaranje šavova',
      mj: 'KOM',
      mjVrij: '1,2',
      normativnaOsnova: 'Normativ 2',
      va: 'VA2',
      primKlas: 'PK2',
      sekKlas: 'SK2'
    },
    {
      alternativa: '0',
      pozicija: '30',
      operacija: 'OP003',
      naziv: 'Poliranje',
      napomena: 'Finalno poliranje',
      mj: 'KOM',
      mjVrij: '0,8',
      normativnaOsnova: 'Normativ 3',
      va: 'VA3',
      primKlas: 'PK3',
      sekKlas: 'SK3'
    },
    {
      alternativa: '1',
      pozicija: '40',
      operacija: 'OP004',
      naziv: 'Montaža',
      napomena: 'Montaža komponenti',
      mj: 'KOM',
      mjVrij: '2,0',
      normativnaOsnova: 'Normativ 4',
      va: 'VA4',
      primKlas: 'PK4',
      sekKlas: 'SK4'
    }
  ];

  // Function to populate tables
  function populateTables() {
    // Simple grayish gradient for all image placeholders
    const gradient = 'linear-gradient(135deg, #e0e0e0 0%, #b0b0b0 100%)';
    
    // Populate Sastavnica table
    const sastavnicaTbody = document.querySelector('#sastavnica-table tbody');
    sastavnicaData.forEach((item) => {
      const row = document.createElement('tr');
      row.innerHTML = `
        <td class="py-1">${item.alternativa}</td>
        <td class="py-1">${item.pozicija}</td>
        <td class="py-1">${item.artikal}</td>
        <td class="py-1">${item.opis}</td>
        <td class="py-1">
          <div class="image-placeholder" style="width: 60px; height: 60px; background: ${gradient}; border-radius: 6px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <i class="fa fa-image" style="font-size: 24px; color: #666; opacity: 0.9;"></i>
          </div>
        </td>
        <td class="py-1">${item.napomena}</td>
        <td class="py-1">${item.kolicina}</td>
        <td class="py-1">${item.mj}</td>
        <td class="py-1">${item.serija}</td>
        <td class="py-1">${item.normativnaOsnova}</td>
        <td class="py-1">${item.aktivno}</td>
        <td class="py-1">${item.zavrseno}</td>
        <td class="py-1">${item.va}</td>
        <td class="py-1">${item.primKlas}</td>
        <td class="py-1">${item.sekKlas}</td>
      `;
      sastavnicaTbody.appendChild(row);
    });

    // Populate Materijali table
    const materijaliTbody = document.querySelector('#materijali-table tbody');
    materijaliData.forEach(item => {
      const row = document.createElement('tr');
      row.innerHTML = `
        <td class="py-1">${item.pozicija}</td>
        <td class="py-1">${item.materijal}</td>
        <td class="py-1">${item.naziv}</td>
        <td class="py-1">${item.kolicina}</td>
        <td class="py-1">${item.napomena || ''}</td>
      `;
      materijaliTbody.appendChild(row);
    });

    // Populate Operacija table
    const operacijaTbody = document.querySelector('#operacija-table tbody');
    operacijaData.forEach(item => {
      const row = document.createElement('tr');
      row.innerHTML = `
        <td class="py-1">${item.alternativa}</td>
        <td class="py-1">${item.pozicija}</td>
        <td class="py-1">${item.operacija}</td>
        <td class="py-1">${item.naziv}</td>
        <td class="py-1">${item.napomena}</td>
        <td class="py-1">${item.mj}</td>
        <td class="py-1">${item.mjVrij}</td>
        <td class="py-1">${item.normativnaOsnova}</td>
        <td class="py-1">${item.va}</td>
        <td class="py-1">${item.primKlas}</td>
        <td class="py-1">${item.sekKlas}</td>
      `;
      operacijaTbody.appendChild(row);
    });
  }

  // Initialize on page load
  document.addEventListener('DOMContentLoaded', function() {
    populateTables();
  });
</script>
@endsection
