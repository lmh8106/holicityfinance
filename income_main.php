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

$today = date("Y-m-d");

$selected_date = isset($_GET['date']) ? $_GET['date'] : $today;
$filter_item = isset($_GET['item_code']) ? intval($_GET['item_code']) : '';
$current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'input'; 

// 1. 기초 데이터 로드
//전체보기관련
$is_all = $_GET['is_all'] ?? '';
$where_clause = " WHERE church_id = '$church_id'"; // 기본 교회 필터

if ($is_all !== 'on') { 
    // 전체보기가 아닐 때만 출석 교인 필터링
    $where_clause .= " AND attendance_type = '출석'";
}


$items_res = $conn->query("SELECT * FROM account_items WHERE target_year=$target_year AND item_type='INCOME' AND item_code % 100 != 0 ORDER BY item_code ASC");
$members_res = $conn->query("SELECT id, name, job_title,birth_date FROM church_members $where_clause ORDER BY name ASC");

// 2. 내역 데이터 로드
$where_item = $filter_item ? "AND r.item_code = $filter_item" : "";
$list_sql = "SELECT r.*, m.name as member_name, m.birth_date, i.item_name 
             FROM income_records r
             LEFT JOIN church_members m ON r.member_id = m.id
             LEFT JOIN account_items i ON r.item_code = i.item_code AND i.target_year = $target_year
             WHERE r.income_date = '$selected_date' $where_item
             ORDER BY r.is_fixed ASC, r.id DESC";
$records = $conn->query($list_sql);

$unfixed_list = [];
$fixed_list = [];
$day_total = 0;

// 교인별 당일 총 입금액 계산
$sum_sql = "SELECT member_id, SUM(amount) as total FROM income_records WHERE income_date = '$selected_date' GROUP BY member_id";
$sum_res = $conn->query($sum_sql);
$member_totals = [];
while($s = $sum_res->fetch_assoc()) {
    $member_totals[$s['member_id']] = $s['total'];
}

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
    <title>통합 수입 관리</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { --gold: #d4af37; --bg: #0a0a0a; --card: #161616; --border: #333; --red: #ff4d4d; --blue: #3498db; --green: #2ecc71; }
       
		.container { max-width: 900px; margin: 0 auto; padding-bottom: 120px !important; }
        
        .tab-menu { display: flex; background: #000; position: sticky; top: 0; z-index: 1; border-bottom: 1px solid var(--border); }
        .tab-item { flex: 1; padding: 15px; text-align: center; font-size: 14px; color: #666; cursor: pointer; font-weight: bold; }
        .tab-item.active { color: var(--gold); border-bottom: 3px solid var(--gold); }

        .content-section { display: none; padding: 15px; }
        .content-section.active { display: block; }

        .section-title { font-size: 13px; color: #888; margin: 15px 0 8px; font-weight: bold; display: flex; justify-content: space-between; align-items: center; }
        .card { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 15px; margin-bottom: 15px; }
        

		/* 검색창 스타일 */
		.search-box { 
			width: 100%; background: #222; border: 1px solid var(--border); 
			color: #fff; padding: 12px; border-radius: 10px; outline: none; 
			margin-bottom: 15px; box-sizing: border-box; font-size: 14px; 
		}
		.search-box:focus { border-color: var(--gold); }

		.search-box[type="date"] {
			color-scheme: dark; 
		}
		.search-box[type="date"]::-webkit-calendar-picker-indicator {
			background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="16" height="15" viewBox="0 0 24 24" fill="white"><path d="M20 3h-1V1h-2v2H7V1H5v2H4c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 18H4V8h16v13z"/></svg>');
			filter: invert(0); 
			cursor: pointer;
		}
		

		/* 검색창 스타일2 */
		.search-box2 { 
			margin-top:10px; background:transparent; border:1px solid #444; color:#fff; padding:5px; border-radius:5px; font-size:16px;
		}
		.search-box2:focus { border-color: var(--gold); }
		.search-box2[type="date"] {
			color-scheme: dark; 
		}

        .scroll-wrapper { display: flex; gap: 8px; overflow-x: auto; padding-bottom: 10px; }
        .item-chip { flex: 0 0 auto; padding: 8px 15px; background: #222; border-radius: 20px; font-size: 14px; font-weight: 700; cursor: pointer; color: #aaa; border: 1px solid transparent; }
        .item-chip.active { background: var(--gold); color: #000; font-weight: bold; border-color: var(--gold); }

        .record-item { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #222; }
        .record-item:last-child { border: none; }
        .select-check { width: 22px; height: 22px; accent-color: var(--gold); margin-right: 12px; }
        
        .badge { font-size: 10px; padding: 4px 10px; border-radius: 5px; cursor: pointer; margin-left: 5px; border: 1px solid #444; color: #aaa; text-decoration: none; }
        .badge-edit { border-color: var(--gold); color: var(--gold); }
        .badge-del { border-color: var(--red); color: var(--red); }
        .badge-undo { border-color: var(--blue); color: var(--blue); }
		
		.all-select-btn { background: #333; color: #fff; border: none; padding: 5px 10px; border-radius: 5px; font-size: 11px; margin-right: 10px; cursor: pointer; }
        .btn-main { width: 100%; background: var(--gold); color: #000; border: none; padding: 15px; border-radius: 10px; font-size: 16px; font-weight: bold; cursor: pointer; margin-top: 10px; }
        .btn-action { background: var(--green); color: #000; border: none; padding: 6px 15px; border-radius: 8px; font-size: 13px; font-weight: bold; }
        .total-banner { background: #111; border: 1px solid var(--gold); text-align: center; padding: 15px; border-radius: 12px; margin-bottom: 15px; }
        
        .member-row { display: flex; align-items: center; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #222; }
        .amount-box { width: 100px; background: #000; border: 1px solid var(--border); color: var(--gold); padding: 8px; border-radius: 6px; text-align: right; }
        .member-total-tag { font-size: 10px; color: #aaa; background: #222; padding: 2px 6px; border-radius: 4px; margin-left: 5px; font-weight: normal; }

		/* 공통 하단 네비게이션 바 */
		.bottom-nav {
			position: fixed; bottom: 0; left: 0; right: 0; height: 65px;
			background: rgba(10, 10, 10, 0.3); display: flex; justify-content: space-around;
			align-items: center; border-top: 1px solid #333; backdrop-filter: blur(15px);
			z-index: 9999; padding-bottom: env(safe-area-inset-bottom);
			max-width: 900px; margin: 0 auto;
		}

		.nav-item {
			flex: 1; text-align: center; text-decoration: none; color: #666;
			display: flex; flex-direction: column; align-items: center; font-size: 11px; transition: all 0.3s;
		}

		.nav-item i { font-size: 18px; margin-bottom: 4px; }
		.nav-item.active { color: #d4af37; }
		.nav-item:active { transform: scale(0.9); opacity: 0.7; }
		
		.cho-btn {
			display: inline-flex; align-items: center; justify-content: center;
			width: 34px; height: 34px; background: #1a1a1a; border: 1px solid #444;
			color: #888; border-radius: 8px; font-size: 13px; font-weight: 600;
			text-decoration: none; transition: all 0.2s ease;
		}

		.cho-btn:hover { border-color: var(--gold); color: var(--gold); transform: translateY(-2px); }
		.cho-btn.active { background: var(--gold); border-color: var(--gold); color: #000; box-shadow: 0 4px 12px rgba(212, 175, 55, 0.3); }
		.cho-btn.all { width: auto; padding: 0 12px; }
    </style>
</head>
<body>
<div class="container">
	<div class="tab-menu">
		<div class="tab-item <?= $current_tab == 'input' ? 'active' : '' ?>" onclick="openTab('input-tab', this)">수입 입력</div>
		<div class="tab-item <?= $current_tab == 'manage' ? 'active' : '' ?>" onclick="openTab('manage-tab', this)">확정 관리</div>
	</div>


	<div id="input-tab" class="content-section <?= $current_tab == 'input' ? 'active' : '' ?>">
		<form action="income_record_process.php" method="POST">
			<div class="section-title">기본 정보</div>
			<div style="display: flex; gap: 8px; margin-bottom: 15px;">
				<input type="date" name="income_date" value="<?= $selected_date ?>" class="search-box" style="flex:1; margin-bottom:0;">
				<select name="season_name" class="search-box" style="flex:1; margin-bottom:0;">
					<option value="2026-1분기">2026-1분기</option>
					<option value="부활절">부활절</option>
				</select>
			</div>

			<div class="section-title">항목 선택</div>
			<div class="scroll-wrapper">
				<input type="hidden" name="item_code" id="input_selected_item">
				<?php $items_res->data_seek(0); while($it = $items_res->fetch_assoc()): ?>
					<div class="item-chip" onclick="setSelectItem(this, '<?= $it['item_code'] ?>')"><?= $it['item_name'] ?></div>
				<?php endwhile; ?>
			</div>

			<div class="section-title">
				<span>교인 리스트 (<?= $is_all == 'on' ? '전체' : '출석교인' ?>)</span>
				<button type="button" 
						onclick="location.href='?date=<?= $selected_date ?>&tab=input&is_all=<?= $is_all == 'on' ? '' : 'on' ?>'" 
						class="cho-btn <?= $is_all == 'on' ? '' : 'active' ?>" 
						style="padding: 4px 10px; height: 28px; font-size: 11px; border-radius: 6px; width:auto;">
					<i class="fa-solid <?= $is_all == 'on' ? 'fa-eye-slash' : 'fa-eye' ?>"></i> 출석인원만
				</button>
			</div>

			<input type="text" class="search-box" placeholder="이름으로 찾기..." onkeyup="filterInputNames(this.value)">
			
			<div class="card" style="padding: 0 15px; max-height: 400px; overflow-y: auto;" id="inputMemberList">
				<?php if($members_res->num_rows > 0): ?>
					<?php while($m = $members_res->fetch_assoc()): ?>
						<div class="member-row" data-name="<?= $m['name'] ?><?= str_replace('-','/',$m['birth_date']) ?>">
							<div style="display: flex; flex-direction: column;">
								<div>
									<span style="font-weight: bold;"><?= $m['name'] ?></span>
									<small style="color:#666; margin-left:4px;"><?= $m['job_title'] ?></small>
								</div>
								<div style="margin-top: 2px;">
									<small style="color:#888; font-size: 11px;">(<?= str_replace('-','/',$m['birth_date']) ?>)</small>
								</div>
							</div>
							<input type="number" name="amounts[<?= $m['id'] ?>]" class="amount-box" placeholder="0">
						</div>
					<?php endwhile; ?>
				<?php else: ?>
					<div style="text-align:center; padding:30px; color:#555; font-size:13px;">해당 조건의 성도가 없습니다.</div>
				<?php endif; ?>
			</div>
			
			<input type="hidden" name="is_all" value="<?= $is_all ?>">
			<button type="submit" class="btn-main">입력 완료</button>
		</form>
	</div>

	<div id="manage-tab" class="content-section <?= $current_tab == 'manage' ? 'active' : '' ?>">
		<div class="total-banner">
			<div style="font-size:16px; font-weight: 700; color:#aaa;"><?= $selected_date ?> 총계</div>
			<div style="font-size:24px; font-weight:bold; color:var(--gold);">₩ <?= number_format($day_total) ?></div>
			<input type="date" value="<?= $selected_date ?>" onchange="location.href='?date='+this.value+'&tab=manage'" class="search-box2">
		</div>

		<div class="section-title">항목별 필터링</div>
		<div class="scroll-wrapper">
			<div class="item-chip <?= !$filter_item ? 'active' : '' ?>" onclick="filterManageItem('')">전체보기</div>
			<?php $items_res->data_seek(0); while($it = $items_res->fetch_assoc()): ?>
				<div class="item-chip <?= $filter_item == $it['item_code'] ? 'active' : '' ?>" 
					 onclick="filterManageItem('<?= $it['item_code'] ?>')">
					<?= $it['item_name'] ?>
				</div>
			<?php endwhile; ?>
		</div>

		<div class="section-title">내역 내 이름 검색</div>
		<input type="text" class="search-box" id="manageSearch" placeholder="찾으려는 교인 이름을 입력하세요..." onkeyup="filterManageNames(this.value)">

		<form action="income_manage_process.php?action=select_fix" method="POST">
			<input type="hidden" name="date" value="<?= $selected_date ?>">
			
			<div class="section-title">
				<div>
					<button type="button" class="all-select-btn" onclick="toggleSelectAll(this)">전체선택</button>
					미확정 내역 (<?= count($unfixed_list) ?>)
				</div>
				<?php if(count($unfixed_list) > 0): ?>
					<button type="submit" class="btn-action">선택항목 확정</button>
				<?php endif; ?>
			</div>
			
			<div class="card" id="unfixedContainer">
				<?php if(count($unfixed_list) > 0): ?>
					<?php foreach($unfixed_list as $row): ?>
						<div class="record-item" data-name="<?= $row['member_name'] ?><?=str_replace('-','/',$row['birth_date']) ?>">
							<div style="display:flex; align-items:center;">
								<input type="checkbox" name="select_ids[]" value="<?= $row['id'] ?>" class="select-check">
								<div style="display: flex; flex-direction: column;">
									<div style="font-size:14px;">
										<strong><?= $row['member_name'] ?></strong>
										<span class="member-total-tag">당일총액: <?= number_format($member_totals[$row['member_id']] ?? 0) ?></span>
									</div>
									<div style="margin-top: 2px;">
										<small style="color:#888; font-size: 11px;"><?= $row['birth_date'] ? "(".str_replace('-','/',$row['birth_date']).")" : "" ?></small>
									</div>
									<div style="font-size:11px; color:var(--gold); margin-top: 2px;"><?= $row['item_name'] ?></div>
								</div>
							</div>
							<div style="text-align:right;">
								<div style="font-size:15px; font-weight:bold;"><?= number_format($row['amount']) ?></div>
								<div style="margin-top:5px;">
									<a href="javascript:editRec(<?= $row['id'] ?>, <?= $row['amount'] ?>)" class="badge badge-edit">수정</a>
									<a href="javascript:delRec(<?= $row['id'] ?>)" class="badge badge-del">삭제</a>
								</div>
							</div>
						</div>
					<?php endforeach; ?>
				<?php else: ?>
					<div style="text-align:center; padding:30px; color:#444; font-size:13px;">내역이 없습니다.</div>
				<?php endif; ?>
			</div>

			<div class="section-title" style="color:#666; margin-top:20px;">확정 완료 내역 (<?= count($fixed_list) ?>)</div>
			<div class="card" style="opacity: 0.6; border-style: dashed;" id="fixedContainer">
				<?php foreach($fixed_list as $row): ?>
					<div class="record-item" data-name="<?= $row['member_name'] ?><?=str_replace('-','/',$row['birth_date']) ?>">
						<div style="display: flex; flex-direction: column;">
							<div style="font-size:14px;">
								<strong><?= $row['member_name'] ?></strong>
							</div>
							<div style="margin-top: 2px;">
								<small style="color:#888; font-size: 11px;"><?= $row['birth_date'] ? "(".str_replace('-','/',$row['birth_date']).")" : "" ?></small>
							</div>
							<div style="font-size:11px; color:#555; margin-top: 2px;"><?= $row['item_name'] ?></div>
						</div>
						<div style="text-align:right;">
							<div style="font-size:15px; font-weight:bold; color:#888;"><?= number_format($row['amount']) ?></div>
							<a href="javascript:undoFix(<?= $row['id'] ?>)" class="badge badge-undo">확정취소</a>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</form>
	</div>

	<div class="bottom-nav">
		<a href="income_main.php" class="nav-item <?= strpos($_SERVER['PHP_SELF'], 'income_main') !== false ? 'active' : '' ?>">
			<i class="fa-solid fa-hand-holding-dollar"></i>
			<span>수입관리</span>
		</a>
		<a href="expense_main.php" class="nav-item <?= strpos($_SERVER['PHP_SELF'], 'expense_main') !== false ? 'active' : '' ?>">
			<i class="fa-solid fa-receipt"></i>
			<span>지출관리</span>
		</a>
		<a href="report_annual.php" class="nav-item <?= strpos($_SERVER['PHP_SELF'], 'report_annual') !== false ? 'active' : '' ?>">
			<i class="fa-solid fa-chart-line"></i>
			<span>연간분석</span>
		</a>
		<a href="report_total.php" class="nav-item <?= strpos($_SERVER['PHP_SELF'], 'report_total') !== false ? 'active' : '' ?>">
			<i class="fa-solid fa-scale-balanced"></i>
			<span>수지결산</span>
		</a>
		<a href="income_settings.php" class="nav-item <?= strpos($_SERVER['PHP_SELF'], 'income_settings') !== false ? 'active' : '' ?>">
			<i class="fa-solid fa-gear"></i>
			<span>설정</span>
		</a>
	</div>

	<div class="card" style="display:flex; gap:10px;">
		<button onclick="location.href='excel_export.php?date=<?= $selected_date ?>'" style="flex:1; background:#2e7d32; color:#fff; border:none; padding:10px; border-radius:8px;">엑셀 저장</button>
		<button onclick="window.open('report_weekly.php?date=<?= $selected_date ?>')" style="flex:1; background:#1565c0; color:#fff; border:none; padding:10px; border-radius:8px;">주일보고서</button>
	</div>
</div>

<script>
function openTab(tabId, el) {
    document.querySelectorAll('.content-section').forEach(s => s.classList.remove('active'));
    document.querySelectorAll('.tab-item').forEach(t => t.classList.remove('active'));
    document.getElementById(tabId).classList.add('active');
    el.classList.add('active');
}

function setSelectItem(el, code) {
    document.querySelectorAll('#input-tab .item-chip').forEach(c => c.classList.remove('active'));
    el.classList.add('active');
    document.getElementById('input_selected_item').value = code;
}

function filterManageItem(code) {
    const date = '<?= $selected_date ?>';
    location.href = `income_main.php?date=${date}&item_code=${code}&tab=manage`;
}

function filterInputNames(val) {
    const search = val.toLowerCase();
    document.querySelectorAll('#inputMemberList .member-row').forEach(row => {
        row.style.display = row.getAttribute('data-name').toLowerCase().includes(search) ? "flex" : "none";
    });
}

function filterManageNames(val) {
    const search = val.toLowerCase();
    document.querySelectorAll('#unfixedContainer .record-item').forEach(row => {
        const name = row.getAttribute('data-name').toLowerCase();
        row.style.display = name.includes(search) ? "flex" : "none";
    });
    document.querySelectorAll('#fixedContainer .record-item').forEach(row => {
        const name = row.getAttribute('data-name').toLowerCase();
        row.style.display = name.includes(search) ? "flex" : "none";
    });
}

function toggleSelectAll(btn) {
    const checks = document.querySelectorAll('input[name="select_ids[]"]');
    const visibleChecks = Array.from(checks).filter(c => c.closest('.record-item').style.display !== 'none');
    const isAllVisibleChecked = visibleChecks.every(c => c.checked);
    
    visibleChecks.forEach(c => c.checked = !isAllVisibleChecked);
    btn.innerText = !isAllVisibleChecked ? "전체해제" : "전체선택";
}

function editRec(id, old) {
    let val = prompt("금액 수정", old);
    if(val && val != old) location.href=`income_manage_process.php?action=edit&id=${id}&amount=${val}&date=<?= $selected_date ?>`;
}

function delRec(id) {
    if(confirm("이 내역을 삭제하시겠습니까?")) 
        location.href=`income_manage_process.php?action=delete&id=${id}&date=<?= $selected_date ?>`;
}

function undoFix(id) {
    if(confirm("확정을 취소하시겠습니까?")) 
        location.href=`income_manage_process.php?action=undo_fix&id=${id}&date=<?= $selected_date ?>`;
}
</script>
</body>
</html>