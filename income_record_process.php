<?
include  "./lib/config.php";


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $item_code   = isset($_POST['item_code']) ? intval($_POST['item_code']) : 0;
    $income_date = $_POST['income_date'];
    $season_name = $_POST['season_name'];
    
    // 중요: 교인별 금액이 배열(amounts[ID])로 들어옵니다.
    $amounts     = isset($_POST['amounts']) ? $_POST['amounts'] : null; 

    $success_count = 0;

    // 1. 수입 항목(칩)이 선택되었는지 확인
    if ($item_code > 0 && is_array($amounts)) {
        
        foreach ($amounts as $member_id => $amount) {
            $member_id = intval($member_id);
            $amount    = intval($amount);

            // 2. 금액이 0보다 큰 경우에만 DB에 저장 (입력 안 한 사람은 건너뜀)
            if ($amount > 0) {
                $sql = "INSERT INTO income_records (church_id, member_id, item_code, amount, income_date, season_name) 
                        VALUES ('$church_id', $member_id, $item_code, $amount, '$income_date', '$season_name')";
                
                if ($conn->query($sql)) {
                    $success_count++;
                }
            }
        }

        if ($success_count > 0) {
            echo "<script>alert('총 {$success_count}건의 기록이 저장되었습니다.'); location.href='income_main.php';</script>";
        } else {
            echo "<script>alert('금액이 입력된 항목이 없습니다.'); history.back();</script>";
        }
        
    } else {
        // 항목을 선택하지 않았을 때 발생하는 에러 방지
        echo "<script>alert('상단에서 수입 항목(칩)을 먼저 선택해주세요.'); history.back();</script>";
    }
}
$conn->close();
?>