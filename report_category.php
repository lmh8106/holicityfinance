<?
include  "./lib/config.php";
include "./inc/header_b.html"; 


$church_id = "CHURCH_001"; // 세션 연동 필요


$target_year = isset($_GET['year']) ? intval($_GET['year']) : date("Y");


error_reporting(E_ALL);
ini_set('display_errors', 1);

<?php
include "db_conn.php";

$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date("Y-m-01");
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date("Y-m-d");

// 카테고리별 수입/지출 통합 쿼리
$sql = "SELECT 
            i.category_name,
            SUM(CASE WHEN i.item_type = 'INCOME' THEN r_inc.amount ELSE 0 END) as total_income,
            SUM(CASE WHEN i.item_type = 'EXPENSE' THEN r_exp.amount ELSE 0 END) as total_expense
        FROM account_items i
        LEFT JOIN income_records r_inc ON i.item_code = r_inc.item_code AND r_inc.income_date BETWEEN '$start_date' AND '$end_date' AND r_inc.is_fixed = 1
        LEFT JOIN expense_records r_exp ON i.item_code = r_exp.item_code AND r_exp.expense_date BETWEEN '$start_date' AND '$end_date' AND r_exp.is_fixed = 1
        GROUP BY i.category_name
        ORDER BY total_income DESC";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>팀별/목적별 수지현황</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { --gold: #d4af37; --bg: #0a0a0a; --card: #161616; --border: #333; --red: #ff4d4d; --blue: #3498db; }
        body { background: var(--bg); color: #fff; font-family: 'Pretendard', sans-serif; padding-bottom: 80px; }
        .container { padding: 15px; }
        .cat-card { background: var(--card); border: 1px solid var(--border); border-radius: 15px; padding: 20px; margin-bottom: 15px; }
        .cat-name { font-size: 18px; font-weight: bold; color: var(--gold); border-bottom: 1px solid #222; padding-bottom: 10px; margin-bottom: 15px; }
        
        .row { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px; }
        .val { font-weight: bold; }
        .balance-row { margin-top: 10px; padding-top: 10px; border-top: 1px dashed #444; font-size: 16px; }
        
        .progress-bg { background: #222; height: 8px; border-radius: 4px; margin-top: 5px; overflow: hidden; display: flex; }
        .progress-inc { background: var(--blue); height: 100%; }
        .progress-exp { background: var(--red); height: 100%; }
    </style>
</head>
<body>

<div class="container">
    <h2 style="text-align:center; color:var(--gold);">팀별/목적별 수지현황</h2>
    
    <form method="GET" style="display:flex; gap:10px; margin-bottom:20px;">
        <input type="date" name="start_date" value="<?= $start_date ?>" style="flex:1; padding:10px; border-radius:8px; border:1px solid #444; background:#111; color:#fff;">
        <input type="date" name="end_date" value="<?= $end_date ?>" style="flex:1; padding:10px; border-radius:8px; border:1px solid #444; background:#111; color:#fff;">
        <button type="submit" style="background:var(--gold); border:none; padding:10px 15px; border-radius:8px; font-weight:bold;">조회</button>
    </form>

    <?php while($row = $result->fetch_assoc()): 
        $inc = $row['total_income'];
        $exp = $row['total_expense'];
        $bal = $inc - $exp;
        $total = $inc + $exp;
        $inc_p = ($total > 0) ? ($inc / $total) * 100 : 0;
    ?>
    <div class="cat-card">
        <div class="cat-name"><i class="fa-solid fa-users-gear"></i> <?= $row['category_name'] ?></div>
        <div class="row">
            <span>총 수입</span>
            <span class="val" style="color:var(--blue);">₩ <?= number_format($inc) ?></span>
        </div>
        <div class="row">
            <span>총 지출</span>
            <span class="val" style="color:var(--red);">₩ <?= number_format($exp) ?></span>
        </div>
        
        <div class="progress-bg">
            <div class="progress-inc" style="width:<?= $inc_p ?>%"></div>
            <div class="progress-exp" style="width:<?= 100 - $inc_p ?>%"></div>
        </div>

        <div class="row balance-row">
            <span>현재 잔액</span>
            <span class="val" style="color: <?= $bal >= 0 ? 'var(--gold)' : 'var(--red)' ?>;">₩ <?= number_format($bal) ?></span>
        </div>
    </div>
    <?php endwhile; ?>
</div>

</body>
</html>