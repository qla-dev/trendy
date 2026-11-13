<?php

namespace App\Http\Controllers\Cnc;

use App\Http\Controllers\Controller;
use App\Models\Cnc\CncActivityLog;
use App\Models\Cnc\CncCustomer;
use App\Models\Cnc\CncHeatTreatment;
use App\Models\Cnc\CncMachine;
use App\Models\Cnc\CncMaterial;
use App\Models\Cnc\CncOtherOperation;
use App\Models\Cnc\CncPosition;
use App\Models\Cnc\CncQuote;
use App\Models\Cnc\CncQuoteContactHistory;
use App\Models\Cnc\CncQuoteDraft;
use App\Models\Cnc\CncSetting;
use App\Models\Cnc\CncSurfaceProtection;
use App\Models\Cnc\CncUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class CncApiController extends Controller
{
    private function cncUser(Request $request): CncUser
    {
        return $request->attributes->get('cnc_user');
    }

    private function logActivity(Request $request, string $action, string $entityType, $entityId, string $entityLabel, string $description): void
    {
        $user = $this->cncUser($request);
        CncActivityLog::create([
            'actor_id' => $user->id,
            'actor_name' => $user->ime_prezime,
            'actor_email' => $user->email,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => (string) $entityId,
            'entity_label' => $entityLabel,
            'description' => mb_substr($description, 0, 500),
        ]);
    }

    // ===================== CUSTOMERS =====================

    public function customers(): JsonResponse
    {
        $customers = CncCustomer::orderBy('naziv')->get();
        return response()->json($customers);
    }

    public function storeCustomer(Request $request): JsonResponse
    {
        $data = $request->validate([
            'naziv' => 'required|string|max:255',
            'adresa' => 'nullable|string|max:255',
            'postanski_broj' => 'nullable|string|max:20',
            'grad' => 'nullable|string|max:100',
            'drzava' => 'nullable|string|max:100',
            'kontakt_osoba' => 'nullable|string|max:255',
            'email' => 'nullable|string|max:255',
            'telefon' => 'nullable|string|max:50',
            'aktivan' => 'boolean',
            'napomena' => 'nullable|string',
        ]);

        $customer = CncCustomer::create($data);
        $this->logActivity($request, 'create', 'customer', $customer->id, $customer->naziv, "Kreiran kupac: {$customer->naziv}");

        return response()->json($customer, 201);
    }

    public function updateCustomer(Request $request, $id): JsonResponse
    {
        $customer = CncCustomer::findOrFail($id);
        $data = $request->validate([
            'naziv' => 'sometimes|string|max:255',
            'adresa' => 'nullable|string|max:255',
            'postanski_broj' => 'nullable|string|max:20',
            'grad' => 'nullable|string|max:100',
            'drzava' => 'nullable|string|max:100',
            'kontakt_osoba' => 'nullable|string|max:255',
            'email' => 'nullable|string|max:255',
            'telefon' => 'nullable|string|max:50',
            'aktivan' => 'boolean',
            'napomena' => 'nullable|string',
        ]);

        $customer->update($data);
        $action = isset($data['aktivan']) ? ($data['aktivan'] ? 'activate' : 'deactivate') : 'update';
        $this->logActivity($request, $action, 'customer', $customer->id, $customer->naziv, "Izmijenjen kupac: {$customer->naziv}");

        return response()->json($customer);
    }

    public function deleteCustomer(Request $request, $id): JsonResponse
    {
        $customer = CncCustomer::findOrFail($id);
        $this->logActivity($request, 'delete', 'customer', $customer->id, $customer->naziv, "Obrisan kupac: {$customer->naziv}");
        $customer->delete();

        return response()->json(['ok' => true]);
    }

    // ===================== MATERIALS =====================

    public function materials(): JsonResponse
    {
        $materials = CncMaterial::orderBy('oznaka')->get();
        return response()->json($materials);
    }

    public function storeMaterial(Request $request): JsonResponse
    {
        $data = $request->validate([
            'oznaka' => 'required|string|max:100',
            'naziv' => 'required|string|max:255',
            'grupa' => 'nullable|string|max:100',
            'standard' => 'nullable|string|max:100',
            'gustina' => 'numeric',
            'cijena_po_kg' => 'numeric',
            'aktivno' => 'boolean',
            'napomena' => 'nullable|string',
        ]);

        $material = CncMaterial::create($data);
        $this->logActivity($request, 'create', 'material', $material->id, $material->oznaka, "Dodan materijal {$material->oznaka}");

        return response()->json($material, 201);
    }

    public function updateMaterial(Request $request, $id): JsonResponse
    {
        $material = CncMaterial::findOrFail($id);
        $data = $request->validate([
            'oznaka' => 'sometimes|string|max:100',
            'naziv' => 'sometimes|string|max:255',
            'grupa' => 'nullable|string|max:100',
            'standard' => 'nullable|string|max:100',
            'gustina' => 'numeric',
            'cijena_po_kg' => 'numeric',
            'aktivno' => 'boolean',
            'napomena' => 'nullable|string',
        ]);

        $material->update($data);
        $action = isset($data['aktivno']) ? ($data['aktivno'] ? 'activate' : 'deactivate') : 'update';
        $this->logActivity($request, $action, 'material', $material->id, $material->oznaka, "Izmijenjen materijal {$material->oznaka}");

        return response()->json($material);
    }

    // ===================== MACHINES =====================

    public function machines(): JsonResponse
    {
        $machines = CncMachine::orderBy('naziv')->get();
        return response()->json($machines);
    }

    public function storeMachine(Request $request): JsonResponse
    {
        $data = $request->validate([
            'oznaka' => 'required|string|max:100',
            'naziv' => 'required|string|max:255',
            'tip' => 'nullable|string|max:255',
            'satnica_programiranja' => 'numeric',
            'satnica_pripreme' => 'numeric',
            'satnica_obrade' => 'numeric',
            'aktivna' => 'boolean',
            'napomena' => 'nullable|string',
        ]);

        $machine = CncMachine::create($data);
        $this->logActivity($request, 'create', 'machine', $machine->id, $machine->oznaka, "Dodana mašina {$machine->naziv}");

        return response()->json($machine, 201);
    }

    public function updateMachine(Request $request, $id): JsonResponse
    {
        $machine = CncMachine::findOrFail($id);
        $data = $request->validate([
            'naziv' => 'sometimes|string|max:255',
            'tip' => 'nullable|string|max:255',
            'satnica_programiranja' => 'numeric',
            'satnica_pripreme' => 'numeric',
            'satnica_obrade' => 'numeric',
            'aktivna' => 'boolean',
            'napomena' => 'nullable|string',
        ]);

        $machine->update($data);
        $action = isset($data['aktivna']) ? ($data['aktivna'] ? 'activate' : 'deactivate') : 'update';
        $this->logActivity($request, $action, 'machine', $machine->id, $machine->oznaka, "Izmijenjena mašina {$machine->naziv}");

        return response()->json($machine);
    }

    // ===================== SURFACE PROTECTIONS =====================

    public function surfaceProtections(): JsonResponse
    {
        return response()->json(CncSurfaceProtection::orderBy('naziv')->get());
    }

    public function storeSurfaceProtection(Request $request): JsonResponse
    {
        $data = $request->validate([
            'naziv' => 'required|string|max:255',
            'cijena' => 'numeric',
            'tip_cijene' => 'in:komad,kg,fiksno',
            'aktivno' => 'boolean',
            'napomena' => 'nullable|string',
        ]);

        $item = CncSurfaceProtection::create($data);
        $this->logActivity($request, 'create', 'surfaceProtection', $item->id, $item->naziv, "Dodana površinska zaštita {$item->naziv}");

        return response()->json($item, 201);
    }

    public function updateSurfaceProtection(Request $request, $id): JsonResponse
    {
        $item = CncSurfaceProtection::findOrFail($id);
        $data = $request->validate([
            'naziv' => 'sometimes|string|max:255',
            'cijena' => 'numeric',
            'tip_cijene' => 'in:komad,kg,fiksno',
            'aktivno' => 'boolean',
            'napomena' => 'nullable|string',
        ]);

        $item->update($data);
        $this->logActivity($request, 'update', 'surfaceProtection', $item->id, $item->naziv, "Izmijenjena površinska zaštita {$item->naziv}");

        return response()->json($item);
    }

    public function deleteSurfaceProtection(Request $request, $id): JsonResponse
    {
        $item = CncSurfaceProtection::findOrFail($id);
        $this->logActivity($request, 'delete', 'surfaceProtection', $item->id, $item->naziv, "Obrisana površinska zaštita {$item->naziv}");
        $item->delete();

        return response()->json(['ok' => true]);
    }

    // ===================== HEAT TREATMENTS =====================

    public function heatTreatments(): JsonResponse
    {
        return response()->json(CncHeatTreatment::orderBy('naziv')->get());
    }

    public function storeHeatTreatment(Request $request): JsonResponse
    {
        $data = $request->validate([
            'naziv' => 'required|string|max:255',
            'cijena' => 'numeric',
            'tip_cijene' => 'in:komad,kg,fiksno',
            'aktivno' => 'boolean',
            'napomena' => 'nullable|string',
        ]);

        $item = CncHeatTreatment::create($data);
        $this->logActivity($request, 'create', 'heatTreatment', $item->id, $item->naziv, "Dodana termička obrada {$item->naziv}");

        return response()->json($item, 201);
    }

    public function updateHeatTreatment(Request $request, $id): JsonResponse
    {
        $item = CncHeatTreatment::findOrFail($id);
        $data = $request->validate([
            'naziv' => 'sometimes|string|max:255',
            'cijena' => 'numeric',
            'tip_cijene' => 'in:komad,kg,fiksno',
            'aktivno' => 'boolean',
            'napomena' => 'nullable|string',
        ]);

        $item->update($data);
        $this->logActivity($request, 'update', 'heatTreatment', $item->id, $item->naziv, "Izmijenjena termička obrada {$item->naziv}");

        return response()->json($item);
    }

    public function deleteHeatTreatment(Request $request, $id): JsonResponse
    {
        $item = CncHeatTreatment::findOrFail($id);
        $this->logActivity($request, 'delete', 'heatTreatment', $item->id, $item->naziv, "Obrisana termička obrada {$item->naziv}");
        $item->delete();

        return response()->json(['ok' => true]);
    }

    // ===================== OTHER OPERATIONS =====================

    public function otherOperations(): JsonResponse
    {
        return response()->json(CncOtherOperation::orderBy('naziv')->get());
    }

    public function storeOtherOperation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'naziv' => 'required|string|max:255',
            'cijena' => 'numeric',
            'tip_cijene' => 'in:komad,kg,fiksno',
            'aktivno' => 'boolean',
            'napomena' => 'nullable|string',
        ]);

        $item = CncOtherOperation::create($data);
        $this->logActivity($request, 'create', 'otherOperation', $item->id, $item->naziv, "Dodan ostali zahvat {$item->naziv}");

        return response()->json($item, 201);
    }

    public function updateOtherOperation(Request $request, $id): JsonResponse
    {
        $item = CncOtherOperation::findOrFail($id);
        $data = $request->validate([
            'naziv' => 'sometimes|string|max:255',
            'cijena' => 'numeric',
            'tip_cijene' => 'in:komad,kg,fiksno',
            'aktivno' => 'boolean',
            'napomena' => 'nullable|string',
        ]);

        $item->update($data);
        $this->logActivity($request, 'update', 'otherOperation', $item->id, $item->naziv, "Izmijenjen ostali zahvat {$item->naziv}");

        return response()->json($item);
    }

    public function deleteOtherOperation(Request $request, $id): JsonResponse
    {
        $item = CncOtherOperation::findOrFail($id);
        $this->logActivity($request, 'delete', 'otherOperation', $item->id, $item->naziv, "Obrisan ostali zahvat {$item->naziv}");
        $item->delete();

        return response()->json(['ok' => true]);
    }

    // ===================== POSITIONS =====================

    public function positions(): JsonResponse
    {
        return response()->json(CncPosition::orderBy('sifra')->get());
    }

    public function storePosition(Request $request): JsonResponse
    {
        $data = $request->all();
        $position = CncPosition::create($data);
        $this->logActivity($request, 'create', 'position', $position->id, $position->sifra ?? $position->naziv ?? '', "Kreirana pozicija {$position->sifra}");

        return response()->json($position, 201);
    }

    public function showPosition($id): JsonResponse
    {
        return response()->json(CncPosition::findOrFail($id));
    }

    public function findPositionByCode(Request $request): JsonResponse
    {
        $sifra = $request->query('sifra');
        $position = CncPosition::where('sifra', $sifra)->first();
        return response()->json($position);
    }

    public function updatePosition(Request $request, $id): JsonResponse
    {
        $position = CncPosition::findOrFail($id);
        $position->update($request->all());
        $this->logActivity($request, 'update', 'position', $position->id, $position->sifra ?? '', "Izmijenjena pozicija {$position->sifra}");

        return response()->json($position);
    }

    public function deletePosition(Request $request, $id): JsonResponse
    {
        $position = CncPosition::findOrFail($id);
        $this->logActivity($request, 'delete', 'position', $position->id, $position->sifra ?? '', "Obrisana pozicija {$position->sifra}");
        $position->delete();

        return response()->json(['ok' => true]);
    }

    // ===================== QUOTES =====================

    public function quotes(): JsonResponse
    {
        $quotes = CncQuote::with(['positions', 'contactHistory'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json($quotes);
    }

    public function showQuote($id): JsonResponse
    {
        $quote = CncQuote::with(['positions', 'contactHistory'])->findOrFail($id);
        return response()->json($quote);
    }

    public function storeQuote(Request $request): JsonResponse
    {
        $data = $request->except('positions', 'historija_kontakata');
        $quote = CncQuote::create($data);

        if ($request->has('positions')) {
            foreach ($request->input('positions', []) as $pos) {
                $quote->positions()->create($pos);
            }
        }

        $quote->load('positions', 'contactHistory');
        $this->logActivity($request, 'create', 'quote', $quote->id, $quote->broj_ponude, "Kreirana ponuda {$quote->broj_ponude}");

        return response()->json($quote, 201);
    }

    public function updateQuote(Request $request, $id): JsonResponse
    {
        $quote = CncQuote::findOrFail($id);
        $data = $request->except('positions', 'historija_kontakata');
        $quote->update($data);

        if ($request->has('positions')) {
            $quote->positions()->delete();
            foreach ($request->input('positions', []) as $pos) {
                $quote->positions()->create($pos);
            }
        }

        $quote->load('positions', 'contactHistory');
        $this->logActivity($request, 'update', 'quote', $quote->id, $quote->broj_ponude, "Izmijenjena ponuda {$quote->broj_ponude}");

        return response()->json($quote);
    }

    public function deleteQuote(Request $request, $id): JsonResponse
    {
        $quote = CncQuote::findOrFail($id);
        $this->logActivity($request, 'delete', 'quote', $quote->id, $quote->broj_ponude, "Obrisana ponuda {$quote->broj_ponude}");
        $quote->delete();

        return response()->json(['ok' => true]);
    }

    public function addContactHistory(Request $request, $quoteId): JsonResponse
    {
        $quote = CncQuote::findOrFail($quoteId);
        $data = $request->validate([
            'datum' => 'required|date',
            'kanal' => 'in:email,telefon,sastanak,drugo',
            'kontakt_osoba' => 'nullable|string|max:255',
            'ishod' => 'nullable|string',
            'napomena' => 'nullable|string',
            'evidentirao' => 'nullable|string|max:255',
        ]);

        $data['evidentirano_u'] = now();
        $entry = $quote->contactHistory()->create($data);

        return response()->json($entry, 201);
    }

    // ===================== SETTINGS =====================

    public function settings(): JsonResponse
    {
        $settings = CncSetting::all()->pluck('value', 'key')->toArray();

        return response()->json([
            'transportPricePerKg' => (float) ($settings['transportPricePerKg'] ?? 0),
            'ensatPricePerPiece' => (float) ($settings['ensatPricePerPiece'] ?? 0),
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        foreach (['transportPricePerKg', 'ensatPricePerPiece'] as $key) {
            if ($request->has($key)) {
                CncSetting::updateOrCreate(
                    ['key' => $key],
                    ['value' => (string) $request->input($key)],
                );
            }
        }

        $this->logActivity($request, 'update', 'settings', 'general', 'Opće postavke', 'Izmijenjene opće kalkulacijske postavke');

        return $this->settings();
    }

    public function getSetting(string $key): JsonResponse
    {
        $setting = CncSetting::where('key', $key)->first();

        if (!$setting) {
            return response()->json(null, 404);
        }

        $decoded = json_decode($setting->value, true);

        return response()->json($decoded !== null ? $decoded : $setting->value);
    }

    public function putSetting(Request $request, string $key): JsonResponse
    {
        $value = $request->input('value');

        CncSetting::updateOrCreate(
            ['key' => $key],
            ['value' => is_array($value) || is_object($value) ? json_encode($value) : (string) $value],
        );

        return response()->json(['ok' => true]);
    }

    // ===================== ACTIVITY LOGS =====================

    public function activityLogs(Request $request): JsonResponse
    {
        $limit = min(500, max(1, (int) $request->query('limit', 200)));

        $logs = CncActivityLog::orderByDesc('created_at')
            ->limit($limit)
            ->get();

        return response()->json($logs);
    }

    // ===================== USERS (admin) =====================

    public function users(): JsonResponse
    {
        $users = CncUser::orderByRaw("FIELD(role, 'admin', 'kalkulant')")
            ->orderByDesc('aktivan')
            ->orderBy('ime_prezime')
            ->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'imePrezime' => $u->ime_prezime,
                'email' => $u->email,
                'role' => $u->role,
                'aktivan' => $u->aktivan,
                'permissions' => $u->permissions ?? [],
            ]);

        return response()->json($users);
    }

    public function storeUser(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ime_prezime' => 'required|string|min:2|max:100',
            'email' => 'required|email|max:254|unique:mysql.cnc_users,email',
            'password' => 'required|string|min:8|max:128',
            'permissions' => 'nullable|array',
        ]);

        $user = CncUser::create([
            'ime_prezime' => trim($data['ime_prezime']),
            'email' => strtolower(trim($data['email'])),
            'password' => Hash::make($data['password']),
            'role' => 'kalkulant',
            'aktivan' => true,
            'permissions' => $data['permissions'] ?? [],
        ]);

        $this->logActivity($request, 'create', 'user', $user->id, $user->ime_prezime, "Kreiran korisnik {$user->ime_prezime} ({$user->email})");

        return response()->json(['id' => $user->id], 201);
    }

    public function updateUser(Request $request, $id): JsonResponse
    {
        $user = CncUser::findOrFail($id);
        $data = $request->validate([
            'ime_prezime' => 'sometimes|string|min:2|max:100',
            'aktivan' => 'boolean',
            'permissions' => 'nullable|array',
        ]);

        $user->update($data);

        $action = isset($data['aktivan']) ? ($data['aktivan'] ? 'activate' : 'deactivate') : 'update';
        $this->logActivity($request, $action, 'user', $user->id, $user->ime_prezime, "Izmijenjen korisnik {$user->ime_prezime}");

        return response()->json(['ok' => true]);
    }

    public function resetUserPassword(Request $request, $id): JsonResponse
    {
        $user = CncUser::findOrFail($id);
        $data = $request->validate([
            'password' => 'required|string|min:8|max:128',
        ]);

        $user->update(['password' => Hash::make($data['password'])]);
        $this->logActivity($request, 'reset_password', 'user', $user->id, $user->ime_prezime, "Reset lozinke za {$user->email}");

        return response()->json(['ok' => true]);
    }

    // ===================== QUOTE DRAFTS =====================

    public function quoteDrafts(Request $request): JsonResponse
    {
        $user = $this->cncUser($request);
        $drafts = CncQuoteDraft::where('owner_id', $user->id)
            ->orderByDesc('updated_at_ms')
            ->get();

        return response()->json($drafts);
    }

    public function showQuoteDraft(Request $request, $draftId): JsonResponse
    {
        $user = $this->cncUser($request);
        $draft = CncQuoteDraft::where('owner_id', $user->id)
            ->where('draft_id', $draftId)
            ->first();

        return response()->json($draft);
    }

    public function saveQuoteDraft(Request $request): JsonResponse
    {
        $user = $this->cncUser($request);
        $data = $request->validate([
            'draft_id' => 'required|string',
            'label' => 'nullable|string|max:160',
            'position_count' => 'integer|min:0|max:500',
            'created_at_ms' => 'integer',
            'updated_at_ms' => 'integer',
            'state' => 'required|array',
        ]);

        CncQuoteDraft::updateOrCreate(
            ['owner_id' => $user->id, 'draft_id' => $data['draft_id']],
            [
                'label' => $data['label'] ?? 'Nedovršena ponuda',
                'position_count' => $data['position_count'] ?? 0,
                'created_at_ms' => $data['created_at_ms'] ?? now()->getTimestampMs(),
                'updated_at_ms' => $data['updated_at_ms'] ?? now()->getTimestampMs(),
                'state' => $data['state'],
            ],
        );

        return response()->json(['ok' => true]);
    }

    public function deleteQuoteDraft(Request $request, $draftId): JsonResponse
    {
        $user = $this->cncUser($request);
        CncQuoteDraft::where('owner_id', $user->id)
            ->where('draft_id', $draftId)
            ->delete();

        return response()->json(['ok' => true]);
    }
}
