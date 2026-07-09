<?php
require_once __DIR__ . '/_bootstrap.php';
require_admin();

$filter = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;
$offset = ($page - 1) * $perPage;

$where = '1=1';
$params = [];
if (in_array($filter, ['pending','paid','failed','cancelled','refunded'], true)) {
    $where = 'payment_status = ?';
    $params[] = $filter;
}
$total = (int)(function () use ($where, $params) {
    $s = db()->prepare("SELECT COUNT(*) FROM orders WHERE $where");
    $s->execute($params);
    return $s->fetchColumn();
})();
$pages = (int)ceil($total / $perPage);

$stmt = db()->prepare("SELECT * FROM orders WHERE $where ORDER BY id DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$orders = $stmt->fetchAll();

$adminTitle = 'Orders';
$adminNav = 'orders';
include __DIR__ . '/_header.php';
?>
<div class="toolbar">
    <div>
        <a class="btn btn-sm <?= $filter===''?'':'btn-ghost' ?>" href="<?= e(admin_url('orders.php')) ?>">All</a>
        <a class="btn btn-sm <?= $filter==='paid'?'':'btn-ghost' ?>" href="<?= e(admin_url('orders.php?status=paid')) ?>">Paid</a>
        <a class="btn btn-sm <?= $filter==='pending'?'':'btn-ghost' ?>" href="<?= e(admin_url('orders.php?status=pending')) ?>">Pending</a>
        <a class="btn btn-sm <?= $filter==='cancelled'?'':'btn-ghost' ?>" href="<?= e(admin_url('orders.php?status=cancelled')) ?>">Cancelled</a>
    </div>
    <span class="muted"><?= number_format($total) ?> orders</span>
</div>

<?php if (!$orders): ?>
    <div class="panel"><p class="muted" style="margin:0">No orders here yet.</p></div>
<?php else: ?>
<table class="grid">
    <thead><tr><th>Order</th><th>Date</th><th>Customer</th><th>Items</th><th>Total</th><th>Payment</th><th>Fulfilment</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($orders as $o):
        $pc = ['paid'=>'b-green','pending'=>'b-amber','failed'=>'b-red','cancelled'=>'b-grey','refunded'=>'b-blue'][$o['payment_status']] ?? 'b-grey';
        $itemCount = (int)db()->query('SELECT COALESCE(SUM(quantity),0) FROM order_items WHERE order_id='.(int)$o['id'])->fetchColumn(); ?>
        <tr>
            <td><a href="<?= e(admin_url('order.php?id='.$o['id'])) ?>"><strong><?= e($o['order_number']) ?></strong></a></td>
            <td class="muted" style="font-size:.82rem"><?= e(date('d M Y H:i', strtotime($o['created_at']))) ?></td>
            <td><?= e($o['customer_name']) ?><br><span class="muted" style="font-size:.78rem"><?= e($o['email']) ?></span></td>
            <td><?= $itemCount ?></td>
            <td style="font-weight:700"><?= money((float)$o['total']) ?></td>
            <td><span class="badge <?= $pc ?>"><?= e(ucfirst($o['payment_status'])) ?></span></td>
            <td><span class="badge b-grey"><?= e(ucfirst($o['status'])) ?></span></td>
            <td><a class="btn btn-sm btn-ghost" href="<?= e(admin_url('order.php?id='.$o['id'])) ?>">View</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php if ($pages > 1): ?>
<div class="pagination">
    <?php for ($i = max(1,$page-3); $i <= min($pages,$page+3); $i++): ?>
        <a class="<?= $i===$page?'active':'' ?>" href="<?= e(admin_url('orders.php?'.http_build_query(array_filter(['status'=>$filter,'page'=>$i])))) ?>"><?= $i ?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>
<?php include __DIR__ . '/_footer.php'; ?>
