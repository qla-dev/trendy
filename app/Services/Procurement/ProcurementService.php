<?php

namespace App\Services\Procurement;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ProcurementService
{
    public const SYNC_BLOCKER = 'Nabavka je sačuvana lokalno. Prenesite narudžbenicu u Pantheon prije ERP prijema.';

    public function __construct(private ProcurementStore $store, private ProcurementSource $source, private ProcurementCalculator $calculator, private ?ProcurementGateway $gateway = null) {}

    public function all(): array { return array_map(fn ($purchase) => $this->normalize($purchase), array_values(array_filter($this->store->all(), fn ($purchase) => empty($purchase['deleted_at'])))); }

    private function normalize(array $purchase): array
    {
        $purchase += ['header'=>[],'documents'=>[],'complaints'=>[],'communication'=>[],'audit'=>[]];
        foreach ($purchase['items'] as $index=>&$line) {
            $line['id'] ??= (string) \Ramsey\Uuid\Uuid::uuid5(\Ramsey\Uuid\Uuid::NAMESPACE_URL, 'enalog:' . $purchase['id'] . ':' . $index);
        }
        return $purchase;
    }

    public function find(string $id): array
    {
        foreach ($this->all() as $purchase) if ($purchase['id'] === $id) return $purchase;
        abort(404);
    }

    public function reserved(array $rows = null): array
    {
        $reserved = [];
        foreach ($rows ?? $this->all() as $purchase) {
            if (!empty($purchase['deleted_at']) || $this->calculator->deliveryStatus($purchase) === 'cancelled') continue;
            foreach ($purchase['items'] as $item) $reserved[$item['source']['id']] = $purchase['id'];
        }
        return $reserved;
    }

    public function progress(?array $rows = null): array
    {
        $result = [];
        foreach ($rows ?? $this->all() as $purchase) {
            if (!empty($purchase['deleted_at']) || !empty($purchase['cancelled_at'])) continue;
            foreach ($purchase['items'] as $line) {
                $receivedLeft = $line['received'] ?? '0';
                $allocations = $line['allocations'] ?? [['source'=>$line['source'], 'quantity'=>$line['quantity']]];
                foreach ($allocations as $allocation) {
                    $source = $allocation['source'];
                    // Legacy orders recorded kilograms even for non-KG source rows; never invent a conversion.
                    if (strtoupper($line['unit'] ?? 'KG') !== strtoupper($source['unit'])) continue;
                    $id = $source['id'];
                    $result[$id] ??= ['ordered'=>'0.000000','received'=>'0.000000','purchases'=>[],'suppliers'=>[],'late'=>false];
                    $received = bccomp($receivedLeft, $allocation['quantity'], 6) > 0 ? $allocation['quantity'] : $receivedLeft;
                    $receivedLeft = bcsub($receivedLeft, $received, 6);
                    $result[$id]['ordered'] = bcadd($result[$id]['ordered'], $allocation['quantity'], 6);
                    $result[$id]['received'] = bcadd($result[$id]['received'], $received, 6);
                    $result[$id]['purchases'][$purchase['id']] = $purchase['number'];
                    $result[$id]['suppliers'] = array_values(array_unique([...$result[$id]['suppliers'], $purchase['supplier']]));
                    $result[$id]['late'] = $result[$id]['late'] || (bccomp($received, $allocation['quantity'], 6) < 0 && ($line['deadline'] ?? $purchase['deadline']) < substr($this->now(), 0, 10));
                }
            }
        }
        return $result;
    }

    public function compatibility(array $source): string
    {
        return hash('sha256', json_encode([$source['material'], trim($source['dimensions']), strtoupper($source['unit']), $source['vat_rate']]));
    }

    private function prepareItems(array $data, array $rows): array
    {
        $ids = [];
        foreach ($data['items'] as $item) foreach ($item['allocations'] ?? [['source_id'=>$item['source_id'] ?? '', 'quantity'=>$item['quantity']]] as $allocation) $ids[] = $allocation['source_id'];
        $workOrderIds = array_values(array_filter($ids, fn ($id) => !str_starts_with($id,'catalog_')));
        if (count($ids) === 0 || count($ids) > 100 || count(array_unique($workOrderIds)) !== count($workOrderIds)) throw new InvalidArgumentException('Izaberite najviše 100 različitih izvornih potreba.');
        $sources = $this->source->selected($ids);
        $progress = $this->progress($rows);
        $items = [];
        foreach ($data['items'] as $item) {
            $allocations = [];
            $total = '0';
            $compatibility = null;
            $deadlines = [];
            foreach ($item['allocations'] ?? [['source_id'=>$item['source_id'], 'quantity'=>$item['quantity']]] as $allocation) {
                $source = $sources[$allocation['source_id']] ?? null;
                if (!$source) throw new InvalidArgumentException('Izvorna stavka nije dostupna.');
                $key = !empty($item['ordered_dimensions'])
                    ? hash('sha256', json_encode([$source['material'],strtoupper($source['unit']),$source['vat_rate']]))
                    : $this->compatibility($source);
                if ($compatibility !== null && $key !== $compatibility) throw new InvalidArgumentException('Različite materijale, dimenzije, jedinice ili porezne profile nije moguće spojiti.');
                $compatibility = $key;
                $quantity = $this->calculator->decimal($allocation['quantity'], true);
                if (empty($source['stock_order']) && bccomp($source['required'], '0', 6) <= 0) throw new InvalidArgumentException('Zahtjev bez potrebne količine nije moguće nabaviti.');
                $ordered = $progress[$source['id']]['ordered'] ?? '0';
                if (empty($source['stock_order']) && bccomp(bcadd($ordered, $quantity, 6), $source['required'], 6) > 0 && (empty($data['_allow_overorder']) || trim($data['override_reason'] ?? '') === '')) {
                    throw new InvalidArgumentException('Količina prelazi preostalu potrebu. Ovlašteno prekoračenje zahtijeva potvrdu i obrazloženje.');
                }
                $total = bcadd($total, $quantity, 6);
                if (!empty($source['required_date'])) $deadlines[] = $source['required_date'];
                $allocations[] = ['source'=>$source,'quantity'=>$quantity];
            }
            $orderedQuantity = $this->calculator->decimal($item['quantity'], true);
            if (bccomp($total, $orderedQuantity, 6) !== 0 && empty($item['ordered_dimensions'])) throw new InvalidArgumentException('Zbir raspodjela mora biti jednak količini stavke.');
            if (bccomp($total, $orderedQuantity, 6) > 0) throw new InvalidArgumentException('Naručena količina ne može biti manja od raspodijeljene potrebe.');
            sort($deadlines);
            $deadline = $item['deadline'] ?? $data['deadline'];
            $this->validDate($deadline);
            $line = $this->calculator->line($orderedQuantity, $item['price'], $item['vat_rate'], $item['discount_rate'] ?? '0');
            $items[] = $line + ['id'=>(string) Str::uuid(), 'source'=>$allocations[0]['source'], 'allocations'=>$allocations,
                'ordered_dimensions'=>trim($item['ordered_dimensions'] ?? $allocations[0]['source']['dimensions']),
                'pieces'=>isset($item['pieces']) && $item['pieces'] !== '' ? $this->calculator->decimal($item['pieces'], true) : null,
                'unit'=>$allocations[0]['source']['unit'], 'weight'=>isset($item['weight']) && $item['weight'] !== '' ? $this->calculator->decimal($item['weight']) : null,
                'deadline'=>$deadline, 'required_date'=>$deadlines[0] ?? null, 'received'=>'0.000000'];
        }
        return $items;
    }

    private function validDate(string $value): string
    {
        try { $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, config('app.timezone')); }
        catch (\Throwable $exception) { throw new InvalidArgumentException('Datum nije ispravan.'); }
        if (!$date || $date->format('Y-m-d') !== $value) throw new InvalidArgumentException('Datum nije ispravan.');
        return $value;
    }

    public function create(array $data, int $userId): array
    {
        return $this->store->update(function (array &$rows) use ($data, $userId) {
            // Idempotency is evaluated under the same lock as reservation and commit.
            foreach ($rows as $row) if ($row['submission'] === $data['submission'] && $row['created_by'] === $userId) return $row;
            $date = $this->validDate($data['deadline']);
            if (!$this->source->validSupplier($data['supplier'])) throw new InvalidArgumentException('Izaberite postojećeg Pantheon dobavljača.');
            $items = $this->prepareItems($data, $rows);
            $purchase = [
                'id' => (string) Str::uuid(), 'number' => 'NS-' . str_pad((string) (count($rows) + 1), 6, '0', STR_PAD_LEFT),
                'submission' => $data['submission'], 'created_by' => $userId, 'created_at' => $this->now(),
                'supplier' => trim($data['supplier']), 'deadline' => $date, 'schema_version'=>2,
                'currency' => config('workorders.catalog_product_defaults.purchase_currency', 'KM'),
                'items' => $items, 'totals' => $this->calculator->totals($items), 'receipts' => [],
                'cancelled_at' => null, 'sync_status' => 'pending', 'sync_message' => self::SYNC_BLOCKER,
                'sync_attempts' => 0, 'pantheon_key' => null, 'pantheon_number' => null,
                'header'=>$this->header($data), 'documents'=>[], 'complaints'=>[], 'communication'=>[],
                'audit'=>[['action'=>'created','user_id'=>$userId,'at'=>$this->now()]],
                'override_reason'=>!empty($data['_allow_overorder']) ? ($data['override_reason'] ?? '') : '',
            ];
            $rows[] = $purchase;
            return $purchase;
        });
    }

    private function header(array $data): array
    {
        return array_intersect_key($data, array_flip(['document_date','carrier','valid_until','department','procurement_type','responsible_person','reference_person','notes','exchange_rate']));
    }

    private function auditItems(array $items): array
    {
        return array_map(function($item) {
            return array_intersect_key($item,array_flip(['id','quantity','price','vatRate','discountRate','unit','weight','deadline','ordered_dimensions','pieces'])) + ['allocations'=>array_map(fn($allocation)=>['source_id'=>$allocation['source']['id'],'quantity'=>$allocation['quantity']],$item['allocations'] ?? [['source'=>$item['source'],'quantity'=>$item['quantity']]])];
        },$items);
    }

    public function edit(string $id, array $data, int $userId): array
    {
        return $this->store->update(function (&$rows) use ($id, $data, $userId) {
            foreach ($rows as &$purchase) {
                if ($purchase['id'] !== $id) continue;
                if (!empty($purchase['deleted_at'])) abort(404);
                $purchase = $this->normalize($purchase);
                if (!empty($purchase['pantheon_key']) || $purchase['cancelled_at'] || $purchase['receipts']) throw new InvalidArgumentException('Izmjena nije dozvoljena nakon prijema, otkazivanja ili ERP prijenosa.');
                foreach ($purchase['items'] as $line) if (strtoupper($line['unit'] ?? 'KG') !== strtoupper($line['source']['unit'])) throw new InvalidArgumentException('Starija nabavka ima drugačiju jedinicu od izvora. Potrebna je ručna provjera prije izmjene.');
                if (!$this->source->validSupplier($data['supplier'])) throw new InvalidArgumentException('Dobavljač ne postoji.');
                $others = array_values(array_filter($rows, fn ($r) => $r['id'] !== $id));
                $items = $this->prepareItems($data, $others);
                $usedIds = [];
                foreach ($items as &$newLine) {
                    $matches = [];
                    foreach ($purchase['items'] as $oldLine) {
                        if (isset($usedIds[$oldLine['id']])) continue;
                        $oldIds = array_column(array_column($oldLine['allocations'] ?? [['source'=>$oldLine['source']]], 'source'), 'id');
                        $newIds = array_column(array_column($newLine['allocations'], 'source'), 'id');
                        sort($oldIds); sort($newIds);
                        if ($oldIds === $newIds && $this->compatibility($oldLine['source']) === $this->compatibility($newLine['source'])) $matches[] = $oldLine;
                    }
                    usort($matches, fn ($a,$b) => (int)(($b['ordered_dimensions'] ?? $b['source']['dimensions']) === $newLine['ordered_dimensions']) <=> (int)(($a['ordered_dimensions'] ?? $a['source']['dimensions']) === $newLine['ordered_dimensions']));
                    if ($matches) { $newLine['id'] = $matches[0]['id']; $usedIds[$newLine['id']] = true; }
                }
                unset($newLine);
                $before = ['supplier'=>$purchase['supplier'],'deadline'=>$purchase['deadline'],'totals'=>$purchase['totals'],'items'=>$this->auditItems($purchase['items']),'header'=>$purchase['header'] ?? []];
                $purchase['supplier'] = $data['supplier'];
                $purchase['deadline'] = $this->validDate($data['deadline']);
                $purchase['items'] = $items;
                $purchase['totals'] = $this->calculator->totals($items);
                $purchase['header'] = $this->header($data);
                $purchase['audit'][] = ['action'=>'modified','user_id'=>$userId,'at'=>$this->now(),'before'=>$before,'after'=>['supplier'=>$purchase['supplier'],'deadline'=>$purchase['deadline'],'totals'=>$purchase['totals'],'items'=>$this->auditItems($items),'header'=>$purchase['header']]];
                return $purchase;
            }
            abort(404);
        });
    }

    public function receive(string $id, array $quantities, string $reference, int $userId): array
    {
        return $this->change($id, function (array &$purchase) use ($quantities, $reference, $userId) {
            if ($purchase['cancelled_at']) throw new InvalidArgumentException('Otkazana nabavka nema prijem.');
            if (trim($reference) === '' || count($quantities) !== count($purchase['items'])) throw new InvalidArgumentException('Unesite prijemnicu i sve primljene količine.');
            $changed = false;
            foreach ($purchase['items'] as $index => &$item) {
                $value = $this->calculator->decimal($quantities[$index] ?? '');
                if (bccomp($value, $item['received'], 6) < 0 || bccomp($value, $item['quantity'], 6) > 0) throw new InvalidArgumentException('Primljena kilaža ne može biti smanjena ili veća od naručene.');
                if (bccomp($value, $item['received'], 6) > 0) $changed = true;
                $item['received'] = $value;
            }
            unset($item);
            if ($changed) {
                foreach ($purchase['receipts'] as $receipt) if ($receipt['reference'] === trim($reference)) throw new InvalidArgumentException('Ova prijemnica je već evidentirana.');
                $purchase['receipts'][] = ['reference' => trim($reference), 'user_id' => $userId, 'at' => $this->now(), 'quantities' => array_column($purchase['items'], 'received')];
            }
        });
    }

    public function cancel(string $id, int $userId): array
    {
        return $this->change($id, function (array &$purchase) use ($userId) {
            if (!empty($purchase['pantheon_key'])) throw new InvalidArgumentException('Otkazivanje ERP dokumenta zahtijeva potvrđeni Pantheon postupak.');
            foreach ($purchase['items'] as $item) if (bccomp($item['received'], '0', 6) > 0) throw new InvalidArgumentException('Nabavka s prijemom se ne može otkazati.');
            $purchase['cancelled_at'] = $purchase['cancelled_at'] ?? $this->now();
            $purchase['cancelled_by'] = $userId;
            $purchase['audit'][] = ['action'=>'cancelled','user_id'=>$userId,'at'=>$this->now()];
        });
    }

    public function canDelete(array $purchase): bool
    {
        return empty($purchase['deleted_at']) && empty($purchase['pantheon_key']) && empty($purchase['receipts']) && empty($purchase['sync_attempts'])
            && !array_filter($purchase['items'], fn ($item) => bccomp($item['received'] ?? '0','0',6)>0);
    }

    public function delete(string $id, int $userId): void
    {
        $this->change($id, function (&$purchase) use ($userId) {
            if (!$this->canDelete($purchase)) {
                throw new InvalidArgumentException('Brisanje nije dozvoljeno nakon prijema ili pokušaja ERP prijenosa.');
            }
            $purchase['deleted_at'] = $this->now();
            $purchase['deleted_by'] = $userId;
            $purchase['audit'][] = ['action'=>'deleted','user_id'=>$userId,'at'=>$this->now()];
        });
    }

    public function sync(string $id, int $userId): array
    {
        return $this->change($id, function (array &$purchase) use ($userId) {
            if (!empty($purchase['pantheon_key'])) return;
            if ($purchase['cancelled_at']) throw new InvalidArgumentException('Otkazana nabavka se ne prenosi u ERP.');
            $purchase['sync_attempts']++;
            $purchase['sync_attempted_at'] = $this->now();
            try {
                $purchase['sync_status'] = 'pending';
                if ($purchase['receipts']) throw new \LogicException('Postoje lokalni prijemi prije ERP prijenosa. Potrebno je uskladiti ih prije kreiranja ERP narudžbenice.');
                $confirmation = ($this->gateway ?? app(ProcurementGateway::class))->transfer($purchase + ['erp_actor_id'=>$userId], 'enalog-procurement-' . $purchase['id']);
                if (($confirmation['confirmed'] ?? false) !== true || empty($confirmation['key']) || empty($confirmation['number'])) throw new \RuntimeException('ERP nije potvrdio dokument.');
                $purchase['pantheon_key'] = $confirmation['key'];
                $purchase['pantheon_number'] = $confirmation['number'];
                $purchase['synced_at'] = $this->now();
                $purchase['sync_status'] = 'synced';
                $purchase['sync_message'] = 'Sinhronizovano s Pantheonom.';
                $purchase['last_sync_error'] = null;
            } catch (\Throwable $exception) {
                $purchase['sync_status'] = $exception instanceof \LogicException ? 'blocked' : 'error';
                $purchase['sync_message'] = $purchase['sync_status'] === 'blocked' ? self::SYNC_BLOCKER : 'ERP nije potvrdio prijenos. Lokalna nabavka je sačuvana; ponovni pokušaj koristi isti ključ.';
                $purchase['last_sync_error'] = $purchase['sync_message'];
                Log::warning('Procurement ERP transfer failed.', ['purchase_id'=>$purchase['id'],'user_id'=>$userId,'error'=>$exception->getMessage()]);
            }
            $purchase['audit'][] = ['action'=>'sync_' . $purchase['sync_status'],'user_id'=>$userId,'at'=>$this->now()];
        });
    }

    public function change(string $id, callable $callback): array
    {
        return $this->store->update(function (array &$rows) use ($id, $callback) {
            foreach ($rows as &$purchase) {
                if ($purchase['id'] !== $id) continue;
                if (!empty($purchase['deleted_at'])) abort(404);
                $purchase = $this->normalize($purchase);
                $callback($purchase);
                return $purchase;
            }
            abort(404);
        });
    }

    public function receiveEvent(string $id, array $data, int $userId): array
    {
        return $this->change($id, function (&$purchase) use ($data, $userId) {
            if ($purchase['cancelled_at']) throw new InvalidArgumentException('Otkazana nabavka nema prijem.');
            foreach ($purchase['receipts'] as $receipt) {
                if (($receipt['submission'] ?? null) === $data['submission']) return;
                if ($receipt['reference'] === $data['reference']) throw new InvalidArgumentException('Prijemnica je već evidentirana.');
            }
            if (!$this->source->validWarehouse($data['warehouse'])) throw new InvalidArgumentException('Izaberite postojeće skladište.');
            $date = $this->validDate($data['received_date']);
            if ($date > substr($this->now(), 0, 10)) throw new InvalidArgumentException('Prijem ne može biti u budućnosti.');
            $accepted = [];
            $rejected = [];
            $hasQuantity = false;
            foreach ($purchase['items'] as $index => &$item) {
                $quantity = $this->calculator->decimal($data['quantities'][$index] ?? '0');
                $reject = $this->calculator->decimal($data['rejected'][$index] ?? '0');
                $new = bcadd($item['received'], $quantity, 6);
                if (bccomp($new, $item['quantity'], 6) > 0 && !(config('procurement.allow_overdelivery') && !empty($data['_allow_overdelivery']) && !empty($data['override_reason']))) throw new InvalidArgumentException('Prijem prelazi preostalu naručenu količinu.');
                if (bccomp($quantity, '0', 6) > 0 || bccomp($reject, '0', 6) > 0) $hasQuantity = true;
                $item['received'] = $new;
                $accepted[] = $quantity;
                $rejected[] = $reject;
            }
            unset($item);
            if (!$hasQuantity) throw new InvalidArgumentException('Unesite primljenu ili odbijenu količinu.');
            $event = ['id'=>(string) Str::uuid(),'submission'=>$data['submission'],'reference'=>$data['reference'],'user_id'=>$userId,'at'=>$this->now(),
                'received_date'=>$date,'warehouse'=>$data['warehouse'],'notes'=>$data['notes'] ?? '', 'accepted'=>$accepted,'rejected'=>$rejected,'quantities'=>array_column($purchase['items'], 'received')];
            if (!empty($purchase['pantheon_key'])) {
                $gateway = $this->gateway ?? app(ProcurementGateway::class);
                if (!$gateway instanceof ProcurementReceiptGateway) throw new InvalidArgumentException('ERP adapter ne podržava prijem.');
                try {
                    $confirmation = $gateway->receive($purchase, $event, 'enalog-receipt-' . $data['submission']);
                } catch (\Throwable $exception) {
                    Log::warning('Procurement ERP receipt failed.', ['purchase_id'=>$purchase['id'],'user_id'=>$userId,'error'=>$exception->getMessage()]);
                    throw new InvalidArgumentException('Pantheon nije potvrdio prijem. Podaci nisu lokalno evidentirani. Ponovite isti obrazac; ako je ERP već prihvatio prijem, postojeći dokument će biti prepoznat bez dupliranja.');
                }
                if (($confirmation['confirmed'] ?? false) !== true || (empty($confirmation['key']) && empty($confirmation['rejected_only']))) {
                    throw new InvalidArgumentException('ERP nije potvrdio prijem. Lokalni prijem nije sačuvan.');
                }
                $event['pantheon_key'] = $confirmation['key'];
                $event['pantheon_number'] = $confirmation['number'];
                $event['sync_status'] = 'synced';
            } else {
                $event['sync_status'] = 'local';
            }
            $purchase['receipts'][] = $event;
            $purchase['audit'][] = ['action'=>'received','user_id'=>$userId,'at'=>$this->now(),'event_id'=>$event['id'],'accepted'=>$accepted,'rejected'=>$rejected];
        });
    }

    public function receiveScan(string $id, ?string $lineId, int $userId): array
    {
        $purchase = $this->find($id);
        if ($lineId !== null && !in_array($lineId, array_column($purchase['items'], 'id'), true)) abort(404);
        if ($purchase['cancelled_at']) throw new InvalidArgumentException('Narudžbenica je otkazana.');
        if (empty($purchase['pantheon_key'])) {
            $purchase = $this->sync($id, $userId);
            if (empty($purchase['pantheon_key'])) throw new InvalidArgumentException('Pantheon nije potvrdio narudžbenicu. Materijal nije zaprimljen.');
        }
        $quantities = [];
        foreach ($purchase['items'] as $item) $quantities[] = $lineId === null || $lineId === $item['id'] ? bcsub($item['quantity'],$item['received'],6) : '0';
        if (!array_filter($quantities, fn ($q) => bccomp($q,'0',6)>0)) return $purchase;
        $token = (string) \Ramsey\Uuid\Uuid::uuid5(\Ramsey\Uuid\Uuid::NAMESPACE_URL, 'procurement-scan:'.$id.':'.($lineId ?? 'all'));
        return $this->receiveEvent($id, ['submission'=>$token,'reference'=>'QR-'.$purchase['number'].'-'.($lineId ?? 'all'),
            'received_date'=>substr($this->now(),0,10),'warehouse'=>config('procurement.warehouse'), 'quantities'=>$quantities,
            'notes'=>'Materijal stigao — QR skeniranje'], $userId);
    }

    public function addDocument(string $id, array $document, int $userId): array
    {
        return $this->change($id, function (&$purchase) use ($document, $userId) {
            if (isset($document['line_id']) && $document['line_id'] !== '' && !in_array($document['line_id'], array_column($purchase['items'], 'id'), true)) throw new InvalidArgumentException('Stavka dokumenta ne pripada nabavci.');
            if (!empty($document['complaint_id']) && !in_array($document['complaint_id'], array_column($purchase['complaints'] ?? [],'id'),true)) throw new InvalidArgumentException('Reklamacija ne pripada nabavci.');
            if (!empty($document['receipt_id']) && !in_array($document['receipt_id'], array_column($purchase['receipts'] ?? [],'id'),true)) throw new InvalidArgumentException('Prijem dokumenta ne pripada nabavci.');
            $purchase['documents'][] = $document + ['id'=>(string) Str::uuid(),'user_id'=>$userId,'at'=>$this->now()];
            $purchase['audit'][] = ['action'=>'document_uploaded','user_id'=>$userId,'at'=>$this->now(),'type'=>$document['type']];
        });
    }

    public function supplierEvent(string $id, array $data, int $userId): array
    {
        return $this->change($id, function (&$purchase) use ($data, $userId) {
            if ($purchase['cancelled_at']) throw new InvalidArgumentException('Nabavka je otkazana.');
            if ($data['action'] === 'delay') {
                $found = false;
                foreach ($purchase['items'] as &$item) {
                    if (($item['id'] ?? '') !== $data['line_id']) continue;
                    if (bccomp($item['quantity'],$item['received'],6)<=0) throw new InvalidArgumentException('Potpuno primljena stavka nema novu najavu kašnjenja.');
                    if ($data['expected_date'] < substr($this->now(),0,10)) throw new InvalidArgumentException('Novi očekivani datum ne može biti u prošlosti.');
                    if (($item['expected_date'] ?? '')===$data['expected_date'] && ($item['delay_reason'] ?? '')===$data['reason']) return;
                    $item['expected_date'] = $this->validDate($data['expected_date']);
                    $item['delay_reason'] = $data['reason'];
                    $found = true;
                }
                unset($item);
                if (!$found) throw new InvalidArgumentException('Stavka nije pronađena.');
            } else {
                if (!empty($purchase['supplier_confirmed_at'])) return;
                $purchase['supplier_confirmed_at'] = $this->now();
            }
            $purchase['communication'][] = $data + ['id'=>(string) Str::uuid(),'user_id'=>$userId,'at'=>$this->now(),'unread'=>true];
            $purchase['audit'][] = ['action'=>'supplier_' . $data['action'],'user_id'=>$userId,'at'=>$this->now()];
        });
    }

    public function acknowledge(string $id, int $userId): array
    {
        return $this->change($id,function (&$purchase) use ($userId) {
            foreach ($purchase['communication'] as &$message) { $message['unread']=false;$message['read_by']=$userId; }
            $purchase['audit'][]=['action'=>'notices_read','user_id'=>$userId,'at'=>$this->now()];
        });
    }

    public function complaint(string $id, array $data, int $userId): array
    {
        return $this->change($id, function (&$purchase) use ($data, $userId) {
            $line = null;
            $lineIndex = null;
            foreach ($purchase['items'] as $index=>$item) if (($item['id'] ?? '') === $data['line_id']) { $line = $item; $lineIndex = $index; }
            if (!$line) throw new InvalidArgumentException('Stavka reklamacije nije pronađena.');
            if (!empty($data['receipt_id']) && !in_array($data['receipt_id'], array_column($purchase['receipts'], 'id'), true)) throw new InvalidArgumentException('Prijem ne pripada nabavci.');
            foreach (['accepted','rejected'] as $field) $data[$field] = $this->calculator->decimal($data[$field] ?? '0');
            if (!empty($data['receipt_id'])) foreach ($purchase['receipts'] as $receipt) {
                if (($receipt['id'] ?? '') !== $data['receipt_id']) continue;
                $actual = bcadd($receipt['accepted'][$lineIndex] ?? $receipt['quantities'][$lineIndex] ?? '0',$receipt['rejected'][$lineIndex] ?? '0',6);
                if (bccomp(bcadd($data['accepted'],$data['rejected'],6),$actual,6)>0) throw new InvalidArgumentException('Količine reklamacije prelaze evidentirani prijem za tu stavku.');
            }
            $purchase['complaints'][] = $data + ['id'=>(string) Str::uuid(),'number'=>'R-' . $purchase['number'] . '-' . (count($purchase['complaints'] ?? []) + 1),
                'status'=>'open','date'=>substr($this->now(), 0, 10),'expected'=>$line['quantity'],'received'=>$line['received'],'user_id'=>$userId,'at'=>$this->now()];
            $purchase['audit'][] = ['action'=>'complaint_created','user_id'=>$userId,'at'=>$this->now()];
        });
    }

    public function resolveComplaint(string $id, string $complaintId, string $status, string $notes, int $userId): array
    {
        return $this->change($id, function (&$purchase) use ($complaintId, $status, $notes, $userId) {
            $allowed = ['open'=>['sent','processing','resolved','rejected'], 'sent'=>['processing','resolved','rejected'], 'processing'=>['resolved','rejected'], 'resolved'=>[], 'rejected'=>[]];
            foreach ($purchase['complaints'] ?? [] as $index=>$complaint) {
                if ($complaint['id'] !== $complaintId) continue;
                if (!in_array($status, $allowed[$complaint['status']], true)) throw new InvalidArgumentException('Promjena statusa reklamacije nije dozvoljena.');
                $purchase['complaints'][$index]['status'] = $status;
                $purchase['complaints'][$index]['resolution'] = $notes;
                $purchase['complaints'][$index]['resolved_at'] = in_array($status, ['resolved','rejected'], true) ? $this->now() : null;
                $purchase['audit'][] = ['action'=>'complaint_' . $status,'user_id'=>$userId,'at'=>$this->now(),'complaint_id'=>$complaintId,'notes'=>$notes];
                return;
            }
            abort(404);
        });
    }

    private function now(): string { return CarbonImmutable::now(config('app.timezone'))->toIso8601String(); }
}
