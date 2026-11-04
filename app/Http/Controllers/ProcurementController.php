<?php

namespace App\Http\Controllers;

use App\Services\Procurement\ProcurementCalculator;
use App\Services\Procurement\ProcurementService;
use App\Services\Procurement\ProcurementSource;
use App\Services\Procurement\ProcurementAccess;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ProcurementController extends Controller
{
    public function __construct(private ProcurementService $service, private ProcurementSource $source, private ProcurementCalculator $calculator)
    {
        $this->middleware(function ($request, $next) {
            $user = $request->user();
            $action = $request->route()?->getActionMethod();
            $permission = match ($action) {
                'receive','receiveForm','scan','scanReceive' => 'receive',
                'store','create','edit','update','confirmInquiry','selection','upload','complaint' => 'edit',
                'destroy','cancel','sync','resolveComplaint','settings','bindSupplier','draft' => 'manage',
                default => 'view',
            };
            abort_unless(app(ProcurementAccess::class)->can($user, $permission), 403);
            return $next($request);
        });
    }

    public function materials(Request $request)
    {
        $filters = $this->materialFilters($request);
        $selected = $request->session()->get('procurement_selected', []);
        $filters['selected_ids'] = $selected;
        $progress = $this->service->progress();
        $rows = $this->source->requirements($filters, $progress);
        $filteredIds = $rows->pluck('id')->all();
        $filteredTotal = $rows->total();
        $filteredSelected = $selected ? $this->source->requirementCount(array_merge($filters,['selected_only'=>true,'selected_ids'=>$selected]), $progress) : 0;
        $filterToken = (string) Str::uuid();
        $snapshots = $request->session()->get('procurement_filter_snapshots', []);
        $snapshots[$filterToken] = ['filters'=>$filters,'ids'=>count($filteredIds)===$filteredTotal ? $filteredIds : null];
        $request->session()->put('procurement_filter_snapshots', array_slice($snapshots, -10, null, true));
        $stock = $this->source->stock($rows->pluck('material')->all());
        return view('content.apps.procurement.materials', compact('rows', 'selected', 'stock', 'filteredIds', 'filterToken', 'filteredTotal', 'filteredSelected'));
    }

    private function materialFilters(Request $request): array
    {
        return $request->validate(['q'=>'nullable|string|max:255', 'sort'=>'nullable|in:work_order,material,order,position,required_date,required', 'direction'=>'nullable|in:asc,desc', 'unit'=>'nullable|string|max:16',
            'status'=>'nullable|in:not_ordered,part_ordered,ordered,partial,received,late,soon,zero','supplier'=>'nullable|string|max:255','work_order'=>'nullable|string|max:255',
            'from'=>'nullable|date_format:Y-m-d','to'=>'nullable|date_format:Y-m-d','created_from'=>'nullable|date_format:Y-m-d','created_to'=>'nullable|date_format:Y-m-d','per_page'=>'nullable|integer|in:10,25,50,100','selected_only'=>'nullable|boolean','page'=>'nullable|integer|min:1']);
    }

    public function orderingOptions(Request $request)
    {
        abort_unless(app(ProcurementAccess::class)->can($request->user(), 'edit'), 403);
        $data = $request->validate(['q'=>'nullable|string|max:255','catalog_only'=>'nullable|boolean','material'=>'nullable|string|max:255','page'=>'nullable|integer|min:1','selected'=>'nullable|array','selected.*'=>'string|max:1000','source_ids'=>'nullable|array','source_ids.*'=>'string|max:1000']);
        $selected = $data['selected'] ?? $request->session()->get('procurement_selected', []);
        $requirements = $catalog = $stock = [];
        $nextPage = null;
        if ($request->boolean('catalog_only')) {
            $catalog = $this->source->catalog((string) $request->query('q',''));
            $stock = $this->source->stock(array_column($catalog,'material'));
        } elseif ($request->filled('material')) {
            $rows = $this->source->requirements(['material'=>$data['material'],'q'=>$data['q'] ?? '', 'page'=>$data['page'] ?? 1,'per_page'=>50], $this->service->progress());
            $requirements = $rows->items();
            $nextPage = $rows->hasMorePages() ? $rows->currentPage()+1 : null;
        } else {
            $ids = array_values(array_unique([...$selected,...($data['source_ids'] ?? [])]));
            $catalogIds = array_values(array_filter($ids, fn ($id) => str_starts_with($id,'catalog_')));
            if ($catalogIds) $catalog = array_values($this->business(fn () => $this->source->selected($catalogIds)));
            $ids = array_values(array_diff($ids,$catalogIds));
            if ($ids) {
                $requirements = $this->source->requirements(['selected_only'=>true,'selected_ids'=>$ids,'_all'=>true], $this->service->progress())->items();
            }
            if ($requirements || $catalog) $stock = $this->source->stock(array_merge(array_column($requirements,'material'),array_column($catalog,'material')));
        }
        return response()->json(compact('requirements','catalog','stock','selected','nextPage'));
    }

    public function create(Request $request)
    {
        if ($request->isMethod('post')) {
            if ($request->has('selected_json')) {
                $decoded = json_decode((string) $request->input('selected_json'), true);
                $request->merge(['selected'=>is_array($decoded) ? $decoded : null]);
            }
            if (!$request->boolean('from_session')) {
                $data = $request->validate(['selected' => 'required|array|min:1', 'document_type'=>'nullable|in:order,inquiry', 'selected.*' => 'required|string|max:1000|distinct']);
                $request->session()->put('procurement_selected', $data['selected']);
            }
            $request->session()->put('procurement_document_type', $request->input('document_type','order'));
            $request->session()->forget('_old_input');
            $request->session()->put('procurement_combine', $request->boolean('combine'));
            $request->session()->put('procurement_overorder_prepare', $request->boolean('overorder_prepare') && app(ProcurementAccess::class)->can($request->user(),'manage'));
            return redirect()->route('app-procurement-create-form', $request->input('document_type') === 'inquiry' ? ['document_type'=>'inquiry'] : []);
        }
        $documentType = $request->validate(['document_type'=>'nullable|in:order,inquiry'])['document_type'] ?? $request->session()->get('procurement_document_type','order');
        $inquiry = $documentType === 'inquiry';
        if ($inquiry) return redirect()->route('app-procurement-materials',['open_inquiry'=>1]);
        $progress = $this->service->progress();
        $selected = $request->session()->get('procurement_selected', []);
        if ($request->old('items')) {
            $selected = [];
            foreach ($request->old('items') as $line) foreach ($line['allocations'] ?? [['source_id'=>$line['source_id'] ?? '']] as $allocation) $selected[] = $allocation['source_id'];
        }
        if ($selected === []) return redirect()->route('app-procurement-materials');
        try {
            if ($inquiry) {
                $items = collect($this->source->requirements(['selected_only'=>true,'selected_ids'=>$selected,'_all'=>true], $progress)->items())->keyBy('id')->all();
                if (array_diff($selected,array_keys($items))) throw new InvalidArgumentException('Dio odabranih RN-ova više nije dostupan. Ponovite odabir.');
            } else $items = $this->source->selected($selected);
        } catch (InvalidArgumentException $exception) {
            return redirect()->route('app-procurement-materials')->withErrors(['selected' => $exception->getMessage()]);
        }
        $unavailable = [];
        foreach ($items as $id => $item) {
            if (!$inquiry && (bccomp($item['required'],'0',6)<=0 || (bccomp($item['required'], $progress[$id]['ordered'] ?? '0', 6) <= 0 && !$request->session()->get('procurement_overorder_prepare')))) {
                $unavailable[$id] = $item['material'] . ' (' . $item['work_order'] . ')';
            }
        }
        if ($unavailable) {
            $request->session()->put('procurement_selected', array_values(array_diff($selected, array_keys($unavailable))));
            return redirect()->route('app-procurement-materials')->withErrors(['selected' => 'Nema preostale količine za nabavku: ' . implode(', ', $unavailable) . '. Te stavke su uklonjene iz odabira.']);
        }
        $groups = [];
        foreach ($items as $id=>$item) {
            $key = !$inquiry && $request->session()->get('procurement_combine') ? $this->service->compatibility($item) : $id;
            $quantity = bcsub($item['required'], $progress[$id]['ordered'] ?? '0', 6);
            if (bccomp($quantity,'0',6)<0) $quantity = '0.000000';
            $groups[$key] ??= ['source'=>$item,'quantity'=>'0','price'=>$item['price'] ?? '','vatRate'=>$item['vat_rate'],'discountRate'=>'0','deadline'=>$item['required_date'] ?? '', 'allocations'=>[]];
            if ($inquiry) {
                $groups[$key]['deadline'] = $item['inquiry_deadline'] ?? '';
                $groups[$key]['price'] = '';
                $groups[$key]['price_pending'] = true;
                $groups[$key]['ordered_dimensions'] = $item['dimensions'];
                $groups[$key]['pieces'] = $item['pieces'] ?? '';
            }
            $groups[$key]['quantity'] = bcadd($groups[$key]['quantity'], $quantity, 6);
            $groups[$key]['allocations'][] = ['source'=>$item,'quantity'=>$quantity];
            if (!$inquiry && !empty($item['required_date']) && (!$groups[$key]['deadline'] || $item['required_date'] < $groups[$key]['deadline'])) $groups[$key]['deadline'] = $item['required_date'];
        }
        return $this->form(array_values($groups), null, $documentType);
    }

    private function form(array $lines, ?array $purchase = null, ?string $documentType = null)
    {
        return view('content.apps.procurement.create', ['documentType'=>$documentType ?? ($purchase['document_type'] ?? 'order'),'lines'=>$lines,'purchase'=>$purchase,'suppliers'=>$this->source->suppliers(),'submission'=>(string) Str::uuid()]);
    }

    public function selection(Request $request)
    {
        $data = $request->validate(['add'=>'nullable|array','add.*'=>'string|max:1000','remove'=>'nullable|array','remove.*'=>'string|max:1000','clear'=>'nullable|boolean','filtered'=>'nullable|in:add,remove','filter_token'=>'required_with:filtered|uuid']);
        $selected = $request->boolean('clear') ? [] : $request->session()->get('procurement_selected', []);
        $selected = array_values(array_diff(array_unique([...$selected, ...($data['add'] ?? [])]), $data['remove'] ?? []));
        if (!empty($data['filtered'])) {
            // Resolve only matching identities, and only when Select all is first used.
            // Repeated selection changes reuse that list without loading RN details.
            $snapshots = $request->session()->get('procurement_filter_snapshots', []);
            if (!array_key_exists($data['filter_token'], $snapshots)) throw ValidationException::withMessages(['filtered'=>'Lista je istekla. Osvježite stranicu i ponovite odabir.']);
            $snapshot = $snapshots[$data['filter_token']];
            if (array_key_exists('filters',$snapshot)) {
                $matches = $snapshot['ids'] ?? $this->source->requirementIds($snapshot['filters'], $this->service->progress());
                $snapshots[$data['filter_token']]['ids'] = $matches;
                $request->session()->put('procurement_filter_snapshots', $snapshots);
            } else $matches = $snapshot; // Already-open pages from the previous release.
            $selected = $data['filtered'] === 'add' ? array_values(array_unique([...$selected,...$matches])) : array_values(array_diff($selected,$matches));
        }
        $request->session()->put('procurement_selected', $selected);
        return response()->json(['selected'=>$selected,'count'=>count($selected)] + (isset($matches) ? ['filtered_ids'=>$matches] : []));
    }

    public function store(Request $request)
    {
        $data = $this->validatedPurchase($request);
        $purchase = $this->business(fn () => $this->service->create($data, (int) $request->user()->id));
        $request->session()->forget('procurement_selected');
        if ($purchase['document_type'] === 'inquiry' && $request->boolean('open_pdf')) return redirect()->route('app-procurement-print', $purchase['id']);
        if ($purchase['document_type'] === 'inquiry') return redirect()->route('app-procurement-show', $purchase['id'])->with('success','Upit je sačuvan. Pregledajte ga prije otvaranja PDF-a.');
        return redirect()->route('app-procurement-show', $purchase['id'])->with('success', 'Nabavka je sačuvana.');
    }

    private function validatedPurchase(Request $request): array
    {
        $decimal = ['required', 'string', 'max:30', 'regex:/^\d{1,12}(?:[.,]\d{1,6})?$/D'];
        $price = $request->input('document_type') === 'inquiry' ? ['nullable', ...array_slice($decimal, 1)] : $decimal;
        $data = $request->validate([
            'document_type'=>'nullable|in:order,inquiry',
            'submission' => 'required|uuid', 'supplier' => 'required|string|max:255', 'deadline' => 'required|date_format:Y-m-d',
            'items' => 'required|array|min:1', 'items.*.source_id' => 'nullable|string|max:1000',
            'items.*.quantity' => $decimal, 'items.*.price' => $price, 'items.*.vat_rate' => $decimal,
            'items.*.discount_rate'=>'nullable|string|max:30','items.*.weight'=>'nullable|string|max:30','items.*.deadline'=>'nullable|date_format:Y-m-d',
            'items.*.ordered_dimensions'=>'nullable|string|max:1000','items.*.pieces'=>'nullable|string|max:30',
            'items.*.allocations'=>'nullable|array|min:1','items.*.allocations.*.source_id'=>'required|string|max:1000', 'items.*.allocations.*.quantity'=>$decimal,
            'document_date'=>'nullable|date_format:Y-m-d','valid_until'=>'nullable|date_format:Y-m-d','exchange_rate'=>'nullable|string|max:30',
            'carrier'=>'nullable|string|max:255','department'=>'nullable|string|max:255','procurement_type'=>'nullable|string|max:255','responsible_person'=>'nullable|string|max:255','reference_person'=>'nullable|string|max:255','notes'=>'nullable|string|max:4000',
            'override_reason'=>'nullable|string|max:1000','overorder'=>'nullable|boolean',
        ]);
        $data['_allow_overorder'] = $request->boolean('overorder') && app(ProcurementAccess::class)->can($request->user(), 'manage');
        if (!empty($data['exchange_rate'])) $data['exchange_rate'] = $this->business(fn () => $this->calculator->decimal($data['exchange_rate'], true));
        return $data;
    }

    public function edit(string $id)
    {
        $purchase = $this->service->find($id);
        if ($purchase['receipts'] || $purchase['pantheon_key'] || $purchase['cancelled_at']) return redirect()->route('app-procurement-show',$id)->with('warning','Izmjena nije dozvoljena nakon prijema, otkazivanja ili ERP potvrde.');
        return $this->form($purchase['items'], $purchase);
    }

    public function update(Request $request, string $id)
    {
        $request->merge(['document_type'=>$this->service->find($id)['document_type']]);
        $data = $this->validatedPurchase($request);
        $this->business(fn () => $this->service->edit($id, $data, (int) $request->user()->id));
        return redirect()->route('app-procurement-show', $id)->with('success','Nabavka je izmijenjena.');
    }

    public function confirmInquiry(Request $request, string $id)
    {
        $this->business(fn () => $this->service->confirmInquiry($id, (int) $request->user()->id));
        return redirect()->route('app-procurement-show', $id)->with('success', 'Upit je potvrđen kao narudžba.');
    }

    public function index(Request $request)
    {
        $request->validate(['q'=>'nullable|string|max:255', 'status'=>'nullable|in:open,partial,delivered,cancelled',
            'sync'=>'nullable|in:blocked,pending,synced,error', 'late'=>'nullable|boolean', 'sort'=>'nullable|in:number,supplier,deadline,created_at',
            'direction'=>'nullable|in:asc,desc', 'page'=>'nullable|integer|min:1','supplier'=>'nullable|string|max:255','from'=>'nullable|date_format:Y-m-d','to'=>'nullable|date_format:Y-m-d','soon'=>'nullable|boolean','notices'=>'nullable|boolean','active'=>'nullable|boolean']);
        $rows = collect($this->service->all())->map(function ($row) {
            $workOrders = [];
            foreach ($row['items'] as $line) foreach ($line['allocations'] ?? [['source'=>$line['source']]] as $allocation) if (empty($allocation['source']['stock_order'])) $workOrders[$allocation['source']['work_order_key']] = $allocation['source']['work_order'];
            return $row + ['work_orders'=>$workOrders,'delivery_status' => $this->calculator->deliveryStatus($row), 'due' => $this->calculator->deadline($row)];
        });
        $metrics = ['active'=>0,'late'=>0,'partial'=>0,'soon'=>0,'complaints'=>0,'notices'=>0,'late_items'=>0,'outstanding'=>[],'value'=>[],'awaiting_value'=>[]];
        foreach ($rows as $row) {
            if ($row['document_type'] === 'inquiry') continue;
            $active = !in_array($row['delivery_status'],['delivered','cancelled','inquiry'],true);
            if ($active) $metrics['active']++;
            if ($row['due']['days']>0) $metrics['late']++;
            if ($row['delivery_status']==='partial') $metrics['partial']++;
            if ($active && $row['deadline']>=now()->format('Y-m-d') && $row['deadline']<=now()->addDays(7)->format('Y-m-d')) $metrics['soon']++;
            foreach ($row['complaints'] ?? [] as $complaint) if (!in_array($complaint['status'],['resolved','rejected'],true)) $metrics['complaints']++;
            foreach ($row['communication'] ?? [] as $message) if (!empty($message['unread'])) $metrics['notices']++;
            if ($row['delivery_status']!=='cancelled') $metrics['value'][$row['currency']] = bcadd($metrics['value'][$row['currency']] ?? '0',$row['totals']['gross'],2);
            foreach ($row['items'] as $line) {
                $remaining = bcsub($line['quantity'],$line['received'],6);
                if (!$active || bccomp($remaining,'0',6)<=0) continue;
                $unit = $line['unit'] ?? 'KG';
                $metrics['outstanding'][$unit] = bcadd($metrics['outstanding'][$unit] ?? '0',$remaining,6);
                $metrics['awaiting_value'][$row['currency']] = bcadd($metrics['awaiting_value'][$row['currency']] ?? '0',bcdiv(bcmul($line['gross'],$remaining,8),$line['quantity'],2),2);
                if (($line['deadline'] ?? $row['deadline'])<now()->format('Y-m-d')) $metrics['late_items']++;
            }
        }
        $rows = $rows->filter(function ($row) use ($request) {
            $search = mb_strtolower(trim((string) $request->query('q', '')));
            $haystack = $row['number'] . ' ' . $row['supplier'] . ' ' . implode(' ', $row['work_orders']);
            return ($search === '' || str_contains(mb_strtolower($haystack), $search))
                && (!$request->filled('status') || $row['delivery_status'] === $request->query('status'))
                && (!$request->boolean('late') || $row['due']['days'] > 0)
                && (!$request->filled('sync') || $row['sync_status'] === $request->query('sync'));
                // Additional filters are applied below to keep the legacy search contract intact.
        });
        $rows = $rows->filter(fn ($row) => (!$request->filled('supplier') || $row['supplier']===$request->query('supplier'))
            && (!$request->boolean('active') || !in_array($row['delivery_status'],['delivered','cancelled','inquiry'],true))
            && (!$request->filled('from') || $row['deadline']>=$request->query('from')) && (!$request->filled('to') || $row['deadline']<=$request->query('to'))
            && (!$request->boolean('soon') || (!in_array($row['delivery_status'],['delivered','cancelled','inquiry'],true) && $row['deadline']>=now()->format('Y-m-d') && $row['deadline']<=now()->addDays(7)->format('Y-m-d')))
            && (!$request->boolean('notices') || count(array_filter($row['communication'] ?? [],fn ($m) => !empty($m['unread'])))>0));
        $sort = in_array($request->query('sort'), ['number','supplier','deadline','created_at'], true) ? $request->query('sort') : 'created_at';
        $rows = $rows->sortBy($sort, SORT_REGULAR, $request->query('direction', 'desc') !== 'asc')->values();
        $page = max(1, (int) $request->query('page', 1));
        $paginator = new LengthAwarePaginator($rows->forPage($page, 30)->values(), $rows->count(), 30, $page, ['path' => $request->url(), 'query' => $request->query()]);
        return view('content.apps.procurement.index', ['rows' => $paginator,'metrics'=>$metrics]);
    }

    public function show(string $id)
    {
        $purchase = $this->service->find($id);
        return view('content.apps.procurement.show', ['purchase' => $purchase, 'due' => $this->calculator->deadline($purchase), 'status' => $this->calculator->deliveryStatus($purchase)]);
    }

    public function printOrder(Request $request, string $id)
    {
        $purchase = $this->service->find($id);
        return view('content.apps.procurement.print', ['purchase'=>$purchase,'supplierDetails'=>$this->source->supplierDetails($purchase['supplier']),'itemSheets'=>$request->boolean('items')]);
    }

    public function scan(Request $request, string $id)
    {
        $purchase = $this->service->find($id);
        $line = $request->query('line');
        if ($line !== null) abort_unless(in_array($line, array_column($purchase['items'],'id'), true),404);
        return view('content.apps.procurement.scan', compact('purchase','line'));
    }

    public function scanReceive(Request $request, string $id)
    {
        $data = $request->validate(['line'=>'nullable|uuid']);
        $purchase = $this->business(fn () => $this->service->receiveScan($id, $data['line'] ?? null, (int) $request->user()->id));
        return redirect()->route('app-procurement-show',$id)->with('success','Materijal stigao — QR prijem je evidentiran.');
    }

    public function receive(Request $request, string $id)
    {
        $data = $request->validate(['submission'=>'required|uuid','reference'=>'required|string|max:255','received_date'=>'required|date_format:Y-m-d','warehouse'=>'required|string|max:255',
                'quantities'=>'required|array|max:100','quantities.*'=>'required|string|max:30','rejected'=>'nullable|array|max:100','rejected.*'=>'nullable|string|max:30','notes'=>'nullable|string|max:4000','overdelivery'=>'nullable|boolean','override_reason'=>'nullable|string|max:1000']);
        $data['_allow_overdelivery'] = $request->boolean('overdelivery') && app(ProcurementAccess::class)->can($request->user(),'manage');
        $this->business(fn () => $this->service->receiveEvent($id, $data, (int) $request->user()->id));
        return redirect()->route('app-procurement-show', $id)->with('success','Prijem je evidentiran.');
    }

    public function receiveForm(Request $request, string $id)
    {
        $purchase = $this->service->find($id);
        if ($request->filled('line')) abort_unless(in_array($request->query('line'), array_column($purchase['items'], 'id'), true), 404);
        if (in_array($this->calculator->deliveryStatus($purchase),['delivered','cancelled','inquiry'],true)) return redirect()->route('app-procurement-show',$id)->with('warning','Nabavka je zaključena. Historija prijema je dostupna u detaljima.');
        return view('content.apps.procurement.receive', ['purchase'=>$purchase,'warehouses'=>$this->source->warehouses(),'submission'=>(string) Str::uuid()]);
    }

    public function cancel(Request $request, string $id)
    {
        $this->business(fn () => $this->service->cancel($id, (int) $request->user()->id));
        return back()->with('success', 'Nabavka je otkazana.');
    }

    public function destroy(Request $request, string $id)
    {
        $this->business(fn () => $this->service->delete($id, (int) $request->user()->id));
        return redirect()->route('app-procurement')->with('success','Narudžbenica je obrisana.');
    }

    public function sync(Request $request, string $id)
    {
        $purchase = $this->business(fn () => $this->service->sync($id, (int) $request->user()->id));
        return back()->with('warning', $purchase['sync_message']);
    }

    public function upload(Request $request, string $id)
    {
        $data = $request->validate(\App\Services\Procurement\ProcurementDocuments::rules());
        $this->business(fn () => app(\App\Services\Procurement\ProcurementDocuments::class)->upload($id, $request->file('file'), $data, (int) $request->user()->id));
        return back()->with('success','Dokument je sačuvan.');
    }

    public function download(string $id, string $documentId)
    {
        return app(\App\Services\Procurement\ProcurementDocuments::class)->download($this->service->find($id), $documentId);
    }

    public function complaint(Request $request, string $id)
    {
        $data = $request->validate(['line_id'=>'required|uuid','receipt_id'=>'nullable|uuid','type'=>'required|in:material,dimensions,damage,quality,quantity,certificate,other',
            'description'=>'required|string|max:4000','responsible'=>'required|string|max:255','accepted'=>'required|string|max:30','rejected'=>'required|string|max:30']);
        $this->business(fn () => $this->service->complaint($id, $data, (int) $request->user()->id));
        return back()->with('success','Reklamacija je evidentirana. Fotografije i priloge dodajte kroz dokumente, uz odabranu stavku.');
    }

    public function resolveComplaint(Request $request, string $id, string $complaintId)
    {
        $data = $request->validate(['status'=>'required|in:sent,processing,resolved,rejected','notes'=>'required|string|max:4000']);
        $this->business(fn () => $this->service->resolveComplaint($id, $complaintId, $data['status'], $data['notes'], (int) $request->user()->id));
        return back()->with('success','Status reklamacije je izmijenjen.');
    }

    public function settings()
    {
        return view('content.apps.procurement.settings', ['suppliers'=>$this->source->suppliers(),'accounts'=>\App\Models\User::where('role', \App\Models\User::ROLE_SUPPLIER)->get(['id','name','username']), 'bindings'=>app(ProcurementAccess::class)->registry()->all()]);
    }

    public function bindSupplier(Request $request)
    {
        $data = $request->validate(['user_id'=>'required|integer','supplier'=>'required|string|max:255','contact'=>'required|email|max:255']);
        abort_unless(\App\Models\User::whereKey($data['user_id'])->where('role', \App\Models\User::ROLE_SUPPLIER)->exists(), 422);
        if (!$this->source->validSupplier($data['supplier'])) throw ValidationException::withMessages(['supplier'=>'Dobavljač ne postoji u Pantheonu.']);
        app(ProcurementAccess::class)->bind((int) $data['user_id'], $data['supplier'], $data['contact'], (int) $request->user()->id);
        return back()->with('success','Korisnik je povezan s dobavljačem.');
    }

    public function labels(Request $request)
    {
        if (!$request->filled('orders')) return redirect()->route('app-procurement')->with('warning','Označite barem jednu nabavku za štampu naljepnica.');
        $data = $request->validate(['orders'=>'required|array|min:1|max:20','orders.*'=>'required|uuid']);
        $labels = [];
        foreach (array_unique($data['orders']) as $id) {
            $purchase = $this->service->find($id);
            $date = \Carbon\Carbon::parse($purchase['header']['document_date'] ?? $purchase['created_at'])->format('d.m.Y.');
            foreach ($purchase['items'] as $index=>$line) {
                $dates = [];
                foreach ($purchase['receipts'] as $receipt) if (bccomp($receipt['accepted'][$index] ?? $receipt['quantities'][$index] ?? '0','0',6)>0) $dates[] = \Carbon\Carbon::parse($receipt['received_date'] ?? $receipt['at'])->format('d.m.Y.');
                $labels[] = ['title'=>$line['source']['name'],'dimensions'=>trim($line['ordered_dimensions'] ?? '') ?: $line['source']['dimensions'],
                    'code'=>$line['source']['material'], 'order'=>($purchase['pantheon_number'] ?: $purchase['number']).'/'.($index+1),
                    'details'=>'Narudžba: '.$date.' · Prijem: '.($dates ? implode(', ',array_unique($dates)):'čeka prijem'),
                    'url'=>route('app-procurement-scan',['id'=>$id,'line'=>$line['id']])];
            }
        }
        return view('content.apps.procurement.labels', ['labels'=>$labels]);
    }

    public function draft(Request $request, string $id)
    {
        $purchase = $this->service->find($id);
        $templates = ['overdue'=>'Upozorenje na kašnjenje isporuke','upcoming'=>'Podsjetnik na predstojeću isporuku','confirmation'=>'Zahtjev za potvrdu isporuke','invoice'=>'Zahtjev za fakturu','certificate'=>'Zahtjev za atest','complaint'=>'Obavijest o reklamaciji'];
        if ($purchase['document_type'] === 'inquiry') $templates = ['inquiry'=>'Upit za cijenu materijala'];
        $kind = (string) $request->query('template', $purchase['document_type'] === 'inquiry' ? 'inquiry' : 'overdue');
        abort_unless(isset($templates[$kind]), 422);
        $contacts = array_values(array_filter(app(ProcurementAccess::class)->registry()->all(), fn ($row) => ($row['kind'] ?? '')==='account' && $row['supplier']===$purchase['supplier']));
        $lines = [];
        foreach ($purchase['items'] as $index=>$line) if (bccomp($line['quantity'],$line['received'],6)>0) $lines[] = ($index+1) . '. ' . $line['source']['material'] . ' — nedostaje ' . bcsub($line['quantity'],$line['received'],6) . ' ' . ($line['unit'] ?? 'KG') . ', rok ' . ($line['deadline'] ?? $purchase['deadline']);
        return view('content.apps.procurement.draft', ['purchase'=>$purchase,'templates'=>$templates,'kind'=>$kind,'contacts'=>$contacts,'subject'=>$templates[$kind] . ' — ' . $purchase['number'], 'body'=>"Poštovani,\n\n" . $templates[$kind] . ($purchase['document_type'] === 'inquiry' ? ' za upit ' : ' za narudžbenicu ') . $purchase['number'] . ".\n\n" . implode("\n", $lines) . "\n\nMolimo Vas za odgovor.\nSrdačan pozdrav"]);
    }

    public function acknowledge(Request $request, string $id)
    {
        $this->service->acknowledge($id,(int)$request->user()->id);
        return back()->with('success','Obavijesti su označene pregledanim.');
    }

    private function business(callable $callback): mixed
    {
        try { return $callback(); }
        catch (InvalidArgumentException $exception) { throw ValidationException::withMessages(['procurement' => $exception->getMessage()]); }
    }
}
