<?
include  "./lib/config.php";


$church_id = "CHURCH_001"; // 세션 연동 필요

$date = isset($_GET['date']) ? $_GET['date'] : date("Y-m-d");

// 항목별 합계 계산
$sql_summary = "SELECT i.item_name, SUM(r.amount) as item_total 
                FROM income_records r
                JOIN account_items i ON r.item_code = i.item_code
                WHERE r.income_date = '$date'
                GROUP BY r.item_code";
$summary = $conn->query($sql_summary);
?>
<!DOCTYPE html>
<html>
<head>
    <title>주일 수입 보고서 (<?= $date ?>)</title>
    <style>
        body { font-family: 'serif'; padding: 40px; color: #333; }
        .report-title { text-align: center; font-size: 28px; text-decoration: underline; margin-bottom: 30px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #000; padding: 10px; text-align: center; }
        .total-row { background: #f0f0f0; font-weight: bold; }
        .sign-area { margin-top: 50px; display: flex; justify-content: flex-end; gap: 30px; }
        .sign-box { border: 1px solid #000; width: 80px; height: 80px; text-align: center; line-height: 20px; padding-top: 5px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="no-print"><button onclick="window.print()">인쇄하기</button></div>
    
    <div class="report-title">주일 수입 결산 보고서</div>
    <p style="text-align: right;">일자: <?= $date ?></p>

    <table>
        <thead>
            <tr>
                <th>수입 항목</th>
                <th>금액 (원)</th>
                <th>비고</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $grand_total = 0;
            while($row = $summary->fetch_assoc()): 
                $grand_total += $row['item_total'];
            ?>
            <tr>
                <td><?= $row['item_name'] ?></td>
                <td style="text-align: right;"><?= number_format($row['item_total']) ?></td>
                <td></td>
            </tr>
            <?php endwhile; ?>
            <tr class="total-row">
                <td>합 계</td>
                <td style="text-align: right;"><?= number_format($grand_total) ?></td>
                <td></td>
            </tr>
        </tbody>
    </table>

    <div class="sign-area">
        <div class="sign-box">담 당<br><br>(인)</div>
        <div class="sign-box">재 무<br><br>(인)</div>
        <div class="sign-box">담 임<br><br>(인)</div>
    </div>
</body>
</html>