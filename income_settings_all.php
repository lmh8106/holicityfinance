<?
include  "./lib/config.php";
include "./inc/header_b.html"; 

$target_year = isset($_GET['year']) ? intval($_GET['year']) : date("Y");


error_reporting(E_ALL);
ini_set('display_errors', 1);


// 1. 항목 저장/수정 로직
if (isset($_POST['save_item'])) {
    $item_code = $_POST['item_code'];
    // $conn (연결 객체) 확인
    $item_name = $conn->real_escape_string($_POST['item_name']);
    $item_type = $_POST['item_type']; // INCOME or EXPENSE
    $category_name = $conn->real_escape_string($_POST['category_name']); 
    $target_year = $_POST['target_year'];

    // 중복 체크 및 저장 (REPLACE INTO는 기존 코드가 있으면 UPDATE, 없으면 INSERT)
    $sql = "REPLACE INTO account_items (item_code, church_id, item_name, item_type, category_name, target_year) 
            VALUES ($item_code, '$church_id', '$item_name', '$item_type', '$category_name', $target_year)";
    
    if ($conn->query($sql)) {
        header("Location: income_settings_all.php?year=$target_year");
        exit;
    } else {
        echo "저장 에러: " . $conn->error;
    }
}

// 2. 삭제 로직
if (isset($_GET['del_code'])) {
    $code = $_GET['del_code'];
    $year = $_GET['year'];
    $conn->query("DELETE FROM account_items WHERE item_code = $code AND target_year = $year AND  church_id = '$church_id'");
    header("Location: income_settings_all.php?year=$year");
    exit;
}

$selected_year = isset($_GET['year']) ? $_GET['year'] : date("Y");

// 3. 리스트 로드
$list_sql = "SELECT * FROM account_items WHERE target_year = $selected_year AND  church_id = '$church_id' ORDER BY item_type DESC, item_code ASC";
$result = $conn->query($list_sql);
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>계정 항목 설정</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { --gold: #d4af37; --bg: #0a0a0a; --card: #161616; --border: #333; --blue: #3498db; --red: #ff4d4d; }
        body { background: var(--bg); color: #fff; font-family: 'Pretendard', -apple-system, sans-serif; margin: 0; padding-bottom: 100px; }
        
        .header { padding: 20px; text-align: center; background: #000; border-bottom: 1px solid var(--border); }
        .container { padding: 15px; }

        /* 입력 폼 카드 */
        .settings-card { background: var(--card); border: 1px solid var(--gold); border-radius: 15px; padding: 20px; margin-bottom: 25px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 12px; color: #888; margin-bottom: 5px; }
        .form-control { width: 100%; background: #000; border: 1px solid #444; color: #fff; padding: 12px; border-radius: 8px; box-sizing: border-box; outline: none; font-size: 14px; }
        .form-control:focus { border-color: var(--gold); }
        
        .btn-save { width: 100%; background: var(--gold); color: #000; border: none; padding: 15px; border-radius: 10px; font-weight: bold; font-size: 15px; cursor: pointer; transition: 0.2s; }
        .btn-save:active { transform: scale(0.98); opacity: 0.8; }

        /* 필터 탭 스타일 */
        .filter-tabs { display: flex; gap: 5px; margin-bottom: 15px; background: #111; padding: 5px; border-radius: 10px; border: 1px solid var(--border); }
        .filter-btn { flex: 1; padding: 10px; border: none; background: none; color: #666; font-size: 13px; font-weight: bold; cursor: pointer; border-radius: 7px; transition: 0.2s; }
        .filter-btn.active { background: var(--gold); color: #000; }

        /* 리스트 테이블 */
        .item-table { width: 100%; border-collapse: collapse; background: var(--card); border-radius: 12px; overflow: hidden; border: 1px solid var(--border); }
        .item-table th { background: #1f1f1f; padding: 12px; font-size: 12px; color: #888; text-align: left; }
        .item-table td { padding: 15px 12px; border-bottom: 1px solid #222; font-size: 14px; }
        .type-badge { font-size: 10px; padding: 3px 6px; border-radius: 4px; font-weight: bold; }
        .badge-inc { background: rgba(52, 152, 219, 0.2); color: var(--blue); }
        .badge-exp { background: rgba(231, 76, 60, 0.2); color: var(--red); }
        
        .category-tag { font-size: 11px; color: var(--gold); background: rgba(212, 175, 55, 0.1); padding: 2px 6px; border-radius: 4px; border: 1px solid rgba(212, 175, 55, 0.3); }
        .badge-btn { border: none; background: none; cursor: pointer; font-size: 16px; transition: 0.2s; }
        .badge-btn:active { transform: scale(1.2); }

        /* 하단 네비게이션 */
        .bottom-nav { position: fixed; bottom: 0; left: 0; right: 0; height: 65px; background: rgba(0,0,0,0.9); display: flex; border-top: 1px solid #333; backdrop-filter: blur(10px); z-index: 1000; padding-bottom: env(safe-area-inset-bottom); }
        .nav-item { flex: 1; text-align: center; text-decoration: none; color: #555; display: flex; flex-direction: column; justify-content: center; align-items: center; font-size: 10px; }
        .nav-item i { font-size: 20px; margin-bottom: 4px; }
        .nav-item.active { color: var(--gold); }
    </style>
</head>
<body>

<div class="header">
    <h3><i class="fa-solid fa-gears"></i> 계정 항목 관리 (<?= $selected_year ?>)</h3>
</div>

<div class="container">
    <div class="settings-card">
        <form method="POST">
            <input type="hidden" name="target_year" value="<?= $selected_year ?>">
            
            <div class="form-group">
                <label>구분</label>
                <select name="item_type" class="form-control" id="f_type">
                    <option value="INCOME">수입 (INCOME)</option>
                    <option value="EXPENSE">지출 (EXPENSE)</option>
                </select>
            </div>

            <div class="form-group">
                <label>카테고리 (팀명/목적 그룹)</label>
                <input type="text" name="category_name" id="f_category" class="form-control" placeholder="예: 선교팀, 교육부, 운영비" required>
                <small style="color:#555; font-size:11px;">* 동일한 카테고리 이름을 입력하면 나중에 수지가 자동 합산됩니다.</small>
            </div>

            <div style="display:flex; gap:10px;">
                <div class="form-group" style="flex:1;">
                    <label>항목 코드</label>
                    <input type="number" name="item_code" id="f_code" class="form-control" placeholder="예: 101" required>
                </div>
                <div class="form-group" style="flex:2;">
                    <label>항목 이름</label>
                    <input type="text" name="item_name" id="f_name" class="form-control" placeholder="예: 선교헌금, 목적지출" required>
                </div>
            </div>

            <button type="submit" name="save_item" class="btn-save">항목 저장 / 업데이트</button>
        </form>
    </div>

    <div style="font-size:13px; color:#888; margin-bottom:10px; font-weight:bold; display: flex; justify-content: space-between;">
        <span>등록된 계정 목록</span>
        <span><?= $result->num_rows ?>건</span>
    </div>

    <div class="filter-tabs">
        <button type="button" class="filter-btn active" onclick="filterType('ALL', this)">전체</button>
        <button type="button" class="filter-btn" onclick="filterType('INCOME', this)">수입만</button>
        <button type="button" class="filter-btn" onclick="filterType('EXPENSE', this)">지출만</button>
    </div>

    <table class="item-table" id="itemTable">
        <thead>
            <tr>
                <th>구분/코드</th>
                <th>카테고리 & 항목명</th>
                <th style="text-align:right;">관리</th>
            </tr>
        </thead>
        <tbody>
            <?php if($result->num_rows == 0): ?>
            <tr><td colspan="3" style="text-align:center; padding:50px; color:#444;">등록된 항목이 없습니다.</td></tr>
            <?php else: ?>
                <?php while($row = $result->fetch_assoc()): ?>
                <tr class="item-row" data-type="<?= $row['item_type'] ?>">
                    <td>
                        <span class="type-badge <?= $row['item_type'] == 'INCOME' ? 'badge-inc' : 'badge-exp' ?>">
                            <?= $row['item_type'] == 'INCOME' ? '수입' : '지출' ?>
                        </span>
                        <div style="font-size:12px; color:#555; margin-top:5px;"><?= $row['item_code'] ?></div>
                    </td>
                    <td>
                        <span class="category-tag"><?= $row['category_name'] ?: '미지정' ?></span>
                        <div style="font-weight:bold; margin-top:5px;"><?= $row['item_name'] ?></div>
                    </td>
                    <td style="text-align:right;">
                        <button class="badge-btn" style="color:var(--blue);" 
                                onclick="editItem('<?= $row['item_type'] ?>', '<?= $row['category_name'] ?>', '<?= $row['item_code'] ?>', '<?= $row['item_name'] ?>')">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                        <a href="?del_code=<?= $row['item_code'] ?>&year=<?= $selected_year ?>" 
                           style="color:var(--red); margin-left:12px; text-decoration:none;" onclick="return confirm('정말 삭제하시겠습니까?')">
                            <i class="fa-solid fa-trash-can"></i>
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="bottom-nav">
    <a href="income_main.php" class="nav-item">
        <i class="fa-solid fa-hand-holding-dollar"></i>
        <span>수입관리</span>
    </a>
    <a href="expense_main.php" class="nav-item">
        <i class="fa-solid fa-receipt"></i>
        <span>지출관리</span>
    </a>
    <a href="report_annual.php" class="nav-item">
        <i class="fa-solid fa-chart-line"></i>
        <span>연간분석</span>
    </a>
    <a href="report_total.php" class="nav-item">
        <i class="fa-solid fa-scale-balanced"></i>
        <span>수지결산</span>
    </a>
    <a href="income_settings.php" class="nav-item active">
        <i class="fa-solid fa-gear"></i>
        <span>설정</span>
    </a>
</div>

<script>
// 리스트 필터링 기능
function filterType(type, btn) {
    // 버튼 활성화 클래스 처리
    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    const rows = document.querySelectorAll('.item-row');
    rows.forEach(row => {
        if (type === 'ALL') {
            row.style.display = 'table-row';
        } else {
            // 데이터 속성값과 비교하여 노출 여부 결정
            if (row.getAttribute('data-type') === type) {
                row.style.display = 'table-row';
            } else {
                row.style.display = 'none';
            }
        }
    });
}

// 수정 버튼 클릭 시 폼에 데이터 채워넣기
function editItem(type, cat, code, name) {
    document.getElementById('f_type').value = type;
    document.getElementById('f_category').value = cat;
    document.getElementById('f_code').value = code;
    document.getElementById('f_name').value = name;
    
    // 화면 상단 폼으로 부드럽게 이동
    window.scrollTo({ top: 0, behavior: 'smooth' });
    
    // 시각적 피드백: 버튼 일시적 강조
    const btn = document.querySelector('.btn-save');
    btn.style.boxShadow = "0 0 20px rgba(212, 175, 55, 0.6)";
    setTimeout(() => { btn.style.boxShadow = "none"; }, 1000);
}
</script>

</body>
</html>