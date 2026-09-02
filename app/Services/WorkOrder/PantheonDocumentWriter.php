<?php

namespace App\Services\WorkOrder;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RuntimeException;

class PantheonDocumentWriter
{
    public function subjectQId(ConnectionInterface $connection, string $subject, ?int $candidate = null): int
    {
        if (($candidate ?? 0) > 0) {
            return (int) $candidate;
        }

        $qid = $connection->table('dbo.tHE_SetSubj')
            ->whereRaw("LTRIM(RTRIM(ISNULL(acSubject, ''))) = ?", [trim($subject)])
            ->value('anQId');

        if (!is_numeric((string) $qid) || (int) $qid < 1) {
            throw new RuntimeException('Pantheon subjekt nije pronađen: ' . ($subject !== '' ? $subject : '[prazno]'));
        }

        return (int) $qid;
    }

    public function insertHeader(
        ConnectionInterface $connection,
        array $number,
        array $workOrder,
        array $context,
        Carbon $now,
        int $userId
    ): int {
        $documentDate = $now->copy()->startOfDay();
        $orderDate = $this->date($workOrder['adLnkDate'] ?? null, $documentDate);
        $workOrderDate = $this->date($workOrder['adDate'] ?? null, $documentDate);
        $value = (string) ($context['total_value'] ?? '0');
        $receiver = trim((string) ($context['receiver'] ?? ''));
        $issuer = trim((string) ($context['issuer'] ?? ''));
        $dept = trim((string) ($context['department'] ?? ''));
        $receiverQId = $this->subjectQId($connection, $receiver, $context['receiver_qid'] ?? null);
        $issuerQId = $this->subjectQId($connection, $issuer, $context['issuer_qid'] ?? null);
        $compatibility = $this->compatibilityFields($connection, (string) $number['type'], $receiver, $documentDate);
        // Department is not a receiver/warehouse compatibility field. In
        // particular, a 2005 receiver is a warehouse subject; copying a prior
        // header's acDept/anDeptQId can make Pantheon resolve a warehouse (for
        // example "Skladište sirovina") as "Prijemni odjel".
        $deptQId = $this->resolveDepartmentQId(
            $connection,
            $dept,
            $context['department_qid'] ?? null
        );
        $person3 = trim((string) ($context['person3'] ?? $receiver));
        $person3QId = $this->subjectQId($connection, $person3, $context['person3_qid'] ?? null);
        $workOrderNumber = $this->formatNumber((string) ($workOrder['acKeyView'] ?? $workOrder['acKey'] ?? ''));
        $orderReference = trim((string) ($workOrder['acLnkKey'] ?? ''));
        $position = (int) ($workOrder['anLnkNo'] ?? 0);
        if ($orderReference !== '' && $position > 0) {
            $orderReference .= ' - ' . $position;
        }

        $payload = [
            'acKey' => $number['key'],
            'acDocType' => $number['type'],
            'adDate' => $documentDate,
            'acReceiver' => $this->limit($receiver, 30),
            'acIssuer' => $this->limit($issuer, 30),
            'acReceiverStock' => (string) ($context['receiver_stock'] ?? 'N'),
            'acIssuerStock' => (string) ($context['issuer_stock'] ?? 'N'),
            'acPrsn3' => $this->limit($person3, 30),
            'acDoc1' => $this->limit($orderReference, 50),
            'adDateDoc1' => $orderDate,
            'acDoc2' => $this->limit($workOrderNumber, 50),
            'adDateDoc2' => $workOrderDate,
            'acWayOfSale' => (string) ($context['way_of_sale'] ?? 'I'),
            'acPriceRate' => '1',
            'acPayMethod' => (string) ($compatibility['acPayMethod'] ?? ''),
            'adDateInv' => $documentDate,
            'anDaysForPayment' => (int) ($compatibility['anDaysForPayment'] ?? 0),
            'adDateDue' => (string) $number['type'] === '6100'
                ? $orderDate
                : ($compatibility['adDateDue'] ?? $documentDate),
            'anValue' => $value,
            'anVAT' => '0',
            'anDiscount' => '0',
            'anForPay' => $value,
            'anClerk' => $userId,
            'acVerifiedPrices' => 'F',
            'acCurrency' => (string) config('work_order_closing.currency', 'KM'),
            'anCurrValue' => $value,
            'acDept' => $this->limit($dept, 30),
            'acPosted' => 'F',
            'acInternalNote' => 'eNalog.app work order closing',
            'anVATIn' => '0',
            'adDateVAT' => $documentDate,
            'anVATBase' => '0',
            'acCreatFromWO' => 'T',
            'acCreatePayOrd' => 'T',
            'adTimeIns' => $now,
            'anUserIns' => $userId,
            'adTimeChg' => $now,
            'anUserChg' => $userId,
            'acRoundVATOnDoc' => 'F',
            'acVerifyStatus' => 'N',
            'acFiscStatus' => '0',
            'acRetailSale' => 'F',
            'acInsertedFrom' => 'D',
            'anFXRate' => '1',
            'anReceiverQId' => $receiverQId,
            'anIssuerQId' => $issuerQId,
            'anPrsn3QId' => $person3QId,
            'anDeptQId' => $deptQId,
            'anCostDrvOutQId' => 1,
        ];

        if (($compatibility['acISOCountry'] ?? '') !== '') {
            $payload['acISOCountry'] = $compatibility['acISOCountry'];
        }

        $connection->table('dbo.tHE_Move')->insert($payload);
        $moveQId = $connection->table('dbo.tHE_Move')->where('acKey', $number['key'])->value('anQId');
        if (!is_numeric((string) $moveQId) || (int) $moveQId < 1) {
            throw new RuntimeException('Pantheon QId dokumenta nije kreiran.');
        }

        $connection->table('dbo.tHE_MoveFXRate')->insert([
            'acKey' => $number['key'],
            'acCurrency1' => (string) config('work_order_closing.currency', 'KM'),
            'acBank' => 'S',
            'anValue' => 0,
            'anForPay' => 0,
            'anVAT' => 0,
            'adTimeIns' => $now,
            'anUserIns' => $userId,
            'adTimeChg' => $now,
            'anUserChg' => $userId,
            'anFXRate' => 1,
            'anMoveQId' => (int) $moveQId,
        ]);

        return (int) $moveQId;
    }

    public function linkWorkOrder(ConnectionInterface $connection, array $number, int $moveQId, string $workOrderKey, string $type, Carbon $now, int $userId): void
    {
        $connection->table('dbo.tHF_LinkMoveWOEx')->insert([
            'acKey' => $number['key'], 'anNo' => 0, 'acLnkKey' => $workOrderKey,
            'anLnkNo' => 0, 'acType' => $type, 'acTypeA' => '', 'acTypeB' => '',
            'anFieldNA' => 0, 'anFieldNB' => 0, 'anUserId' => 0,
            'anUserChg' => $userId, 'adTimeIns' => $now, 'adTimeChg' => $now,
            'anUserIns' => $userId, 'anNoOperation' => 0, 'acExternalKey' => '',
            'anMoveQId' => $moveQId,
        ]);
    }

    public function insertItem(ConnectionInterface $connection, array $payload): int
    {
        $connection->table('dbo.tHE_MoveItem')->insert($payload);
        $qid = $connection->table('dbo.tHE_MoveItem')
            ->where('acKey', $payload['acKey'])
            ->where('anNo', $payload['anNo'])
            ->value('anQId');

        if (!is_numeric((string) $qid) || (int) $qid < 1) {
            throw new RuntimeException('Pantheon QId stavke dokumenta nije kreiran.');
        }

        return (int) $qid;
    }

    public function linkItem(
        ConnectionInterface $connection,
        array $number,
        int $lineNo,
        int $moveItemQId,
        int $workOrderItemQId,
        Carbon $now,
        int $userId,
        string $typeA = '  '
    ): void
    {
        $connection->table('dbo.tHF_LinkMoveItemWOExItem')->insert([
            'acKey' => $number['key'], 'anNo' => $lineNo, 'acType' => 'PP',
            'acTypeA' => $typeA, 'acTypeB' => '   ', 'anFieldNA' => 0, 'anFieldNB' => 0,
            'anUserId' => 0, 'anUserChg' => $userId, 'adTimeIns' => $now,
            'adTimeChg' => $now, 'anUserIns' => $userId, 'acResursID' => '',
            'acResursID2' => '', 'acExternalPositionKey' => '',
            'anMoveItemQId' => $moveItemQId, 'anWOExItemQid' => $workOrderItemQId,
        ]);
    }

    private function resolveDepartmentQId(ConnectionInterface $connection, string $department, mixed $candidate): int
    {
        if (is_numeric((string) $candidate) && (int) $candidate > 0) {
            return (int) $candidate;
        }

        if ($department !== '') {
            $qid = $connection->table('dbo.tHE_SetSubj')
                ->whereRaw("LTRIM(RTRIM(ISNULL(acSubject, ''))) = ?", [$department])
                ->value('anQId');
            if (is_numeric((string) $qid) && (int) $qid > 0) {
                return (int) $qid;
            }
        }

        // Pantheon's neutral/default department. It must never be inferred
        // from an issuer or receiver warehouse QId.
        return 1;
    }

    private function compatibilityFields(ConnectionInterface $connection, string $type, string $receiver, Carbon $date): array
    {
        // Isolated compatibility hook: replace with Pantheon business logic once the
        // partner-specific due-date/VAT routine is available.
        $row = $connection->table('dbo.tHE_Move')
            ->where('acDocType', $type)
            ->whereRaw("LTRIM(RTRIM(ISNULL(acReceiver, ''))) = ?", [$receiver])
            ->orderByDesc('adDate')
            ->orderByDesc('acKey')
            ->first(['acPayMethod', 'anDaysForPayment', 'acISOCountry', 'acDept', 'anDeptQId']);
        $days = max(0, (int) ($row->anDaysForPayment ?? 0));

        return [
            'acPayMethod' => trim((string) ($row->acPayMethod ?? '')),
            'anDaysForPayment' => $days,
            'adDateDue' => $date->copy()->addDays($days),
            'acISOCountry' => trim((string) ($row->acISOCountry ?? '')),
            'acDept' => trim((string) ($row->acDept ?? '')),
            'anDeptQId' => is_numeric((string) ($row->anDeptQId ?? null)) ? (int) $row->anDeptQId : null,
        ];
    }

    private function date(mixed $value, Carbon $fallback): Carbon
    {
        try {
            return $value ? Carbon::parse($value)->startOfDay() : $fallback->copy();
        } catch (\Throwable) {
            return $fallback->copy();
        }
    }

    private function formatNumber(string $value): string
    {
        $digits = preg_replace('/\D+/', '', trim($value));
        return is_string($digits) && strlen($digits) >= 12
            ? substr($digits, 0, 2) . '-' . substr($digits, 2, 4) . '-' . substr($digits, -6)
            : trim($value);
    }

    private function limit(string $value, int $length): string
    {
        return Str::substr(trim($value), 0, $length);
    }
}
