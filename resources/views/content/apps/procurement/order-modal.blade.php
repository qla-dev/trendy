<style>
#order-material-modal .modal-dialog{max-width:calc(100vw - 48px)}
#order-material-modal .modal-body{min-height:min(420px,60vh)}
#order-material-modal table th,#order-material-modal table td{padding:10px 8px;vertical-align:middle}
#order-material-modal td:first-child{min-width:200px!important}
#order-material-modal [data-need]{min-width:160px!important}
#order-material-modal [data-dimensions]{min-width:150px!important}
#order-material-modal [data-pieces]{width:70px!important}
#order-material-modal [data-quantity]{width:90px!important}
#order-material-modal [data-price]{width:90px!important}
#order-material-modal [data-vat]{width:65px!important}
#order-material-modal .order-actions{position:sticky;right:0;background:var(--bs-body-bg,#fff);white-space:nowrap;z-index:2;min-width:100px;text-align:right}
#order-material-modal .order-actions>.d-flex{justify-content:flex-end}
#order-material-modal .order-quantity{display:flex;align-items:center;gap:6px;white-space:nowrap;min-width:125px}
#order-material-modal [data-stock]{white-space:nowrap;min-width:110px}
.rn-picker{position:fixed;z-index:1070;background:#fff;border:1px solid #d8d6de;border-radius:6px;box-shadow:0 5px 18px #0002;padding:10px;width:420px;max-width:calc(100vw - 32px)}
.rn-picker-list{max-height:240px;overflow:auto;margin:8px 0}.rn-picker-list label{display:flex;align-items:flex-start;gap:9px;padding:8px;cursor:pointer;white-space:nowrap}.rn-picker-list label:hover{background:#4a5c7515}.rn-picker-list input{flex-shrink:0;margin-top:3px}.rn-picker-list small{display:block;white-space:nowrap}.rn-picker-trigger{width:180px;text-align:left;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.dark-layout .rn-picker,.dark-layout #order-material-modal .order-actions{background:#283046;color:#d0d2d6}.dark-layout .rn-picker{border-color:#404656}
@media(max-width:575px){#order-material-modal .modal-dialog{max-width:calc(100vw - 16px);margin:8px}.rn-picker-trigger{width:200px}}
.dark-layout #order-material-modal .select2-selection__choice{background:#4a5c75!important;color:#fff!important}.dark-layout #order-material-modal .select2-selection__choice__remove{color:#fff!important}</style>
<div class="modal fade" id="order-material-modal" tabindex="-1" aria-labelledby="order-material-title" aria-hidden="true" data-options-url="{{ route('app-procurement-ordering-options') }}">
 <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><form method="post" action="{{ route('app-procurement-store') }}" class="modal-content" id="material-order-form">@csrf
 <input type="hidden" name="submission" value="{{ old('submission',(string) \Illuminate\Support\Str::uuid()) }}">
 <div class="modal-header"><h4 class="modal-title" id="order-material-title">Naruči materijal</h4><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zatvori"></button></div>
 <div class="modal-body"><div class="row g-1 mb-2">
 <div class="col-md-5"><label class="form-label" for="order-supplier">Dobavljač</label><select id="order-supplier" name="supplier" class="form-control" required><option value="">Izaberite dobavljača</option>@foreach(app(\App\Services\Procurement\ProcurementSource::class)->suppliers() as $supplier)<option @selected(old('supplier')===$supplier)>{{ $supplier }}</option>@endforeach</select></div>
 <div class="col-md-3"><label class="form-label" for="order-date">Datum narudžbe</label><input id="order-date" name="document_date" type="text" data-procurement-date placeholder="dd.mm.gggg" class="form-control" required value="{{ old('document_date',now()->format('Y-m-d')) }}"></div>
 <div class="col-md-3"><label class="form-label" for="order-deadline">Rok isporuke</label><input id="order-deadline" name="deadline" type="text" data-procurement-date placeholder="dd.mm.gggg" class="form-control" required value="{{ old('deadline') }}"></div></div>
 <p class="small">Dimenzije su u mm. Potreba prikazuje zbir dužina po presjeku za preostale potrebe odabranih RN-ova. Količina za narudžbu se unosi u prikazanoj ERP jedinici; komadi i dimenzije čuvaju se zasebno.</p>
 <p id="order-material-error" class="text-danger" role="alert"></p>
 <div class="table-responsive"><table class="table"><thead><tr><th>Materijal</th><th>Radni nalozi</th><th>Tačna potreba RN</th><th>Dimenzije za narudžbu</th><th>Komada</th><th>Skladište sirovina</th><th>Količina / JM</th><th>Cijena / JM</th><th>PDV %</th><th class="order-actions">Akcije</th></tr></thead><tbody id="material-order-rows"></tbody></table></div>
 <label class="form-label mt-1" for="order-notes">Napomena</label><textarea id="order-notes" name="notes" class="form-control" maxlength="4000">{{ old('notes') }}</textarea>
 </div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Zatvori</button><button class="btn btn-primary" id="submit-material-order">Sačuvaj narudžbenicu</button></div>
 </form></div>
</div>
<script>window.procurementOldItems = @json(array_values(old('items', [])));</script>
