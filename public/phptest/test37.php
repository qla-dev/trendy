<?php
/** Read-only comparison: sales-order production view vs Pantheon RN production plan. */
require __DIR__ . '/_conn.php';

$schema = 'dbo';
$limit = max(1, min(100, (int) ($_GET['limit'] ?? 50)));
function rows($conn, string $sql, array $params = []): array {
    $statement = sqlsrv_query($conn, $sql, $params);
    if ($statement === false) throw new RuntimeException(print_r(sqlsrv_errors(), true));
    $result = [];
    while ($row = sqlsrv_fetch_array($statement, SQLSRV_FETCH_ASSOC)) $result[] = $row;
    return $result;
}
function h($value): string { return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8'); }
function date_value($value): string { return $value instanceof DateTimeInterface ? $value->format('d.m.Y H:i') : (string) $value; }

try {
    // Existing order-based plan: order positions and their direct RN reference.
    $orderRows = rows($conn, "SELECT TOP $limit
        ord.acKeyView AS order_no, oi.anNo AS order_position, oi.acIdent AS item_code, oi.acName AS item_name,
        oi.anQty AS ordered_qty, oi.adDeliveryDeadline AS delivery_date,
        wo.acKey AS rn_key, wo.acStatusMF AS rn_status
      FROM $schema.tHE_OrderItem oi
      JOIN $schema.tHE_Order ord ON ord.acKey=oi.acKey
      LEFT JOIN $schema.tHF_WOEx wo ON wo.acLnkKey=oi.acKey AND wo.anLnkNo=oi.anNo
      ORDER BY oi.adDeliveryDeadline DESC, oi.anQId DESC");

    // Pantheon production-plan objects: plan items linked to RN through the dedicated link table.
    $rnPlanRows = rows($conn, "SELECT TOP $limit
        p.acKeyView AS plan_no, p.acName AS plan_name, pi.anNo AS plan_position,
        pi.acIdent AS item_code, pi.acName AS item_name, pi.anQty AS planned_qty,
        pi.adSchedStartTime AS planned_start, pi.adSchedEndTime AS planned_end,
        wo.acKey AS rn_key, wo.acIdent AS rn_product, wo.acName AS rn_product_name,
        wo.acStatusMF AS rn_status, wo.adDate AS rn_date
      FROM $schema.tHF_Plan p
      JOIN $schema.tHF_PlanItem pi ON pi.acKey=p.acKey
      LEFT JOIN $schema.tHF_LinkPlanItemWOEx linkwo ON linkwo.acKey=pi.acKey AND linkwo.anNo=pi.anNo
      LEFT JOIN $schema.tHF_WOEx wo ON wo.acKey=linkwo.acLnkKey
      ORDER BY p.adDate DESC, pi.anNo ASC");
    $error = '';
} catch (Throwable $e) { $orderRows = $rnPlanRows = []; $error = $e->getMessage(); }
?>
<!doctype html><html lang="bs"><head><meta charset="utf-8"><title>Pantheon plan comparison</title><style>body{font:14px Arial;margin:24px;color:#20242b}table{border-collapse:collapse;width:100%;margin:10px 0 30px}th,td{border:1px solid #d7dce3;padding:7px;text-align:left;vertical-align:top}th{background:#eef3f8;white-space:nowrap}h2{margin-top:28px}.note{background:#fff8dc;padding:12px;border-left:4px solid #d9ad27}.error{white-space:pre-wrap;background:#fff0f0;padding:12px;color:#a11}</style></head><body>
<h1>Pantheon: poređenje izvora plana proizvodnje</h1><p class="note">Samo čitanje. Lijevo je postojeći plan po narudžbama; desno je Pantheonov plan (<code>tHF_Plan</code>/<code>tHF_PlanItem</code>) povezan sa RN preko <code>tHF_LinkPlanItemWOEx</code>.</p>
<?php if ($error): ?><pre class="error"><?=h($error)?></pre><?php endif; ?>
<h2>1. Plan po narudžbama</h2><table><tr><th>Narudžba</th><th>Poz.</th><th>Artikal</th><th>Naziv</th><th>Naručeno</th><th>Isporuka</th><th>Povezani RN</th><th>Status RN</th></tr><?php foreach($orderRows as $r): ?><tr><td><?=h($r['order_no'])?></td><td><?=h($r['order_position'])?></td><td><?=h($r['item_code'])?></td><td><?=h($r['item_name'])?></td><td><?=h($r['ordered_qty'])?></td><td><?=h(date_value($r['delivery_date']))?></td><td><?=h($r['rn_key'])?></td><td><?=h($r['rn_status'])?></td></tr><?php endforeach; ?></table>
<h2>2. Pantheon plan po radnim nalozima</h2><table><tr><th>Plan</th><th>Naziv plana</th><th>Poz.</th><th>Artikal plana</th><th>Naziv</th><th>Planirano</th><th>Plan početak</th><th>Plan kraj</th><th>RN</th><th>RN proizvod</th><th>Status RN</th><th>Datum RN</th></tr><?php foreach($rnPlanRows as $r): ?><tr><td><?=h($r['plan_no'])?></td><td><?=h($r['plan_name'])?></td><td><?=h($r['plan_position'])?></td><td><?=h($r['item_code'])?></td><td><?=h($r['item_name'])?></td><td><?=h($r['planned_qty'])?></td><td><?=h(date_value($r['planned_start']))?></td><td><?=h(date_value($r['planned_end']))?></td><td><?=h($r['rn_key'])?></td><td><?=h($r['rn_product'].' '.$r['rn_product_name'])?></td><td><?=h($r['rn_status'])?></td><td><?=h(date_value($r['rn_date']))?></td></tr><?php endforeach; ?></table>
</body></html>
