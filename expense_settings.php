<?
include  "./lib/config.php";
include "./inc/header_b.html"; 


//권한체크
if($_SESSION['admin_id']!="super"){		//슈퍼관리자가 아니라면

	include "./lib/auth_check.php";
	check_access("재정관리", $my_perms); // 권한이 없으면 자동으로 튕겨냄
}


$target_year = isset($_GET['year']) ? intval($_GET['year']) : date("Y");


// 1. 추가 및 수정 처리 (POST 방식)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $_POST['action'] == 'save') {
    
    $item_code = intval($_POST['item_code']);
    $item_name = $conn->real_escape_string($_POST['item_name']);

    if ($item_code > 0 && !empty($item_name)) {
        // 코드가 중복되면 이름을 업데이트하고, 없으면 새로 삽입
        $sql = "INSERT INTO account_items (church_id, target_year, item_type, item_code, item_name) 
                VALUES ('$church_id', $target_year, 'EXPENSE', $item_code, '$item_name')
                ON DUPLICATE KEY UPDATE item_name = '$item_name'";

        if ($conn->query($sql)) {
            echo "<script>alert('저장되었습니다.');</script> ";
        } else {
            echo "오류 발생: " . $conn->error;
        }
    } else {
        echo "<script>alert('코드와 항목명을 정확히 입력해주세요.'); history.back();</script>";
    }
}


// 2. 삭제 처리 (GET 방식)
if (isset($_GET['action']) && $_GET['action'] == 'delete') {
    
    $item_code = intval($_GET['code']);

    if ($item_code > 0) {
        $sql = "DELETE FROM account_items 
                WHERE church_id = '$church_id' 
                AND target_year = $target_year 
                AND item_code = $item_code
				AND item_type = 'EXPENSE'
				";

        if ($conn->query($sql)) {
            echo "<script>alert('삭제되었습니다.');</script>";
        } else {
            echo "삭제 오류: " . $conn->error;
        }
    }
}

if (isset($_GET['action']) && $_GET['action'] == 'delete') {
    $item_code = intval($_GET['code']);
    $sql = "DELETE FROM account_items WHERE church_id = '$church_id' AND target_year = $target_year AND item_code = $item_code AND item_type = 'EXPENSE'";
    if ($conn->query($sql)) {
        echo "<script>location.href='expense_settings.php';</script>";
    }
}

// 지출(EXPENSE) 항목만 불러오기
$result = $conn->query("SELECT * FROM account_items WHERE target_year = $target_year AND church_id = '$church_id' AND item_type = 'EXPENSE' ORDER BY item_code ASC");

?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>지출 항목 설정</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { --gold: #d4af37; --bg: #0a0a0a; --card: #161616; --border: #333; --red: #ff4d4d; --blue: #3498db; }
        .container { max-width: 900px; margin: 0 auto; padding-bottom: 120px !important; }
        
        .header { background: #000; padding: 15px; text-align: center; border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 100; }
        .header h1 { margin: 0; font-size: 17px; color: var(--blue); } /* 지출은 파란색 포인트 */

        .input-section { padding: 15px; background: var(--card); border-bottom: 1px solid var(--border); position: sticky; top: 51px; z-index: 90; }
        .input-group { display: flex; gap: 8px; align-items: center; }
        .input-group input { 
            background: #000; border: 1px solid var(--border); color: #fff; 
            padding: 12px; border-radius: 8px; font-size: 14px; outline: none;
        }
        .input-code { width: 60px; text-align: center; }
        .input-name { flex: 1; }
        
        .btn-submit { 
            background: var(--blue); color: #fff; border: none; 
            width: 45px; height: 45px; border-radius: 8px; font-weight: bold; cursor: pointer;
            display: flex; align-items: center; justify-content: center; font-size: 18px;
        }

        .item-list { padding: 10px; }
        .item-card { 
            background: var(--card); border: 1px solid var(--border); border-radius: 12px; 
            margin-bottom: 8px; display: flex; align-items: center; padding: 12px 15px;
        }
        
        .summary-card { border-left: 4px solid var(--blue); background: #141a1e; }
        
        .item-code { font-family: 'monospace'; font-weight: bold; color: var(--blue); width: 45px; font-size: 15px; }
        .item-info { flex: 1; font-size: 15px; cursor: pointer; }
        
        .btn-delete { color: #555; padding: 10px; font-size: 16px; }
        .btn-delete:active { color: var(--red); }

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

		.nav-item { flex: 1; padding: 15px; text-align: center; color: #888; text-decoration: none; font-size: 12px; }
		.nav-item.active { color: var(--blue); }
    </style>
</head>
<body>
<div class="container">
		<div class="header">
			<h1><i class="fa-solid fa-file-invoice-dollar"></i> <?= $target_year ?> 지출 항목 설정</h1>
		</div>

		<div class="input-section">
			<form action="expense_settings.php" method="POST">
				<input type="hidden" name="action" value="save">
				<div class="input-group">
					<input type="number" name="item_code" id="item_code" class="input-code" placeholder="코드" required>
					<input type="text" name="item_name" id="item_name" class="input-name" placeholder="지출 항목명 입력" required>
					<button type="submit" class="btn-submit">
						<i class="fa-solid fa-plus"></i>
					</button>
				</div>
			</form>
		</div>

		<div class="item-list">
			<?php if($result->num_rows > 0): ?>
				<?php while($row = $result->fetch_assoc()): 
					$is_summary = ($row['item_code'] % 100 == 0);
				?>
				<div class="item-card <?= $is_summary ? 'summary-card' : '' ?>">
					<div class="item-code"><?= $row['item_code'] ?></div>
					<div class="item-info" onclick="editItem('<?= $row['item_code'] ?>', '<?= $row['item_name'] ?>')">
						<?= htmlspecialchars($row['item_name']) ?>
						<?php if($is_summary): ?><span style="font-size:10px; color:var(--blue); margin-left:5px;">[대분류]</span><?php endif; ?>
					</div>
					<div class="btn-delete" onclick="confirmDelete('<?= $row['item_code'] ?>', '<?= htmlspecialchars($row['item_name']) ?>')">
						<i class="fa-solid fa-xmark"></i>
					</div>
				</div>
				<?php endwhile; ?>
			<?php else: ?>
				<div style="text-align:center; padding:50px; color:#444;">등록된 지출 항목이 없습니다.</div>
			<?php endif; ?>
		</div>

</div>

<div class="bottom-nav">
    <a href="income_settings.php" class="nav-item"><i class="fa-solid fa-hand-holding-dollar"></i><br>수입설정</a>
    <a href="expense_settings.php" class="nav-item active"><i class="fa-solid fa-money-bill-transfer"></i><br>지출설정</a>
    <!-- <a href="export.php?type=excel&item_type=EXPENSE" class="nav-item"><i class="fa-solid fa-file-excel"></i><br>엑셀저장</a>
    <a href="export.php?type=txt&item_type=EXPENSE" class="nav-item"><i class="fa-solid fa-file-lines"></i><br>TXT저장</a> -->
	<a href="income_main.php" class="nav-item "> <i class="fa-solid fa-file-lines"></i><br>돌아가기</a>
</div>

<script>
function editItem(code, name) {
    document.getElementById('item_code').value = code;
    document.getElementById('item_name').value = name;
    document.getElementById('item_name').focus();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function confirmDelete(code, name) {
    if(confirm(`[지출항목 삭제]\n${code} : ${name}\n정말 삭제하시겠습니까?`)) {
        location.href = `expense_settings.php?action=delete&code=${code}`;
    }
}
</script>

</body>
</html>