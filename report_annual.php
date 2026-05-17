<?
include  "./lib/config.php";
include "./inc/header_b.html"; 

$church_id = "CHURCH_001"; // 세션 연동 필요

error_reporting(E_ALL);
ini_set('display_errors', 1);

// 1. 기간 및 항목 필터 설정
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date("Y-01-01");
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date("Y-m-d");
$filter_item = isset($_GET['item_code']) ? intval($_GET['item_code']) : '';
$is_all = isset($_GET['is_all']) ? $_GET['is_all'] : ''; // 전체보기 필터 추가
$year = date("Y", strtotime($start_date));

// 2. 기초 데이터 로드
$items_list_res = $conn->query("SELECT * FROM account_items WHERE target_year=$year AND item_type='INCOME' AND item_code % 100 != 0 ORDER BY item_code ASC");

// --- 교인 리스트 필터링 로직 ---
$member_where = " WHERE 1=1 "; 
if ($is_all !== 'on') { 
    $member_where .= " AND attendance_type = '출석'"; // 기본값: 출석 교인만
}
$members_list_res = $conn->query("SELECT id, name, job_title FROM church_members $member_where ORDER BY name ASC");

// 3. 상세 내역 전체 로드
$where_clause = "WHERE r.income_date BETWEEN '$start_date' AND '$end_date' AND r.is_fixed = 1";
if($filter_item) $where_clause .= " AND r.item_code = $filter_item";

$sql_all = "SELECT r.*, m.name as member_name, i.item_name, MONTH(r.income_date) as mon
            FROM income_records r
            LEFT JOIN church_members m ON r.member_id = m.id
            LEFT JOIN account_items i ON r.item_code = i.item_code AND i.target_year = $year
            $where_clause ORDER BY r.income_date ASC";
$res_all = $conn->query($sql_all);
$details_list = [];
while($row = $res_all->fetch_assoc()) { $details_list[] = $row; }
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>개인별/전체 통합 보고서</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { --gold: #d4af37; --bg: #0a0a0a; --card: #161616; --border: #333; --blue: #3498db; --green: #2ecc71; }
        .container { max-width: 900px; margin: 0 auto; padding-bottom: 120px !important; }
        
        .report-header { background: #000; padding: 25px 15px; border-bottom: 1px solid var(--border); text-align: center; }
        .total-label { font-size: 12px; color: #888; margin-bottom: 5px; }
        .total-amount { font-size: 26px; font-weight: bold; color: var(--gold); transition: all 0.3s; }

        .filter-section { padding: 15px; background: #111; margin: 15px; border-radius: 12px; border: 1px solid var(--border); }
        .input-row { display: flex; gap: 8px; margin-bottom: 10px; }
        input, select { flex: 1; background: #222; border: 1px solid #444; color: #fff; padding: 12px; border-radius: 8px; font-size: 13px; outline: none; }
        .btn-search { background: var(--gold); border: none; padding: 12px 20px; border-radius: 8px; font-weight: bold; cursor: pointer; }

        .section-card { background: var(--card); margin: 0 15px 20px; border-radius: 15px; padding: 18px; border: 1px solid var(--border); }
        h3 { font-size: 14px; margin: 0 0 18px; color: #888; display: flex; justify-content: space-between; align-items: center; }
        .mode-badge { font-size: 10px; padding: 2px 8px; border-radius: 10px; background: #333; color: #fff; }

        /* 차트 UI */
        .chart-row { display: flex; align-items: center; margin-bottom: 12px; gap: 10px; }
        .label-text { width: 45px; font-size: 12px; color: #aaa; }
        .bar-outer { flex: 1; height: 10px; background: #222; border-radius: 5px; position: relative; }
        .bar-inner { height: 100%; background: var(--gold); border-radius: 5px; transition: width 0.5s ease; width: 0; }
        .amount-val { width: 90px; text-align: right; font-size: 13px; font-weight: bold; color: var(--gold); cursor: pointer; }

        /* 상세 테이블 */
        #detailTableSection { display: none; margin-top: 20px; border-top: 1px solid #333; padding-top: 20px; }
        .detail-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .detail-table th { color: #555; text-align: left; padding: 8px; border-bottom: 1px solid #222; }
        .detail-table td { padding: 12px 8px; border-bottom: 1px solid #1a1a1a; }

        .search-box { 
            background: #222; border: 1px solid #444; color: #fff; padding: 10px 20px; border-radius: 25px; font-size: 14px; outline: none; text-align: center;
        }
        .search-box:focus { border-color: var(--gold); }

        .search-box[type="date"] { color-scheme: dark; }
        .search-box[type="date"]::-webkit-calendar-picker-indicator {
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="16" height="15" viewBox="0 0 24 24" fill="white"><path d="M20 3h-1V1h-2v2H7V1H5v2H4c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 18H4V8h16v13z"/></svg>');
            filter: invert(0); cursor: pointer;
        }

        .bottom-nav {
            position: fixed; bottom: 0; left: 0; right: 0; height: 65px; background: rgba(10, 10, 10, 0.1);
            display: flex; justify-content: space-around; align-items: center; border-top: 1px solid #333; backdrop-filter: blur(15px); z-index: 9999;
            padding-bottom: env(safe-area-inset-bottom); max-width: 900px; margin: 0 auto;
        }
        .nav-item { flex: 1; text-align: center; text-decoration: none; color: #666; display: flex; flex-direction: column; align-items: center; font-size: 11px; transition: all 0.3s; }
        .nav-item i { font-size: 18px; margin-bottom: 4px; }
        .nav-item.active { color: #d4af37; }
        .nav-item:active { transform: scale(0.9); opacity: 0.7; }

        body { padding-bottom: 80px !important; background: var(--bg); color: #fff; }

        /* 전체인원 보기 버튼 스타일 */
        .all-toggle-btn {
            background: #333; color: #fff; border: 1px solid #444; padding: 5px 10px; border-radius: 6px; font-size: 11px; cursor: pointer; display: flex; align-items: center; gap: 5px;
        }
        .all-toggle-btn.active { border-color: var(--gold); color: var(--gold); }
    </style>
</head>
<body>
<div class="container">
        <div class="report-header">
            <div class="total-label" id="reportTitle">전체 수입 합계</div>
            <div class="total-amount" id="mainTotal">₩ 0</div>
        </div>

        <div class="filter-section">
            <form method="GET" id="filterForm">
                <input type="hidden" name="is_all" id="is_all_val" value="<?= $is_all ?>">
                <div class="input-row">
                    <input type="date" name="start_date" value="<?= $start_date ?>" class="search-box" style="flex:1;">
                    <input type="date" name="end_date" value="<?= $end_date ?>" class="search-box" style="flex:1;">
                </div>
                <div class="input-row">
                    <select name="item_code">
                        <option value="">모든 항목</option>
                        <?php $items_list_res->data_seek(0); while($it = $items_list_res->fetch_assoc()): ?>
                            <option value="<?= $it['item_code'] ?>" <?= $filter_item == $it['item_code'] ? 'selected' : '' ?>><?= $it['item_name'] ?></option>
                        <?php endwhile; ?>
                    </select>
                    <button type="submit" class="btn-search">조회</button>
                </div>
            </form>
            
            <div style="margin-top:10px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                    <span style="font-size:12px; color:#888;">교인 선택</span>
                    <button type="button" class="all-toggle-btn <?= $is_all == 'on' ? '' : 'active' ?>" onclick="toggleAllMembers()">
						<i class="fa-solid <?= $is_all == 'on' ? 'fa-eye-slash' : 'fa-eye' ?>"></i> 출석인원만
                    </button>
                </div>
                <select id="memberFilter" onchange="updateDashboard(this.value)" style="border-color: var(--blue);">
                    <option value="">교인별 검색 (전체보기)</option>
                    <?php while($m = $members_list_res->fetch_assoc()): ?>
                        <option value="<?= $m['name'] ?>"><?= $m['name'] ?> (<?= $m['job_title'] ?>)</option>
                    <?php endwhile; ?>
                </select>
				
				
            </div>
        </div>

        <div class="section-card">
            <h3>월별 추이 <span class="mode-badge" id="monthBadge">전체</span></h3>
            <div id="monthChartContainer"></div>
        </div>

        <div class="section-card">
            <h3>항목별 비중 <span class="mode-badge" id="itemBadge">전체</span></h3>
            <div id="itemChartContainer"></div>

            <div id="detailTableSection">
                <div id="tableTitle" style="font-size:12px; color:var(--gold); margin-bottom:10px; font-weight:bold;"></div>
                <table class="detail-table">
                    <thead>
                        <tr><th>날짜</th><th>성함</th><th>항목</th><th style="text-align:right;">금액</th></tr>
                    </thead>
                    <tbody id="detailList"></tbody>
                </table>
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

<script>
const allData = <?= json_encode($details_list) ?>;

window.onload = () => updateDashboard('');

// 전체 인원 토글 함수
function toggleAllMembers() {
    const isAll = document.getElementById('is_all_val');
    isAll.value = (isAll.value === 'on') ? '' : 'on';
    document.getElementById('filterForm').submit();
}

function updateDashboard(memberName) {
    const filtered = memberName ? allData.filter(d => d.member_name === memberName) : allData;
    const total = filtered.reduce((sum, row) => sum + parseInt(row.amount), 0);
    
    document.getElementById('mainTotal').innerText = '₩ ' + total.toLocaleString();
    document.getElementById('reportTitle').innerText = memberName ? `[${memberName}] 님 수입 합계` : "전체 수입 합계";
    document.getElementById('monthBadge').innerText = memberName ? "개인" : "전체";
    document.getElementById('itemBadge').innerText = memberName ? "개인" : "전체";

    renderMonthChart(filtered);
    renderItemChart(filtered);
    document.getElementById('detailTableSection').style.display = 'none';
}

function renderMonthChart(data) {
    const container = document.getElementById('monthChartContainer');
    const monthlyMap = {};
    for(let i=1; i<=12; i++) monthlyMap[i] = 0;
    data.forEach(d => monthlyMap[d.mon] += parseInt(d.amount));

    const vals = Object.values(monthlyMap);
    const maxVal = Math.max(...vals) || 1;
    container.innerHTML = '';

    Object.keys(monthlyMap).forEach(mon => {
        if(monthlyMap[mon] === 0) return;
        const pct = (monthlyMap[mon] / maxVal) * 100;
        container.innerHTML += `
            <div class="chart-row">
                <div class="label-text">${mon}월</div>
                <div class="bar-outer"><div class="bar-inner" style="width:${pct}%"></div></div>
                <div class="amount-val" onclick="showTable('month', '${mon}', '${mon}월 내역', '${document.getElementById('memberFilter').value}')">
                    ${Number(monthlyMap[mon]).toLocaleString()}
                </div>
            </div>`;
    });
}

function renderItemChart(data) {
    const container = document.getElementById('itemChartContainer');
    const itemMap = {};
    data.forEach(d => {
        itemMap[d.item_name] = (itemMap[d.item_name] || 0) + parseInt(d.amount);
    });

    const sortedItems = Object.entries(itemMap).sort((a,b) => b[1] - a[1]);
    const maxVal = sortedItems.length > 0 ? sortedItems[0][1] : 1;
    container.innerHTML = '';

    sortedItems.forEach(([name, val]) => {
        const pct = (val / maxVal) * 100;
        container.innerHTML += `
            <div class="chart-row">
                <div class="label-text" style="width:70px;">${name}</div>
                <div class="bar-outer"><div class="bar-inner" style="width:${pct}%; background:var(--blue)"></div></div>
                <div class="amount-val" onclick="showTable('item', '${name}', '${name} 내역', '${document.getElementById('memberFilter').value}')">
                    ${Number(val).toLocaleString()}
                </div>
            </div>`;
    });
}

function showTable(type, key, title, memberName) {
    const list = document.getElementById('detailList');
    const section = document.getElementById('detailTableSection');
    
    let filtered = memberName ? allData.filter(d => d.member_name === memberName) : allData;
    filtered = (type === 'month') ? filtered.filter(d => d.mon == key) : filtered.filter(d => d.item_name === key);

    list.innerHTML = '';
    filtered.forEach(row => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td style="color:#666;">${row.income_date.substring(5)}</td>
            <td><strong>${row.member_name}</strong></td>
            <td style="color:var(--gold);">${row.item_name}</td>
            <td style="text-align:right;">${Number(row.amount).toLocaleString()}</td>
        `;
        list.appendChild(tr);
    });

    document.getElementById('tableTitle').innerText = (memberName ? `[${memberName}] ` : "") + title;
    section.style.display = 'block';
    section.scrollIntoView({ behavior: 'smooth' });
}
</script>
</body>
</html>