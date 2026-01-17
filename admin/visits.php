<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

require '../includes/db.php';
require '../includes/functions.php';
is_admin();

/* ==========================================================
   SAFE AUTO CLEANUP (ONCE PER DAY – 180 DAYS)
========================================================== */
$today = date('Y-m-d');
$last = $pdo->query("SELECT value FROM settings WHERE `key`='visits_cleanup'")->fetchColumn();

if ($last !== $today) {
    $pdo->exec("DELETE FROM visits WHERE visited_at < NOW() - INTERVAL 180 DAY");
    $stmt = $pdo->prepare("
        INSERT INTO settings (`key`,`value`)
        VALUES ('visits_cleanup',?)
        ON DUPLICATE KEY UPDATE value=VALUES(value)
    ");
    $stmt->execute([$today]);
}

/* ==========================================================
   FILTER NORMALIZATION
========================================================== */
function normalizeDate($v){
    return $v ? str_replace('T',' ',$v).':00' : '';
}

$from = normalizeDate($_GET['from'] ?? '');
$to   = normalizeDate($_GET['to'] ?? '');
$page_type = $_GET['page_type'] ?? '';
$device = $_GET['device'] ?? '';
$is_new = $_GET['is_new'] ?? '';

$where=[]; 
$params=[];
if($from){$where[]="visited_at>=?";$params[]=$from;}
if($to){$where[]="visited_at<=?";$params[]=$to;}
if($page_type){$where[]="page_type=?";$params[]=$page_type;}
if($device){$where[]="device_type=?";$params[]=$device;}
if($is_new!==''){$where[]="is_new=?";$params[]=(int)$is_new;}
$whereSQL=$where?"WHERE ".implode(" AND ",$where):"";

/* ==========================================================
   EXPORT CSV
========================================================== */
if(isset($_GET['export'])){
    header('Content-Type:text/csv');
    header('Content-Disposition:attachment; filename=visits.csv');
    $stmt=$pdo->prepare("
        SELECT ip_address,device_type,page_type,is_new,visited_at
        FROM visits $whereSQL ORDER BY visited_at DESC
    ");
    $stmt->execute($params);
    $out=fopen('php://output','w');
    fputcsv($out,['IP','Device','Page','User','Date']);
    while($r=$stmt->fetch(PDO::FETCH_ASSOC)){
        fputcsv($out,[
            $r['ip_address'],
            $r['device_type'],
            $r['page_type'],
            $r['is_new']?'New':'Returning',
            $r['visited_at']
        ]);
    }
    fclose($out); 
    exit;
}

/* ==========================================================
   METRIC HELPER
========================================================== */
function metric($pdo,$sql,$params=[]){
    $s=$pdo->prepare($sql);
    $s->execute($params);
    return (int)$s->fetchColumn();
}

/* ==========================================================
   KPI METRICS (BOT + DEVICE FIXED)
========================================================== */
$metrics=[
 'total'=>metric($pdo,"SELECT COUNT(DISTINCT visitor_id) FROM visits $whereSQL",$params),
 'today'=>metric($pdo,"SELECT COUNT(DISTINCT visitor_id) FROM visits WHERE DATE(visited_at)=CURDATE()"),
 'week'=>metric($pdo,"SELECT COUNT(DISTINCT visitor_id) FROM visits WHERE visited_at>=CURDATE()-INTERVAL 7 DAY"),
 'new'=>metric($pdo,"SELECT COUNT(DISTINCT visitor_id) FROM visits WHERE is_new=1"),
 'returning'=>metric($pdo,"SELECT COUNT(DISTINCT visitor_id) FROM visits WHERE is_new=0"),

 // ✅ FIXED: unique bot IPs only
 'bots'=>metric(
     $pdo,
     "SELECT COUNT(DISTINCT ip_address)
      FROM visits
      WHERE user_agent REGEXP 'bot|crawl|spider|slurp'"
 )
];

/* ==========================================================
   DEVICE COUNTS (FIXED)
========================================================== */
$totalDevice=$pdo->query("
    SELECT device_type, COUNT(DISTINCT visitor_id) c
    FROM visits
    GROUP BY device_type
")->fetchAll(PDO::FETCH_KEY_PAIR);

$todayDevice=$pdo->query("
    SELECT device_type, COUNT(DISTINCT visitor_id) c
    FROM visits
    WHERE DATE(visited_at)=CURDATE()
    GROUP BY device_type
")->fetchAll(PDO::FETCH_KEY_PAIR);

/* ==========================================================
   WEEKLY VISITS
========================================================== */
$weekly=$pdo->query("
    SELECT DATE(visited_at) d, COUNT(DISTINCT visitor_id) c
    FROM visits
    WHERE visited_at>=CURDATE()-INTERVAL 7 DAY
    GROUP BY d ORDER BY d
")->fetchAll(PDO::FETCH_ASSOC);

/* ==========================================================
   PRODUCT VISITS (CHART + TABLE)
========================================================== */
$productStats=$pdo->query("
    SELECT p.name, COUNT(*) v
    FROM visits v
    JOIN products p ON p.id=v.product_id
    WHERE v.page_type='product'
    GROUP BY p.id
    ORDER BY v DESC
")->fetchAll();

/* ==========================================================
   PAGE VISITS
========================================================== */
$pageStats=$pdo->query("
    SELECT page_type, COUNT(*) c
    FROM visits
    GROUP BY page_type
    ORDER BY c DESC
")->fetchAll(PDO::FETCH_KEY_PAIR);

/* ==========================================================
   IP PAGINATION
========================================================== */
$perPage=10;
$page=max(1,(int)($_GET['page']??1));
$offset=($page-1)*$perPage;

$totalIps=metric($pdo,"SELECT COUNT(DISTINCT ip_address) FROM visits");
$totalPages=ceil($totalIps/$perPage);

$ipVisits=$pdo->query("
    SELECT ip_address,
           MAX(device_type) device_type,
           MAX(page_type) page_type,
           COUNT(*) visits,
           MAX(visited_at) last_visit
    FROM visits
    GROUP BY ip_address
    ORDER BY last_visit DESC
    LIMIT $perPage OFFSET $offset
")->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Visits Analytics</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
body{background:#f4f7fb}
.kpi{border-radius:14px;padding:14px;color:#fff;text-align:center}
.card{border-radius:16px}
</style>
</head>
<body>
<?php include 'header.php'; ?>

<div class="container-fluid p-3">
<h4 class="fw-bold mb-3">📊 Website Analytics</h4>

<!-- KPI -->
<div class="row g-2">
<?php foreach([
['Total','#2563eb',$metrics['total']],
['Today','#06b6d4',$metrics['today']],
['Week','#16a34a',$metrics['week']],
['New','#22c55e',$metrics['new']],
['Returning','#f97316',$metrics['returning']],
['Bots','#7c3aed',$metrics['bots']],
] as [$t,$c,$v]): ?>
<div class="col-6 col-md-2">
<div class="kpi" style="background:<?=$c?>">
<h5><?=$v?></h5><?=$t?>
</div>
</div>
<?php endforeach;?>
</div>

<!-- DEVICE CARDS -->
<div class="row g-2 mt-3">
<?php foreach([
['Total Desktop','#7c3aed',$totalDevice['desktop']??0],
['Total Mobile','#06b6d4',$totalDevice['mobile']??0],
['Today Desktop','#9333ea',$todayDevice['desktop']??0],
['Today Mobile','#0891b2',$todayDevice['mobile']??0],
] as [$t,$c,$v]): ?>
<div class="col-6 col-md-3">
<div class="kpi" style="background:<?=$c?>">
<h5><?=$v?></h5><?=$t?>
</div>
</div>
<?php endforeach;?>
</div>

<!-- CHARTS -->
<div class="row mt-4">
<div class="col-md-6 mb-3">
<div class="card p-3">
<h6>📱 Today Device</h6>
<canvas id="deviceChart"></canvas>
</div>
</div>

<div class="col-md-6 mb-3">
<div class="card p-3">
<h6>📆 Weekly Visits</h6>
<canvas id="weekChart"></canvas>
</div>
</div>
</div>

<div class="row">
<div class="col-md-6 mb-3">
<div class="card p-3">
<h6>🔥 Product Visits</h6>
<canvas id="productChart"></canvas>
</div>
</div>

<div class="col-md-6 mb-3">
<div class="card p-3">
<h6>📄 Page Visits</h6>
<canvas id="pageChart"></canvas>
</div>
</div>
</div>

<!-- PRODUCT TABLE -->
<div class="card mt-4">
<div class="card-body">
<h6>🛒 Product-wise Visits</h6>
<table class="table table-sm table-striped">
<tr><th>Product</th><th>Visits</th></tr>
<?php foreach($productStats as $p): ?>
<tr>
<td><?=h($p['name'])?></td>
<td><?=$p['v']?></td>
</tr>
<?php endforeach;?>
</table>
</div>
</div>

<!-- IP TABLE -->
<div class="card mt-4">
<div class="card-body">
<h6>🌐 IP Address Visits</h6>
<table class="table table-sm table-striped">
<tr><th>IP</th><th>Device</th><th>Page</th><th>Visits</th><th>Last Visit</th></tr>
<?php foreach($ipVisits as $v): ?>
<tr>
<td><?=h($v['ip_address'])?></td>
<td><?=$v['device_type']?></td>
<td><?=$v['page_type']?></td>
<td><?=$v['visits']?></td>
<td><?=$v['last_visit']?></td>
</tr>
<?php endforeach;?>
</table>

<nav>
<ul class="pagination pagination-sm">
<?php if($page>1): ?>
<li class="page-item"><a class="page-link" href="?page=<?=$page-1?>">Prev</a></li>
<?php endif;?>
<?php if($page<$totalPages): ?>
<li class="page-item"><a class="page-link" href="?page=<?=$page+1?>">Next</a></li>
<?php endif;?>
</ul>
</nav>
</div>
</div>

<a href="?export=1" class="btn btn-success mt-3">⬇ Export CSV</a>
</div>

<script>
new Chart(deviceChart,{
 type:'doughnut',
 data:{labels:<?=json_encode(array_keys($todayDevice))?>,
 datasets:[{data:<?=json_encode(array_values($todayDevice))?>}]}
});

new Chart(weekChart,{
 type:'line',
 data:{labels:<?=json_encode(array_column($weekly,'d'))?>,
 datasets:[{data:<?=json_encode(array_column($weekly,'c'))?>,fill:true}]}
});

new Chart(productChart,{
 type:'bar',
 data:{labels:<?=json_encode(array_column($productStats,'name'))?>,
 datasets:[{data:<?=json_encode(array_column($productStats,'v'))?>}]}
});

new Chart(pageChart,{
 type:'bar',
 data:{labels:<?=json_encode(array_keys($pageStats))?>,
 datasets:[{data:<?=json_encode(array_values($pageStats))?>}]}
});
</script>
</body>
</html>
