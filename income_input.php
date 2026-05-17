<?
include  "./lib/config.php";
include "./inc/header_b.html"; 



$target_year = isset($_GET['year']) ? intval($_GET['year']) : date("Y");

error_reporting(E_ALL);
ini_set('display_errors', 1);

$today = date("Y-m-d");

// 1. 수입 항목 (칩 선택용)
$items = $conn->query("SELECT * FROM account_items  WHERE church_id = '$church_id' AND target_year=$target_year AND item_type='INCOME' AND item_code % 100 != 0 ORDER BY item_code ASC");

// 2. 교인 명단 (입력 리스트용)
$members = $conn->query("SELECT id, name, job_title FROM church_members WHERE church_id = '$church_id' ORDER BY name ASC");
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>교인별 수입 일괄 입력</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { --gold: #d4af37; --bg: #0a0a0a; --card: #161616; --border: #333; }
        body { background: var(--bg); color: #fff; font-family: 'Pretendard', sans-serif; margin: 0; padding-bottom: 100px; }
        
        .header { background: #000; padding: 15px; text-align: center; border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 100; }
        .header h1 { margin: 0; font-size: 17px; color: var(--gold); }

        .form-container { padding: 15px; }
        .section-title { font-size: 14px; color: #888; margin: 15px 0 10px; font-weight: bold; }

        /* 검색창 스타일 */
        .search-box-wrap { margin-bottom: 15px; position: relative; }
        .search-input { 
            width: 100%; background: #222; border: 1px solid var(--border); 
            color: #fff; padding: 12px 12px 12px 40px; border-radius: 10px; font-size: 14px; outline: none; box-sizing: border-box;
        }
        .search-box-wrap i { position: absolute; left: 15px; top: 15px; color: #666; }

        /* 항목 선택 칩 */
        .scroll-wrapper { display: flex; gap: 8px; overflow-x: auto; padding: 5px 0 15px; }
        .item-chip { flex: 0 0 auto; padding: 10px 18px; background: var(--card); border: 1px solid var(--border); border-radius: 20px; font-size: 13px; cursor: pointer; color: #888; }
        .item-chip.active { background: var(--gold); color: #000; border-color: var(--gold); font-weight: bold; }

        /* 교인별 입력 리스트 */
        .member-row { 
            background: var(--card); border: 1px solid var(--border); border-radius: 12px; 
            display: flex; align-items: center; padding: 12px 15px; margin-bottom: 8px;
        }
        .member-info { flex: 1; }
        .member-name { font-size: 15px; font-weight: bold; }
        .member-job { font-size: 11px; color: #666; margin-left: 5px; }
        
        .amount-input-box { 
            width: 120px; background: #000; border: 1px solid var(--border); 
            color: var(--gold); padding: 10px; border-radius: 8px; text-align: right; 
            font-size: 16px; font-weight: bold; outline: none;
        }
        .amount-input-box:focus { border-color: var(--gold); }

        .season-box { display: flex; gap: 8px; margin-bottom: 15px; }
        .season-input { flex: 1; background: #111; border: 1px solid var(--border); color: #fff; padding: 12px; border-radius: 8px; font-size: 14px; }

        .btn-submit { 
            position: fixed; bottom: 20px; left: 15px; right: 15px; 
            background: var(--gold); color: #000; border: none; padding: 18px; 
            border-radius: 12px; font-size: 17px; font-weight: bold; cursor: pointer; z-index: 1000;
        }
    </style>
</head>
<body>

<div class="header">
    <h1><i class="fa-solid fa-pen-to-square"></i> 일괄 금액 입력</h1>
</div>

<div class="form-container">
    <form id="bulkForm" action="income_record_process.php" method="POST">
        
        <div class="section-title">날짜 및 시즌</div>
        <div class="season-box">
            <input type="date" name="income_date" value="<?= $today ?>" class="season-input">
            <select name="season_name" class="season-input">
                <option value="2026-1분기">2026-1분기</option>
                <option value="부활절">부활절</option>
            </select>
        </div>

        <div class="section-title">수입 항목 선택</div>
        <div class="scroll-wrapper">
            <input type="hidden" name="item_code" id="selected_item">
            <?php while($item = $items->fetch_assoc()): ?>
                <div class="item-chip" onclick="selectItem(this, '<?= $item['item_code'] ?>')">
                    <?= $item['item_name'] ?>
                </div>
            <?php endwhile; ?>
        </div>

        <div class="section-title">교인 검색 및 금액 입력</div>
        <div class="search-box-wrap">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" id="nameSearch" class="search-input" placeholder="이름을 입력하세요..." onkeyup="filterNames()">
        </div>

        <div class="member-list" id="memberList">
            <?php while($m = $members->fetch_assoc()): ?>
                <div class="member-row" data-name="<?= htmlspecialchars($m['name']) ?>">
                    <div class="member-info">
                        <span class="member-name"><?= htmlspecialchars($m['name']) ?></span>
                        <span class="member-job"><?= htmlspecialchars($m['job_title']) ?></span>
                    </div>
                    <div>
                        <input type="number" name="amounts[<?= $m['id'] ?>]" class="amount-input-box" placeholder="0">
                    </div>
                </div>
            <?php endwhile; ?>
        </div>

        <button type="submit" class="btn-submit">선택 항목 일괄 저장</button>
    </form>
</div>

<script>
// 이름 필터링 함수
function filterNames() {
    let input = document.getElementById('nameSearch').value.toLowerCase();
    let rows = document.querySelectorAll('.member-row');

    rows.forEach(row => {
        let name = row.getAttribute('data-name').toLowerCase();
        if (name.includes(input)) {
            row.style.display = "flex";
        } else {
            row.style.display = "none";
        }
    });
}

function selectItem(el, code) {
    document.querySelectorAll('.item-chip').forEach(c => c.classList.remove('active'));
    el.classList.add('active');
    document.getElementById('selected_item').value = code;
}

document.getElementById('bulkForm').onsubmit = function() {
    if(!document.getElementById('selected_item').value) {
        alert('수입 항목을 먼저 선택해주세요.');
        return false;
    }
    return true;
};
</script>

</body>
</html>