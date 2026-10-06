<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class NfcCardController extends Controller
{
    public function index(Request $request)
    {
        $user = $this->authorizedUser($request);

        return view('content.apps.nfc-card.app-nfc-card', [
            'pageConfigs' => ['pageHeader' => false],
            'nfcUser' => $user,
            'nfcCard' => $this->cardPayload($user),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $this->authorizedUser($request);
        $uid = User::normalizeNfcCardUid($request->input('uid'));

        if (strlen($uid) < 8 || strlen($uid) > 64 || strlen($uid) % 2 !== 0) {
            return response()->json([
                'message' => 'Očitani broj kartice nije ispravan. Pokušajte ponovo.',
            ], 422);
        }

        $takenBy = User::query()
            ->where('nfc_card_uid', $uid)
            ->where('id', '!=', $user->id)
            ->exists();

        if ($takenBy) {
            return response()->json([
                'message' => 'Ova kartica je već dodijeljena drugom korisniku.',
            ], 409);
        }

        $user->nfc_card_uid = $uid;
        $user->nfc_card_linked_at = now();
        $user->save();

        return response()->json([
            'message' => 'Kartica je uspješno povezana.',
            'card' => $this->cardPayload($user),
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $user = $this->authorizedUser($request);

        $user->nfc_card_uid = null;
        $user->nfc_card_linked_at = null;
        $user->save();

        return response()->json([
            'message' => 'Kartica je uklonjena.',
            'card' => $this->cardPayload($user),
        ]);
    }

    private function authorizedUser(Request $request): User
    {
        $user = $request->user();

        if (!$user instanceof User || !$user->isAdmin()) {
            abort(403);
        }

        $this->ensureCardColumns();

        return $user;
    }

    /**
     * Redeploy does not run migrations, so the columns from
     * 2026_10_03_000001_add_nfc_card_to_users_table are created on first use.
     * Always on the local MySQL connection, never on Pantheon.
     */
    private function ensureCardColumns(): void
    {
        $schema = Schema::connection('mysql');

        if ($schema->hasColumn('users', 'nfc_card_uid')) {
            return;
        }

        $schema->table('users', function (Blueprint $table) {
            $table->string('nfc_card_uid', 64)->nullable()->unique()->after('role');
            $table->timestamp('nfc_card_linked_at')->nullable()->after('nfc_card_uid');
        });
    }

    private function cardPayload(User $user): ?array
    {
        $uid = (string) ($user->nfc_card_uid ?? '');

        if ($uid === '') {
            return null;
        }

        return [
            'uid' => $uid,
            'uid_display' => strtoupper(implode(' ', str_split($uid, 2))),
            'linked_at' => optional($user->nfc_card_linked_at)->format('d.m.Y. H:i'),
        ];
    }
}
