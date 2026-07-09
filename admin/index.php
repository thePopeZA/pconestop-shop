<?php
require_once __DIR__ . '/_bootstrap.php';
require_admin();

$pdo = db();
$totalProducts = (int)$pdo->query('SELECT COUNT(*) FROM products WHERE active=1')->fetchColumn();
$inStock       = (int)$pdo->query('SELECT COUNT(*) FROM products WHERE active=1 AND stock_qty>0')->fetchColumn();
$outStock      = $totalProducts - $inStock;
$totalCats     = (int)$pdo->query('SELECT COUNT(*) FROM categories WHERE product_count>0')->fetchColumn();

$orders30      = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE created_at > NOW() - INTERVAL 30 DAY")->fetchColumn();
$paidOrders    = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE payment_status='paid'")->fetchColumn();
$revenue       = (float)$pdo->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE payment_status='paid'")->fetchColumn();
$pendingOrders = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE payment_status='pending'")->fetchColumn();

$lastFeed = $pdo->query("SELECT * FROM feed_log ORDER BY id DESC LIMIT 1")->fetch();
$recentOrders = $pdo->query("SELECT * FROM orders ORDER BY id DESC LIMIT 8")->fetchAll();
$lowStock = $pdo->query("SELECT sku, name, stock_qty FROM products WHERE active=1 AND stock_qty>0 AND stock_qty<=3 ORDER BY stock_qty ASC LIMIT 8")->fetchAll();

$adminTitle = 'Dashboard';
$adminNav = 'dashboard';
include __DIR__ . '/_header.php';
?>
<div class="cards">
    <div class="stat"><div class="n"><?= number_format($totalProducts) ?></div><div class="l">Active products</div><div class="sub"><span class="badge b-green"><?= number_format($inStock) ?> in stock</span> <span class="badge b-red"><?= number_format($outStock) ?> out</span></div></div>
    <div class="stat"><div class="n"><?= money($revenue) ?></div><div class="l">Revenue (paid)</div><div class="sub"><?= $paidOrders ?> paid orders</div></div>
    <div class="stat"><div class="n"><?= number_format($orders30) ?></div><div class="l">Orders (30 days)</div><div class="sub"><?php if ($pendingOrders): ?><span class="badge b-amber"><?= $pendingOrders ?> pending</span><?php else: ?><span class="badge b-grey">none pending</span><?php endif; ?></div></div>
    <div class="stat"><div class="n"><?= number_format($totalCats) ?></div><div class="l">Categories</div><div class="sub">Auto-built from feed</div></div>
</div>

<div style="display:grid;grid-template-columns:1.4fr 1fr;gap:22px" class="dash-cols">
    <div class="panel">
        <div class="toolbar" style="margin-bottom:12px"><h2 style="margin:0">Recent orders</h2><a class="btn btn-sm btn-ghost" href="<?= e(admin_url('orders.php')) ?>">View all</a></div>
        <?php if (!$recentOrders): ?>
            <p class="muted">No orders yet.</p>
        <?php else: ?>
        <table class="grid">
            <thead><tr><th>Order</th><th>Customer</th><th>Total</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($recentOrders as $o):
                $cls = ['paid'=>'b-green','pending'=>'b-amber','failed'=>'b-red','cancelled'=>'b-grey','refunded'=>'b-blue'][$o['payment_status']] ?? 'b-grey'; ?>
                <tr>
                    <td><a href="<?= e(admin_url('order.php?id='.$o['id'])) ?>"><?= e($o['order_number']) ?></a><br><span class="muted" style="font-size:.78rem"><?= e(date('d M H:i', strtotime($o['created_at']))) ?></span></td>
                    <td><?= e($o['customer_name']) ?></td>
                    <td><?= money((float)$o['total']) ?></td>
                    <td><span class="badge <?= $cls ?>"><?= e(ucfirst($o['payment_status'])) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <div>
        <div class="panel">
            <h2>Feed sync</h2>
            <?php if ($lastFeed): ?>
                <p style="margin:0">Last run: <strong><?= e(date('d M Y H:i', strtotime($lastFeed['started_at']))) ?></strong></p>
                <p style="margin:6px 0">
                    <span class="badge <?= $lastFeed['status']==='success'?'b-green':($lastFeed['status']==='failed'?'b-red':'b-amber') ?>"><?= e(ucfirst($lastFeed['status'])) ?></span>
                    <span class="muted" style="font-size:.82rem"><?= (int)$lastFeed['products_seen'] ?> seen · <?= (int)$lastFeed['products_added'] ?> new · <?= (int)$lastFeed['products_updated'] ?> updated</span>
                </p>
            <?php else: ?>
                <p class="muted">No feed imports yet.</p>
            <?php endif; ?>
            <a class="btn btn-sm" href="<?= e(admin_url('feeds.php')) ?>">Manage feeds →</a>
        </div>

        <div class="panel">
            <h2>Low stock alerts</h2>
            <?php if (!$lowStock): ?>
                <p class="muted">Nothing running low.</p>
            <?php else: ?>
                <table class="grid">
                    <tbody>
                    <?php foreach ($lowStock as $p): ?>
                        <tr><td style="font-size:.85rem"><?= e($p['name']) ?></td><td><span class="badge b-amber"><?= (int)$p['stock_qty'] ?> left</span></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include __DIR__ . '/_footer.php'; ?>
