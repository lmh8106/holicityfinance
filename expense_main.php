<?
include  "./lib/config.php";
include "./inc/header_b.html"; 

//권한체크
if($_SESSION['admin_id']!="super"){		//슈퍼관리자가 아니라면

	include "./lib/auth_check.php";
	check_access("재정관리", $my_perms); // 권한이 없으면 자동으로 튕겨냄
}


$target_year = isset($_GET['year']) ? intval($_GET['year']) : date("Y");

error_reporting(E_ALL);
ini_set('display_errors', 1);


// 1. 기초 설정 및 날짜 수신
$today = date("Y-m-d");
$selected_date = isset($_GET['date']) ? $_GET['date'] : $today;
$current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'input';
$year = date("Y", strtotime($selected_date));

// 2. 지출 항목 리스트 로드 (입력용)
$items_sql = "SELECT * FROM account_items 
              WHERE church_id = '$church_id' AND  item_type='EXPENSE' AND target_year = $year
              AND item_code % 100 != 0 
              ORDER BY item_code ASC";
$items_res = $conn->query($items_sql);
$expense_items = [];
while($row = $items_res->fetch_assoc()) { 
    $expense_items[] = $row; 
}

// 3. 해당 날짜의 지출 내역 로드 (미확정/확정 분리)
$list_sql = "SELECT r.*, i.item_name 
             FROM expense_records r
             LEFT JOIN account_items i ON r.item_code = i.item_code
             WHERE r.expense_date = '$selected_date' AND r.church_id = '$church_id'
             ORDER BY r.id DESC";
$records = $conn->query($list_sql);

$unfixed_list = []; 
$fixed_list = []; 
$day_total = 0;

while($row = $records->fetch_assoc()) {
    $day_total += $row['amount'];
    if($row['is_fixed'] == 0) $unfixed_list[] = $row;
    else $fixed_list[] = $row;
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>지출 관리 시스템</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { --gold: #d4af37; --bg: #0a0a0a; --card: #161616; --border: #333; --red: #ff4d4d; --blue: #3498db; --green: #2ecc71; }
       .container { max-width: 900px; margin: 0 auto; padding-bottom: 120px !important; }

        .header-sticky { position: sticky; top: 0; z-index: 100; background: #000; }
        .tab-menu { display: flex; border-bottom: 1px solid var(--border); }
        .tab-item { flex: 1; padding: 18px; text-align: center; font-size: 14px; color: #666; cursor: pointer; text-decoration: none; }
        .tab-item.active { color: var(--gold); border-bottom: 3px solid var(--gold); font-weight: bold; }

        .control-panel { background: #111; padding: 15px; border-bottom: 1px solid var(--border); }
        .date-input-wrapper { text-align: center; margin-bottom: 12px; }
        
        .search-wrapper { position: relative; display: flex; align-items: center; }
        .search-wrapper i { position: absolute; left: 15px; color: #555; }
        .filter-input { width: 100%; background: #000; border: 1px solid #444; color: var(--gold); padding: 12px 12px 12px 40px; border-radius: 12px; font-size: 14px; outline: none; }

        .item-card { background: var(--card); border: 1px solid var(--border); border-radius: 15px; margin: 10px 15px; padding: 15px; display: flex; align-items: center; justify-content: space-between; transition: 0.2s; }
        .item-info { flex: 0 0 40%; }
        .item-name { font-size: 15px; font-weight: bold; color: #eee; }
        .item-code { font-size: 11px; color: #555; }
        
        .input-area { flex: 1; text-align: right; display: flex; flex-direction: column; gap: 8px; }
        .amt-input { width: 100%; background: #222; border: 1px solid #444; color: var(--gold); padding: 12px; border-radius: 10px; text-align: right; font-size: 16px; font-weight: bold; outline: none; }
        .target-input { width: 100%; background: #111; border: 1px solid #333; color: #888; padding: 8px; border-radius: 8px; text-align: right; font-size: 12px; outline: none; }

        .btn-bulk-submit { position: fixed; bottom: 80px; left: 15px; right: 15px; background: var(--gold); color: #000; border: none; padding: 18px; border-radius: 15px; font-weight: bold; font-size: 16px; z-index: 101; cursor: pointer;  max-width: 900px;margin: 0 auto;}

        .manage-container { padding: 15px; }
        .total-summary { background: linear-gradient(145deg, #1a1a1a, #111); padding: 25px; border-radius: 20px; border: 1px solid var(--gold); text-align: center; margin-bottom: 25px; }
        
        .section-header { font-size: 13px; color: #888; margin: 20px 0 10px 5px; font-weight: bold; display: flex; align-items: center; gap: 5px; }
        .list-box { border-radius: 15px; border: 1px solid var(--border); overflow: hidden; background: #111; margin-bottom: 20px; }
        
        .record-item { display: flex; justify-content: space-between; align-items: center; padding: 15px; border-bottom: 1px solid #222; background: var(--card); }
        .record-item:last-child { border: none; }
        .badge-btn { font-size: 11px; padding: 6px 12px; border-radius: 8px; border: 1px solid #444; color: #aaa; text-decoration: none; margin-left: 5px; font-weight: bold; display: inline-block; }

		/* 검색창 스타일 */
		.search-box { 
			background: #222; border: 1px solid #444; color: #fff; padding: 10px 20px; border-radius: 25px; font-size: 14px; outline: none; text-align: center;
		}
		.search-box:focus { border-color: var(--gold); }

		/* 날짜 입력창 달력 아이콘 하얗게 처리 */
		.search-box[type="date"] {
			color-scheme: dark; /* 브라우저가 지원할 경우 내부 아이콘을 자동으로 밝게 조절 */
		}
		/* 크롬, 사파리, 엣지 등 대부분의 모바일 브라우저 대응 */
		.search-box[type="date"]::-webkit-calendar-picker-indicator {
			background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="16" height="15" viewBox="0 0 24 24" fill="white"><path d="M20 3h-1V1h-2v2H7V1H5v2H4c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 18H4V8h16v13z"/></svg>');
			filter: invert(0); /* 반전을 끄고 위 SVG 색상(white) 사용 */
			cursor: pointer;
		}

        
        /* 하단 네비게이션 */
        .bottom-nav { position: fixed; bottom: 0; left: 0; right: 0; height: 65px; background: rgba(0,0,0,0.1);backdrop-filter: blur(15px); display: flex; border-top: 1px solid #333; backdrop-filter: blur(10px); z-index: 1000; padding-bottom: env(safe-area-inset-bottom); max-width: 900px;margin: 0 auto;}
        .nav-item { flex: 1; text-align: center; text-decoration: none; color: #555; display: flex; flex-direction: column; justify-content: center; align-items: center; font-size: 10px; }
        .nav-item i { font-size: 20px; margin-bottom: 4px; }
        .nav-item.active { color: var(--gold); }

		

        #noResult { display: none; text-align: center; padding: 50px; color: #555; }
    </style>
</head>
<body>
<div class="container">
		<div class="header-sticky">
			<div class="tab-menu">
				<a href="?tab=input&date=<?= $selected_date ?>" class="tab-item <?= $current_tab == 'input' ? 'active' : '' ?>">지출 일괄 입력</a>
				<a href="?tab=manage&date=<?= $selected_date ?>" class="tab-item <?= $current_tab == 'manage' ? 'active' : '' ?>">확정 관리</a>
			</div>

			<div class="control-panel">
				<div class="date-input-wrapper">
					<input type="date" value="<?= $selected_date ?>" onchange="location.href='?tab=<?= $current_tab ?>&date='+this.value" class="search-box" style="flex:1; margin-bottom:0;">
				</div>
				<?php if($current_tab == 'input'): ?>
				<div class="search-wrapper">
					<i class="fa fa-search"></i>
					<input type="text" id="itemSearch" class="filter-input" placeholder="항목명 또는 코드 검색" onkeyup="filterItems()">
				</div>
				<?php endif; ?>
			</div>
		</div>

		<?php if($current_tab == 'input'): ?>
		<form action="expense_process.php?action=bulk_add" method="POST" id="expenseForm">
			<input type="hidden" name="expense_date" value="<?= $selected_date ?>">
			<div id="itemList">
				<?php foreach($expense_items as $item): ?>
				<div class="item-card" data-name="<?= $item['item_name'] ?>" data-code="<?= $item['item_code'] ?>">
					<div class="item-info">
						<div class="item-name"><?= $item['item_name'] ?></div>
						<div class="item-code">CODE <?= $item['item_code'] ?></div>
					</div>
					<div class="input-area">
						<input type="number" name="amounts[<?= $item['item_code'] ?>]" class="amt-input" placeholder="0" inputmode="numeric">
						<input type="text" name="targets[<?= $item['item_code'] ?>]" class="target-input" placeholder="지출처/메모">
					</div>
				</div>
				<?php endforeach; ?>
			</div>
			<div id="noResult">검색 결과가 없습니다.</div>
			<button type="submit" class="btn-bulk-submit">내역 일괄 등록하기</button>
		</form>

		<?php else: ?>
		<div class="manage-container">
			<div class="total-summary">
				<div style="font-size: 12px; color: #888;">선택일 총 지출</div>
				<div style="font-size: 30px; font-weight: bold; color: var(--gold);">₩ <?= number_format($day_total) ?></div>
			</div>

			<div class="section-header" style="color: var(--red);"><i class="fa-solid fa-circle-exclamation"></i> 미확정 내역 (<?= count($unfixed_list) ?>)</div>
			<div class="list-box">
				<?php if(empty($unfixed_list)): ?>
					<div style="padding: 30px; text-align: center; color: #444;">미확정 내역이 없습니다.</div>
				<?php else: ?>
					<?php foreach($unfixed_list as $row): ?>
					<div class="record-item">
						<div>
							<div style="font-size:15px; font-weight:bold; color:#eee;"><?= $row['target_name'] ?: '지출처 미입력' ?></div>
							<div style="font-size:11px; color:var(--blue);"><?= $row['item_name'] ?></div>
						</div>
						<div style="text-align:right;">
							<div style="font-weight:bold; color:var(--gold);">₩<?= number_format($row['amount']) ?></div>
							<div style="margin-top:8px;">
								<a href="expense_process.php?action=fix&id=<?= $row['id'] ?>&date=<?= $selected_date ?>" class="badge-btn" style="border-color:var(--gold); color:var(--gold);">확정</a>
								<a href="expense_process.php?action=del&id=<?= $row['id'] ?>&date=<?= $selected_date ?>" class="badge-btn" style="border-color:var(--red); color:var(--red);" onclick="return confirm('정말 삭제하시겠습니까?')">삭제</a>
							</div>
						</div>
					</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>

			<div class="section-header" style="color: var(--green);"><i class="fa-solid fa-circle-check"></i> 확정된 내역 (<?= count($fixed_list) ?>)</div>
			<div class="list-box" style="border-color: #222;">
				<?php if(empty($fixed_list)): ?>
					<div style="padding: 30px; text-align: center; color: #444;">확정된 내역이 없습니다.</div>
				<?php else: ?>
					<?php foreach($fixed_list as $row): ?>
					<div class="record-item" style="opacity: 0.8; background: #0c0c0c;">
						<div>
							<div style="font-size:14px; font-weight:bold; color:#aaa;"><?= $row['target_name'] ?></div>
							<div style="font-size:11px; color:#555;"><?= $row['item_name'] ?></div>
						</div>
						<div style="text-align:right;">
							<div style="font-weight:bold; color:#888;">₩<?= number_format($row['amount']) ?></div>
							<div style="margin-top:8px;">
								<a href="expense_process.php?action=unfix&id=<?= $row['id'] ?>&date=<?= $selected_date ?>" class="badge-btn">확정취소</a>
							</div>
						</div>
					</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</div>
		<?php endif; ?>

</div>

<div class="bottom-nav">
    <a href="income_main.php" class="nav-item"><i class="fa-solid fa-hand-holding-dollar"></i><span>수입관리</span></a>
    <a href="expense_main.php" class="nav-item active"><i class="fa-solid fa-receipt"></i><span>지출관리</span></a>
    <a href="report_annual.php" class="nav-item"><i class="fa-solid fa-chart-line"></i><span>연간분석</span></a>
    <a href="report_total.php" class="nav-item"><i class="fa-solid fa-scale-balanced"></i><span>수지결산</span></a>
    <a href="income_settings.php" class="nav-item"><i class="fa-solid fa-gear"></i><span>설정</span></a>
</div>

<script>
function filterItems() {
    const query = document.getElementById('itemSearch').value.toLowerCase();
    const items = document.querySelectorAll('.item-card');
    const noResult = document.getElementById('noResult');
    let count = 0;
    items.forEach(item => {
        const name = item.getAttribute('data-name').toLowerCase();
        const code = item.getAttribute('data-code').toLowerCase();
        if (name.includes(query) || code.includes(query)) { item.style.display = 'flex'; count++; }
        else { item.style.display = 'none'; }
    });
    noResult.style.display = (count === 0) ? 'block' : 'none';
}
</script>
</body>
</html>