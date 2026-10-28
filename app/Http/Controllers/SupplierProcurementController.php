<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Procurement\ProcurementAccess;
use App\Services\Procurement\ProcurementService;
use App\Services\Procurement\ProcurementCalculator;
use App\Services\Procurement\ProcurementDocuments;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SupplierProcurementController extends Controller
{
    public function __construct(private ProcurementService $service, private ProcurementAccess $access)
    {
        $this->middleware(function ($request, $next) {
            abort_unless($request->user()?->getRole() === User::ROLE_SUPPLIER && $this->access->supplier($request->user()) !== null, 403);
            return $next($request);
        });
    }

    private function owned(Request $request, string $id): array
    {
        $purchase = $this->service->find($id);
        abort_unless($this->access->owns($request->user(), $purchase), 404);
        return $purchase;
    }

    private function publicOrder(array $purchase): array
    {
        $calculator = app(ProcurementCalculator::class);
        return ['id'=>$purchase['id'],'number'=>$purchase['number'],'created_at'=>$purchase['created_at'],'deadline'=>$purchase['deadline'],
            'status'=>$calculator->deliveryStatus($purchase),'due'=>$calculator->deadline($purchase),'confirmed_at'=>$purchase['supplier_confirmed_at'] ?? null,
            'items'=>array_map(fn ($line) => ['id'=>$line['id'] ?? null,'material'=>$line['source']['material'],'name'=>$line['source']['name'],'dimensions'=>$line['source']['dimensions'],
                'quantity'=>$line['quantity'],'unit'=>$line['unit'] ?? 'KG','received'=>$line['received'],'deadline'=>$line['deadline'] ?? $purchase['deadline'],'expected_date'=>$line['expected_date'] ?? null,'delay_reason'=>$line['delay_reason'] ?? ''], $purchase['items']),
            'documents'=>array_map(fn ($doc) => array_intersect_key($doc,array_flip(['id','name','type','line_id','at'])),array_values(array_filter($purchase['documents'] ?? [],fn ($doc) => !empty($doc['shared'])))),
            'complaints'=>array_map(fn ($c) => array_intersect_key($c,array_flip(['number','line_id','type','description','status','resolution','date'])), $purchase['complaints'] ?? [])];
    }

    public function index(Request $request)
    {
        $request->validate(['q'=>'nullable|string|max:255','open'=>'nullable|boolean']);
        $rows = [];
        foreach ($this->service->all() as $purchase) {
            if (!$this->access->owns($request->user(),$purchase)) continue;
            $order = $this->publicOrder($purchase);
            if ($request->boolean('open') && in_array($order['status'],['delivered','cancelled'],true)) continue;
            if ($request->filled('q') && !str_contains(mb_strtolower($order['number']),mb_strtolower($request->query('q')))) continue;
            $rows[] = $order;
        }
        return view('content.apps.procurement.supplier-index',['orders'=>$rows]);
    }

    public function show(Request $request, string $id)
    {
        return view('content.apps.procurement.supplier-show',['order'=>$this->publicOrder($this->owned($request,$id))]);
    }

    public function event(Request $request, string $id)
    {
        $this->owned($request,$id);
        $data = $request->validate(['action'=>'required|in:confirm,delay','line_id'=>'required_if:action,delay|nullable|uuid',
            'expected_date'=>'required_if:action,delay|nullable|date_format:Y-m-d','reason'=>'required_if:action,delay|nullable|string|max:4000']);
        try { $this->service->supplierEvent($id,$data,(int)$request->user()->id); }
        catch (\InvalidArgumentException $exception) { throw ValidationException::withMessages(['supplier'=>$exception->getMessage()]); }
        return back()->with('success','Obavijest je evidentirana i dostupna odgovornim korisnicima e-Naloga.');
    }

    public function upload(Request $request, string $id)
    {
        $this->owned($request,$id);
        $data = $request->validate(ProcurementDocuments::rules());
        abort_unless(in_array($data['type'],['invoice','certificate'],true),422);
        $data['shared'] = true;
        try { app(ProcurementDocuments::class)->upload($id,$request->file('file'),$data,(int)$request->user()->id); }
        catch (\InvalidArgumentException $exception) { throw ValidationException::withMessages(['document'=>$exception->getMessage()]); }
        return back()->with('success','Dokument je sačuvan.');
    }

    public function download(Request $request, string $id, string $documentId)
    {
        return app(ProcurementDocuments::class)->download($this->owned($request,$id),$documentId,true);
    }
}
