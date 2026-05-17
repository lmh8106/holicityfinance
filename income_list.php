<?
include  "./lib/config.php";
include "./inc/header_b.html"; 


$church_id = "CHURCH_001"; // 세션 연동 필요


$target_year = isset($_GET['year']) ? intval($_GET['year']) : date("Y");

error_reporting(E_ALL);
ini_set('display_errors', 1);

$today = date("Y-m-d");


// 검색 조건 (날짜별로 보기)
$search_date = isset($_GET['date']) ? $_GET['date'] : $today;

// 내역 불러오기 (교인명, 항목명 Join)
$sql = "SELECT r.*, m.name as member_name, i.item_name 
        FROM income_records r
        LEFT JOIN church_members m ON r.member_id = m.id
        LEFT JOIN account_items i ON r.item_code = i.item_code AND i.target_year = $target_year
        WHERE r.income_date = '$search_date'  AND r.church_id = '$church_id'
        ORDER BY r.id DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>수입 내역 관리</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { --gold: #d4af37; --bg: #0a0a0a; --card: #161616; --border: #333; --blue: #3498db; --red: #ff4d4d; }
        body { background: var(--bg); color: #fff; font-family: 'Pretendard', sans-serif; margin: 0; padding-bottom: 80px; }
        
        .header { background: #000; padding: 15px; border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 100; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { margin: 0; font-size: 16px; color: var(--gold); }

        .filter-section { padding: 15px; background: var(--card); border-bottom: 1px solid var(--border); display: flex; gap: 10px; }
        .date-input { flex: 1; background: #000; border: 1px solid var(--border); color: #fff; padding: 10px; border-radius: 8px; }

        .record-list { padding: 10px; }
        .record-card { 
            background: var(--card); border: 1px solid var(--border); border-radius: 12px; 
            padding: 15px; margin-bottom: 10px; position: relative;
        }
        .record-card.fixed { border-left: 4px solid #555; opacity: 0.8; }
        .record-card.unfixed { border-left: 4px solid var(--gold); }

        .card-top { display: flex; justify-content: space-between; margin-bottom: 8px; }
        .member-name { font-size: 16px; font-weight: bold; }
        .item-tag { font-size: 12px; background: #333; padding: 2px 8px; border-radius: 4px; color: var(--gold); }
        
        .card-bottom { display: flex; justify-content: space-between; align-items: flex-end; }
        .amount { font-size: 18px; font-weight: bold; color: #fff; }
        
        /* 버튼 스타일 */
        .btn-group { display: flex; gap: 10px; }
        .action-btn { font-size: 13px; padding: 6px 12px; border-radius: 6px; border: 1px solid #444; color: #ccc; text-decoration: none; }
        .status-badge { font-size: 11px; padding: 3px 8px; border-radius: 20px; }
        .status-fixed { background: #222; color: #666; }
        .status-unfixed { background: #d4af3722; color: var(--gold); border: 1px solid var(--gold); }

        .fixed-notice { font-size: 11px; color: #666; margin-top: 10px; text-align: center; }
    </style>
</head>
<body>

<div class="header">
    <h1><i class="fa-solid fa-list-ul"></i> 수입 내역 관리</h1>
    <button onclick="fixAll()" style="background:var(--gold); border:none; padding:8px 15px; border-radius:6px; font-weight:bold; font-size:12px;">오늘내역 확정</button>
</div>

<div class="filter-section">
    <input type="date" id="search_date" class="date-input" value="<?= $search_date ?>" onchange="location.href='?date='+this.value">
</div>

<div class="record-list">
    <?php if($result->num_rows > 0): ?>
        <?php while($row = $result->fetch_assoc()): 
            $is_fixed = $row['is_fixed'];
        ?>
        <div class="record-card <?= $is_fixed ? 'fixed' : 'unfixed' ?>">
            <div class="card-top">
                <span class="member-name"><?= htmlspecialchars($row['member_name']) ?></span>
                <span class="item-tag"><?= htmlspecialchars($row['item_name']) ?></span>
            </div>
            <div class="card-bottom">
                <div class="amount">₩ <?= number_format($row['amount']) ?></div>
                
                <div class="btn-group">
                    <?php if(!$is_fixed): ?>
                        <a href="#" onclick="editRecord(<?= $row['id'] ?>, <?= $row['amount'] ?>)" class="action-btn"><i class="fa-solid fa-pen"></i> 수정</a>
                        <a href="#" onclick="deleteRecord(<?= $row['id'] ?>)" class="action-btn" style="color:var(--red);"><i class="fa-solid fa-trash"></i></a>
                    <?php else: ?>
                        <span class="status-badge status-fixed"><i class="fa-solid fa-lock"></i> 확정됨</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div style="text-align:center; padding:50px; color:#555;">기록된 내역이 없습니다.</div>
    <?php endif; ?>
</div>

<p class="fixed-notice">확정된 데이터는 수정 및 삭제가 불가능합니다.</p>

<script>
// 1. 수정 기능 (간단하게 Prompt 사용하거나 별도 팝업 구현 가능)
function editRecord(id, oldAmount) {
    let newAmount = prompt("수정할 금액을 입력하세요 (숫자만)", oldAmount);
    if(newAmount && newAmount != oldAmount) {
        location.href = `income_manage_process.php?action=edit&id=${id}&amount=${newAmount}`;
    }
}

// 2. 삭제 기능
function deleteRecord(id) {
    if(confirm("이 내역을 삭제하시겠습니까?")) {
        location.href = `income_manage_process.php?action=delete&id=${id}`;
    }
}

// 3. 일괄 확정 기능
function fixAll() {
    let date = document.getElementById('search_date').value;
    if(confirm(`${date} 내역을 모두 확정하시겠습니까?\n확정 후에는 수정이 불가능합니다.`)) {
        location.href = `income_manage_process.php?action=fix&date=${date}`;
    }
}
</script>

</body>
</html>