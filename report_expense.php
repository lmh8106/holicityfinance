<?
include  "./lib/config.php";


$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date("Y-01-01");
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date("Y-m-d");
$year = date("Y", strtotime($start_date));

// 항목별 지출 통계
$sql_items = "SELECT i.item_name, SUM(r.amount) as total 
              FROM expense_records r
              JOIN account_items i ON r.item_code = i.item_code
              WHERE r.expense_date BETWEEN '$start_date' AND '$end_date' AND r.is_fixed = 1
              GROUP BY r.item_code ORDER BY total DESC";
$items_res = $conn->query($sql_items);
$items_data = []; $max_val = 1;
while($row = $items_res->fetch_assoc()) {
    $items_data[] = $row;
    if($row['total'] > $max_val) $max_val = $row['total'];
}

// 상세 데이터 로드 (JS 필터링용)
$sql_details = "SELECT r.*, i.item_name 
                FROM expense_records r 
                LEFT JOIN account_items i ON r.item_code = i.item_code
                WHERE r.expense_date BETWEEN '$start_date' AND '$end_date' AND r.is_fixed = 1
                ORDER BY r.expense_date DESC";
$details_res = $conn->query($sql_details);
$details_list = [];
while($row = $details_res->fetch_assoc()) { $details_list[] = $row; }
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>지출 보고서</title>
    <style>
        :root { --gold: #d4af37; --bg: #0a0a0a; --card: #161616; --border: #333; --blue: #3498db; }
        body { background: var(--bg); color: #fff; font-family: sans-serif; padding: 15px; }
        .chart-row { display: flex; align-items: center; margin-bottom: 15px; gap: 10px; }
        .bar-container { flex: 1; height: 12px; background: #222; border-radius: 6px; overflow: hidden; }
        .bar-fill { height: 100%; background: var(--gold); }
        .amount-val { width: 100px; text-align: right; color: var(--gold); cursor: pointer; text-decoration: underline; font-size: 13px; }
        .section { background: var(--card); padding: 20px; border-radius: 15px; border: 1px solid var(--border); margin-bottom: 20px; }
    </style>
</head>
<body>

<h2 style="color: var(--gold); text-align: center;">지출 분석 보고서</h2>

<div class="section">
    <form method="GET" style="display: flex; gap: 5px;">
        <input type="date" name="start_date" value="<?= $start_date ?>" style="flex:1; padding:10px; border-radius:8px; border:1px solid #444; background:#222; color:#fff;">
        <input type="date" name="end_date" value="<?= $end_date ?>" style="flex:1; padding:10px; border-radius:8px; border:1px solid #444; background:#222; color:#fff;">
        <button type="submit" style="background:var(--gold); border:none; padding:10px 15px; border-radius:8px; font-weight:bold;">검색</button>
    </form>
</div>

<div class="section">
    <h3>항목별 지출 비중</h3>
    <?php foreach($items_data as $item): 
        $pct = ($item['total'] / $max_val) * 100;
    ?>
    <div class="chart-row">
        <div style="width:70px; font-size:12px;"><?= $item['item_name'] ?></div>
        <div class="bar-container"><div class="bar-fill" style="width:<?= $pct ?>%;"></div></div>
        <div class="amount-val" onclick="showExpenseDetail('<?= $item['item_name'] ?>')">₩<?= number_format($item['total']) ?></div>
    </div>
    <?php endforeach; ?>
</div>

<div id="detailBox" class="section" style="display:none;">
    <h4 id="detailTitle" style="color:var(--gold); margin-top:0;"></h4>
    <div id="detailContent"></div>
</div>

<script>
const details = <?= json_encode($details_list) ?>;
function showExpenseDetail(itemName) {
    const box = document.getElementById('detailBox');
    const content = document.getElementById('detailContent');
    const filtered = details.filter(d => d.item_name === itemName);
    
    let html = '<table style="width:100%; font-size:13px; border-collapse:collapse;">';
    filtered.forEach(d => {
        html += `<tr style="border-bottom:1px solid #333;">
                    <td style="padding:10px 0; color:#888;">${d.expense_date}</td>
                    <td><strong>${d.target_name}</strong></td>
                    <td style="text-align:right;">${Number(d.amount).toLocaleString()}</td>
                 </tr>`;
    });
    html += '</table>';
    
    document.getElementById('detailTitle').innerText = itemName + " 지출 내역";
    content.innerHTML = html;
    box.style.display = 'block';
    box.scrollIntoView({ behavior: 'smooth' });
}
</script>

</body>
</html>