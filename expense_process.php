<?
include  "./lib/config.php";

// GET 또는 POST로 전달된 action과 date를 수신
$action = isset($_GET['action']) ? $_GET['action'] : '';
$date = isset($_REQUEST['date']) ? $_REQUEST['date'] : date("Y-m-d");

// [1] 일괄 등록 (Bulk Add)
if($action == 'bulk_add') {
    $expense_date = $_POST['expense_date'];
    if(isset($_POST['amounts'])) {
        foreach($_POST['amounts'] as $item_code => $amt) {
            $amt = intval($amt);
            if($amt > 0) {
                $target = $conn->real_escape_string($_POST['targets'][$item_code]);
                // 기본 미확정(is_fixed = 0)으로 저장
                $conn->query("INSERT INTO expense_records (church_id,expense_date, item_code, target_name, amount, is_fixed) 
                             VALUES ('$church_id','$expense_date', $item_code, '$target', $amt, 0)");
            }
        }
    }
    header("Location: expense_main.php?tab=manage&date=$expense_date");
    exit;
}

// [2] 확정 처리 (Fix)
if($action == 'fix') {
    $id = intval($_GET['id']);
    $conn->query("UPDATE expense_records SET is_fixed = 1 WHERE church_id = '$church_id' AND  id = $id");
} 

// [3] 확정 취소 (Unfix) - 이 부분이 누락되었는지 확인하세요!
if($action == 'unfix') {
    $id = intval($_GET['id']);
    // is_fixed를 다시 0으로 변경
    $conn->query("UPDATE expense_records SET is_fixed = 0 WHERE church_id = '$church_id' AND  id = $id");
}

// [4] 삭제 (Delete)
if($action == 'del') {
    $id = intval($_GET['id']);
    $conn->query("DELETE FROM expense_records WHERE church_id = '$church_id' AND  id = $id");
}

// 작업 완료 후 원래 날짜의 관리 탭으로 복귀
header("Location: expense_main.php?tab=manage&date=$date");
exit;
?>