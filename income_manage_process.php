<?
include  "./lib/config.php";


$action = $_GET['action'] ?? '';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$date = $_GET['date'] ?? $_POST['date'] ?? date("Y-m-d");

// 결과 처리 후 관리탭으로 복귀시키는 함수
function goBack($date) {
    echo "<script>location.href='income_main.php?date=$date&tab=manage';</script>";
}

// 1. 수정 (미확정 상태일 때만)
if ($action == 'edit' && $id > 0) {
    $amount = intval($_GET['amount']);
    $conn->query("UPDATE income_records SET amount = $amount  WHERE church_id = '$church_id' AND id = $id AND is_fixed = 0");
    goBack($date);
}

// 2. 삭제 (미확정 상태일 때만)
if ($action == 'delete' && $id > 0) {
    $conn->query("DELETE FROM income_records WHERE church_id = '$church_id' AND  id = $id AND is_fixed = 0");
    goBack($date);
}

// 3. 선택 확정
if ($action == 'select_fix') {
    $ids = $_POST['select_ids'] ?? [];
    if (!empty($ids)) {
        $id_list = implode(',', array_map('intval', $ids));
        $conn->query("UPDATE income_records SET is_fixed = 1 WHERE church_id = '$church_id' AND  id IN ($id_list)");
    }
    goBack($date);
}

// 4. 확정 취소 (핵심 요구사항)
if ($action == 'undo_fix' && $id > 0) {
    $conn->query("UPDATE income_records SET is_fixed = 0 WHERE church_id = '$church_id' AND  id = $id");
    goBack($date);
}

$conn->close();
?>