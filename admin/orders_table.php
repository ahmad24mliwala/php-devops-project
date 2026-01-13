<?php
require '../includes/db.php';
require '../includes/functions.php';

$valid_statuses = ['pending', 'processing', 'shipped', 'completed', 'cancelled'];

$filter = $_GET['filter'] ?? 'all';
$status_filter = $_GET['status_filter'] ?? '';
$search = $_GET['search'] ?? '';

$where = [];
if ($filter == 'today') $where[] = "DATE(created_at)=CURDATE()";
elseif ($filter == 'week') $where[] = "WEEK(created_at)=WEEK(CURDATE())";
elseif ($filter == 'month') $where[] = "MONTH(created_at)=MONTH(CURDATE())";

if ($status_filter && in_array($status_filter, $valid_statuses))
    $where[] = "status='$status_filter'";

if ($search) {
    $safeSearch = "%".$search."%";
    $where[] = "(id LIKE :search OR name LIKE :search OR email LIKE :search)";
}

$where_sql = $where ? "WHERE " . implode(" AND ", $where) : '';

$sql = "SELECT * FROM orders $where_sql ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);
if ($search) $stmt->bindValue(':search', $safeSearch);
$stmt->execute();
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

function getStatusColor($status) {
    return match($status) {
        'pending' => '#ffc107',
        'processing' => '#17a2b8',
        'shipped' => '#6c757d',
        'completed' => '#28a745',
        'cancelled' => '#dc3545',
        default => '#6c757d'
    };
}

if ($orders):
    foreach ($orders as $o): ?>
        <tr>
            <td><?= htmlspecialchars($o['id']) ?></td>
            <td><?= htmlspecialchars($o['name']) ?><br><small><?= htmlspecialchars($o['email']) ?></small></td>
            <td><?= htmlspecialchars($o['phone']) ?></td>
            <td><?= htmlspecialchars($o['shipping_address']) ?></td>
            <td>₹<?= number_format($o['total_amount'], 2) ?></td>
            <td>
                <select class="form-select form-select-sm status-select"
                        data-id="<?= $o['id'] ?>"
                        style="background-color: <?= getStatusColor($o['status']) ?>; color:white;">
                    <?php foreach ($valid_statuses as $s): ?>
                        <option value="<?= $s ?>" <?= ($o['status']==$s)?'selected':'' ?>>
                            <?= ucfirst($s) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td><?= htmlspecialchars($o['created_at']) ?></td>
            <td><a href="../public/invoice.php?order_id=<?= $o['id'] ?>" target="_blank" class="btn btn-sm btn-success mb-1">Invoice</a></td>
        </tr>
    <?php endforeach;
else: ?>
    <tr><td colspan="8" class="text-center text-muted">No orders found.</td></tr>
<?php endif; ?>
