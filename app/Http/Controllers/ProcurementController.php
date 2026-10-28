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
                'receive','receiveForm' => 'receive',
                'store','create','edit','update','selection','upload','complaint' => 'edit',
                'cancel','sync','resolveComplaint','settings','bindSupplier','draft' => 'manage',
                default => 'view',
            };
            abort_unless(app(ProcurementAccess::class)->can($user, $permission), 403);
            return $next($request);
        });
    }

    public function materials(Request $request)
    {
        $filters = $request->validate(['q'=>'nullable|string|max:255', 'sort'=>'nullable|in:work_order,material,order,position,required_date,required', 'direction'=>'nullable|in:asc,desc', 'unit'=>'nullable|string|max:16',
            'status'=>'nullable|in:not_ordered,part_ordered,ordered,partial,received,late,soon,zero','supplier'=>'nullable|string|max:255','work_order'=>'nullable|string|max:255',
            'from'=>'nullable|date_format:Y-m-d','to'=>'nullable|date_format:Y-m-d','selected_only'=>'nullable|boolean','page'=>'nullable|integer|min:1']);
        $selected = $request->session()->get('procurement_selected', []);
        $filters['selected_ids'] = $selected;
        $rows = $this->source->requirements($filters, $this->service->progress());
        return view('content.apps.procurement.materials', compact('rows', 'selected'));
    }

    public function create(Request $request)
    {
        if ($request->isMethod('post')) {
            if (!$request->boolean('from_session')) {
                $data = $request->validate(['selected' => 'required|array|min:1|max:100', 'selected.*' => 'required|string|max:1000|distinct']);
                $request->session()->put('procurement_selected', $data['selected']);
            }
            $request->session()->put('procurement_combine', $request->boolean('combine'));
            $request->session()->put('procurement_overorder_prepare', $request->boolean('overorder_prepare') && app(ProcurementAccess::class)->can($request->user(),'manage'));
            return redirect()->route('app-procurement-create-form');
        }
        $selected = $request->session()->get('procurement_selected', []);
        if ($request->old('items')) {
            $selected = [];
            foreach ($request->old('items') as $line) foreach ($line['allocations'] ?? [['source_id'=>$line['source_id'] ?? '']] as $allocation) $selected[] = $allocation['source_id'];
        }
        if ($selected === []) return redirect()->route('app-procurement-materials');
        try {
            $items = $this->source->selected($selected);
        } catch (InvalidArgumentException $exception) {
            return redirect()->route('app-procurement-materials')->withErrors(['selected' => $exception->getMessage()]);
        }
        $progress = $this->service->progress();
        foreach ($items as $id => $item) {
            if (bccomp($item['required'],'0',6)<=0 || (bccomp($item['required'], $progress[$id]['ordered'] ?? '0', 6) <= 0 && !$request->session()->get('procurement_overorder_prepare'))) return redirect()->route('app-procurement-materials')->withErrors(['selected' => 'Odabrana potreba je već naručena ili nema potrebne količine. Dodatnu nabavku može pripremiti upravitelj uz izričito odobrenje prekoračenja.']);
        }
        $groups = [];
        foreach ($items as $id=>$item) {
            $key = $request->session()->get('procurement_combine') ? $this->service->compatibility($item) : $id;
            $quantity = bcsub($item['required'], $progress[$id]['ordered'] ?? '0', 6);
            if (bccomp($quantity,'0',6)<0) $quantity = '0.000000';
            $groups[$key] ??= ['source'=>$item,'quantity'=>'0','price'=>'','vatRate'=>$item['vat_rate'],'discountRate'=>'0','deadline'=>$item['required_date'] ?? '', 'allocations'=>[]];
            $groups[$key]['quantity'] = bcadd($groups[$key]['quantity'], $quantity, 6);
            $groups[$key]['allocations'][] = ['source'=>$item,'quantity'=>$quantity];
            if (!empty($item['required_date']) && (!$groups[$key]['deadline'] || $item['required_date'] < $groups[$key]['deadline'])) $groups[$key]['deadline'] = $item['required_date'];
        }
        return $this->form(array_values($groups));
    }

    private function form(array $lines, ?array $purchase = null)
    {
        return view('content.apps.procurement.create', ['lines'=>$lines,'purchase'=>$purchase,'suppliers'=>$this->source->suppliers(),'submission'=>(string) Str::uuid()]);
    }

    public function selection(Request $request)
    {
        $data = $request->validate(['add'=>'nullable|array|max:100','add.*'=>'string|max:1000','remove'=>'nullable|array|max:100','remove.*'=>'string|max:1000','clear'=>'nullable|boolean']);
        $selected = $request->boolean('clear') ? [] : $request->session()->get('procurement_selected', []);
        $selected = array_values(array_diff(array_unique([...$selected, ...($data['add'] ?? [])]), $data['remove'] ?? []));
        if (count($selected) > 100) throw ValidationException::withMessages(['selected'=>'Najviše 100 potreba može biti odabrano.']);
        $request->session()->put('procurement_selected', $selected);
        return response()->json(['selected'=>$selected,'count'=>count($selected)]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedPurchase($request);
        $purchase = $this->business(fn () => $this->service->create($data, (int) $request->user()->id));
        $request->session()->forget('procurement_selected');
        return redirect()->route('app-procurement-show', $purchase['id'])->with('success', 'Nabavka je sačuvana.');
    }

    private function validatedPurchase(Request $request): array
    {
        $decimal = ['required', 'string', 'max:30', 'regex:/^\d{1,12}(?:[.,]\d{1,6})?$/D'];
        $data = $request->validate([
            'submission' => 'required|uuid', 'supplier' => 'required|string|max:255', 'deadline' => 'required|date_format:Y-m-d',
            'items' => 'required|array|min:1|max:100', 'items.*.source_id' => 'nullable|string|max:1000|distinct',
            'items.*.quantity' => $decimal, 'items.*.price' => $decimal, 'items.*.vat_rate' => $decimal,
            'items.*.discount_rate'=>'nullable|string|max:30','items.*.weight'=>'nullable|string|max:30','items.*.deadline'=>'nullable|date_format:Y-m-d',
            'items.*.allocations'=>'nullable|array|min:1|max:100','items.*.allocations.*.source_id'=>'required|string|max:1000', 'items.*.allocations.*.quantity'=>$decimal,
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
        $data = $this->validatedPurchase($request);
        $this->business(fn () => $this->service->edit($id, $data, (int) $request->user()->id));
        return redirect()->route('app-procurement-show', $id)->with('success','Nabavka je izmijenjena.');
    }

    public function index(Request $request)
    {
        $request->validate(['q'=>'nullable|string|max:255', 'status'=>'nullable|in:open,partial,delivered,cancelled',
            'sync'=>'nullable|in:blocked,pending,synced,error', 'late'=>'nullable|boolean', 'sort'=>'nullable|in:number,supplier,deadline,created_at',
            'direction'=>'nullable|in:asc,desc', 'page'=>'nullable|integer|min:1','supplier'=>'nullable|string|max:255','from'=>'nullable|date_format:Y-m-d','to'=>'nullable|date_format:Y-m-d','soon'=>'nullable|boolean','notices'=>'nullable|boolean','active'=>'nullable|boolean']);
        $rows = collect($this->service->all())->map(function ($row) {
            $workOrders = [];
            foreach ($row['items'] as $line) foreach ($line['allocations'] ?? [['source'=>$line['source']]] as $allocation) $workOrders[$allocation['source']['work_order_key']] = $allocation['source']['work_order'];
            return $row + ['work_orders'=>$workOrders,'delivery_status' => $this->calculator->deliveryStatus($row), 'due' => $this->calculator->deadline($row)];
        });
        $metrics = ['active'=>0,'late'=>0,'partial'=>0,'soon'=>0,'complaints'=>0,'notices'=>0,'late_items'=>0,'outstanding'=>[],'value'=>[],'awaiting_value'=>[]];
        foreach ($rows as $row) {
            $active = !in_array($row['delivery_status'],['delivered','cancelled'],true);
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
            && (!$request->boolean('active') || !in_array($row['delivery_status'],['delivered','cancelled'],true))
            && (!$request->filled('from') || $row['deadline']>=$request->query('from')) && (!$request->filled('to') || $row['deadline']<=$request->query('to'))
            && (!$request->boolean('soon') || (!in_array($row['delivery_status'],['delivered','cancelled'],true) && $row['deadline']>=now()->format('Y-m-d') && $row['deadline']<=now()->addDays(7)->format('Y-m-d')))
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
        if (in_array($this->calculator->deliveryStatus($purchase),['delivered','cancelled'],true)) return redirect()->route('app-procurement-show',$id)->with('warning','Nabavka je zaključena. Historija prijema je dostupna u detaljima.');
        return view('content.apps.procurement.receive', ['purchase'=>$purchase,'warehouses'=>$this->source->warehouses(),'submission'=>(string) Str::uuid()]);
    }

    public function cancel(Request $request, string $id)
    {
        $this->business(fn () => $this->service->cancel($id, (int) $request->user()->id));
        return back()->with('success', 'Nabavka je otkazana.');
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
        $data = $request->validate(['orders'=>'required|array|min:1|max:20','orders.*'=>'required|uuid']);
        $labels = [];
        foreach (array_unique($data['orders']) as $id) {
            $purchase = $this->service->find($id);
            $labels[] = ['title'=>$purchase['number'],'code'=>$purchase['number'],'url'=>route('app-procurement-receive-form',$id)];
            foreach ($purchase['items'] as $index=>$line) $labels[] = ['title'=>$line['source']['name'],'code'=>$line['source']['material'] . ' · ' . $purchase['number'] . '/' . ($index+1),'url'=>route('app-procurement-receive-form',['id'=>$id,'line'=>$line['id'] ?? ''])];
        }
        return view('content.apps.procurement.labels', ['labels'=>$labels]);
    }

    public function draft(Request $request, string $id)
    {
        $purchase = $this->service->find($id);
        $templates = ['overdue'=>'Upozorenje na kašnjenje isporuke','upcoming'=>'Podsjetnik na predstojeću isporuku','confirmation'=>'Zahtjev za potvrdu isporuke','invoice'=>'Zahtjev za fakturu','certificate'=>'Zahtjev za atest','complaint'=>'Obavijest o reklamaciji'];
        $kind = (string) $request->query('template','overdue');
        abort_unless(isset($templates[$kind]), 422);
        $contacts = array_values(array_filter(app(ProcurementAccess::class)->registry()->all(), fn ($row) => ($row['kind'] ?? '')==='account' && $row['supplier']===$purchase['supplier']));
        $lines = [];
        foreach ($purchase['items'] as $index=>$line) if (bccomp($line['quantity'],$line['received'],6)>0) $lines[] = ($index+1) . '. ' . $line['source']['material'] . ' — nedostaje ' . bcsub($line['quantity'],$line['received'],6) . ' ' . ($line['unit'] ?? 'KG') . ', rok ' . ($line['deadline'] ?? $purchase['deadline']);
        return view('content.apps.procurement.draft', ['purchase'=>$purchase,'templates'=>$templates,'kind'=>$kind,'contacts'=>$contacts,'subject'=>$templates[$kind] . ' — ' . $purchase['number'], 'body'=>"Poštovani,\n\n" . $templates[$kind] . ' za narudžbenicu ' . $purchase['number'] . ".\n\n" . implode("\n", $lines) . "\n\nMolimo Vas za odgovor.\nSrdačan pozdrav"]);
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
