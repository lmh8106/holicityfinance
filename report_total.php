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

// 1. 기간 설정 (기본값: 이번 달 1일 ~ 오늘)
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date("Y-m-01");
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date("Y-m-d");

// 2. 수입 총액 및 항목별 합계
$sql_inc = "SELECT i.item_name, SUM(r.amount) as total 
            FROM income_records r
            JOIN account_items i ON r.item_code = i.item_code
            WHERE r.income_date BETWEEN '$start_date' AND '$end_date' AND r.is_fixed = 1
            GROUP BY r.item_code ORDER BY total DESC";
$res_inc = $conn->query($sql_inc);
$inc_data = []; $inc_total = 0;
while($row = $res_inc->fetch_assoc()) {
    $inc_data[] = $row;
    $inc_total += $row['total'];
}

// 3. 지출 총액 및 항목별 합계
$sql_exp = "SELECT i.item_name, SUM(r.amount) as total 
            FROM expense_records r
            JOIN account_items i ON r.item_code = i.item_code
            WHERE r.expense_date BETWEEN '$start_date' AND '$end_date' AND r.is_fixed = 1
            GROUP BY r.item_code ORDER BY total DESC";
$res_exp = $conn->query($sql_exp);
$exp_data = []; $exp_total = 0;
while($row = $res_exp->fetch_assoc()) {
    $exp_data[] = $row;
    $exp_total += $row['total'];
}

// 4. 잔액 및 지출 비율 계산
$balance = $inc_total - $exp_total;
$exp_rate = ($inc_total > 0) ? ($exp_total / $inc_total) * 100 : 0;
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>수지 결산 보고서</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { --gold: #d4af37; --bg: #0a0a0a; --card: #161616; --border: #333; --red: #ff4d4d; --blue: #3498db; }
        .container { max-width: 900px; margin: 0 auto; padding-bottom: 120px !important; }
        
        .header { background: #000; padding: 25px 15px; border-bottom: 1px solid var(--border); text-align: center; }
        .balance-card { background: var(--card); margin: 10px 15px 20px; border-radius: 15px; padding: 20px; border: 1px solid var(--gold); text-align: center; box-shadow: 0 10px 20px rgba(0,0,0,0.5); }
        
        .filter-box { padding: 15px; background: #111; margin: 0 15px 20px; border-radius: 12px; border: 1px solid var(--border); }
        .flex-row { display: flex; gap: 8px; margin-bottom: 10px; }
        input { flex: 1; background: #222; border: 1px solid #444; color: #fff; padding: 10px; border-radius: 8px; font-size: 13px; }
        .btn-search { background: var(--gold); border: none; padding: 10px 20px; border-radius: 8px; font-weight: bold; cursor: pointer; }

        .summary-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin: 0 15px 20px; }
        .mini-card { background: var(--card); padding: 15px; border-radius: 12px; border: 1px solid var(--border); }
        .label { font-size: 11px; color: #888; margin-bottom: 5px; }
        .val { font-size: 16px; font-weight: bold; }

        .section-title { font-size: 14px; font-weight: bold; margin: 20px 15px 10px; display: flex; align-items: center; gap: 8px; }
        
        /* 비교 막대 차트 */
        .comparison-container { margin: 0 15px 20px; background: #222; height: 30px; border-radius: 15px; overflow: hidden; display: flex; }
        .inc-bar { height: 100%; background: var(--blue); transition: width 0.5s; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: bold; }
        .exp-bar { height: 100%; background: var(--red); transition: width 0.5s; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: bold; }

        .item-list { background: var(--card); margin: 0 15px 20px; border-radius: 12px; padding: 10px; border: 1px solid var(--border); }
        .item-row { display: flex; justify-content: space-between; padding: 10px 5px; border-bottom: 1px solid #222; font-size: 13px; }
        .item-row:last-child { border: none; }

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


		/* 공통 하단 네비게이션 바 */
		.bottom-nav {
			position: fixed;
			bottom: 0;
			left: 0;
			right: 0;
			height: 65px;
			background: rgba(10, 10, 10, 0.1);
			display: flex;
			justify-content: space-around;
			align-items: center;
			border-top: 1px solid #333;
			backdrop-filter: blur(15px);
			z-index: 9999;
			padding-bottom: env(safe-area-inset-bottom); /* 아이폰 하단 바 대응 */

			max-width: 900px;margin: 0 auto;
		}

		.nav-item {
			flex: 1;
			text-align: center;
			text-decoration: none;
			color: #666;
			display: flex;
			flex-direction: column;
			align-items: center;
			font-size: 11px;
			transition: all 0.3s;
		}

		.nav-item i {
			font-size: 18px;
			margin-bottom: 4px;
		}

		/* 현재 활성화된 메뉴 스타일 */
		.nav-item.active {
			color: #d4af37; /* Gold */
		}

		/* 클릭 효과 */
		.nav-item:active {
			transform: scale(0.9);
			opacity: 0.7;
		}

		/* 본문 내용이 하단 바에 가려지지 않도록 여백 추가 */
		body { padding-bottom: 80px !important; }

		/* 인쇄 시에만 적용되는 스타일 */
		@media print {
			/* 하단 네비게이션 바 숨김 */
			.bottom-nav, 
			/* 인쇄/PDF 버튼 숨김 */
			button, 
			/* 필터 박스(날짜 선택기) 숨김 */
			.filter-box,
			/* 상단 메뉴(header_b.html에 포함된 네비게이션 등) 숨김 */
			header, 
			nav,
			.header-b-container { /* header_b.html의 클래스명에 따라 조정 필요 */
				display: none !important;
			}

			/* 인쇄 시 배경색과 글자색 최적화 */
			body {
				background: #fff !important;
				color: #000 !important;
				padding-bottom: 0 !important;
			}

			.container {
				max-width: 100% !important;
				margin: 0 !important;
				padding: 0 !important;
			}

			.balance-card, .mini-card, .item-list {
				border: 1px solid #ccc !important;
				background: #fff !important;
				box-shadow: none !important;
				color: #000 !important;
			}
			
			.val { color: #000 !important; }
			
			/* 차트 막대의 색상이 인쇄되도록 설정 (브라우저 설정에 따라 다를 수 있음) */
			.comparison-container {
				-webkit-print-color-adjust: exact;
				print-color-adjust: exact;
				border: 1px solid #000;
			}

			.no-print { display: none !important; }
		}
    </style>
</head>
<body>
<div class="container">
		<div class="header">
			<div style="font-size:16px; font-weight: 700;color:#999;">수지 결산 보고서</div>
			<div style="font-size:14px; font-weight: 700;color:#888; margin-top:5px;"><?= $start_date ?> ~ <?= $end_date ?></div>
		</div>

		<div class="balance-card">
			<div class="label">현재 기간 순수익(잔액)</div>
			<div class="val" style="font-size: 28px; color: <?= $balance >= 0 ? 'var(--gold)' : 'var(--red)' ?>;">
				₩ <?= number_format($balance) ?>
			</div>
			<div style="font-size:11px; color:#999; margin-top:8px;">
				지출 비율: <?= number_format($exp_rate, 1) ?>% (수입 대비)
			</div>
		</div>

		<div class="filter-box">
			<form method="GET" class="flex-row">
				<input type="date" name="start_date" value="<?= $start_date ?>"  class="search-box" style="flex:1; margin-bottom:0;">
				<input type="date" name="end_date" value="<?= $end_date ?>"  class="search-box" style="flex:1; margin-bottom:0;">
				<button type="submit" class="btn-search">조회</button>
			</form>
		</div>

		<div class="summary-grid">
			<div class="mini-card" style="border-left: 4px solid var(--blue);">
				<div class="label">총 수입</div>
				<div class="val" style="color:var(--blue);">₩ <?= number_format($inc_total) ?></div>
			</div>
			<div class="mini-card" style="border-left: 4px solid var(--red);">
				<div class="label">총 지출</div>
				<div class="val" style="color:var(--red);">₩ <?= number_format($exp_total) ?></div>
			</div>
		</div>

		<div class="section-title"><i class="fa fa-balance-scale"></i> 수입 대비 지출 비중</div>
		<div class="comparison-container">
			<?php 
			$total_sum = $inc_total + $exp_total;
			$inc_p = ($total_sum > 0) ? ($inc_total / $total_sum) * 100 : 50;
			$exp_p = ($total_sum > 0) ? ($exp_total / $total_sum) * 100 : 50;
			?>
			<div class="inc-bar" style="width: <?= $inc_p ?>%;">수입 <?= round($inc_p) ?>%</div>
			<div class="exp-bar" style="width: <?= $exp_p ?>%;">지출 <?= round($exp_p) ?>%</div>
		</div>

		<div class="section-title" style="color:var(--blue);"><i class="fa fa-arrow-down"></i> 수입 상세 항목</div>
		<div class="item-list">
			<?php foreach($inc_data as $row): ?>
			<div class="item-row">
				<span><?= $row['item_name'] ?></span>
				<span style="font-weight:bold;">₩ <?= number_format($row['total']) ?></span>
			</div>
			<?php endforeach; ?>
		</div>

		<div class="section-title" style="color:var(--red);"><i class="fa fa-arrow-up"></i> 지출 상세 항목</div>
		<div class="item-list">
			<?php foreach($exp_data as $row): ?>
			<div class="item-row">
				<span><?= $row['item_name'] ?></span>
				<span style="font-weight:bold;">₩ <?= number_format($row['total']) ?></span>
			</div>
			<?php endforeach; ?>
		</div>

		<div class="no-print" style="text-align:center; padding:20px;">
			<button onclick="window.print()" style="background:#333; color:#fff; border:none; padding:8px 15px; border-radius:5px; font-size:12px;">
				<i class="fa fa-print"></i> 보고서 인쇄/PDF 저장
			</div>
		</div>

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

</body>
</html>