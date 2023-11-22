<?php
include_once '../lib/cumas_common.php';



/**
 * メインの処理
 */
// セッションからjob情報の取り出し
$curJob = $session->getTargetJob();
if (!is_array($curJob) || !is_numeric($curJob['id'])) {
    $session->unsetTargetJob();
    $session->set('message','不正な画面アクセスです。');
    header('location: contact_search_result.php');
    exit;
}

// サブジョブ作成ボタン
if (filter_input(INPUT_POST, 'makeSubSubmit')) {
    try {
        $db = CuMAS_PDO::getInstance($config);
        $db->lockTable(['contact_tab', 'category_tab']);
        $parentData = $db->getJobDataByCoId($curJob['id']);

        // 現在のサブジョブ番号の取得
        $sql = 'SELECT co_child_no from contact_tab'
             . ' WHERE co_parent = ?'
             . ' ORDER BY co_child_no DESC NULLS LAST';
        $maxChild = $db->fetchAll($sql, [$curJob['parent']])[0]['co_child_no'];
        $maxChild++;	/* NULLの場合(int)1になる */

        $sql = "INSERT INTO contact_tab"
            . " (co_inquiry, co_lastupdate, co_operator, co_parent"
            . " , co_child_no, co_ma_id, ca_id)"
            . " VALUES"
            . " (:inquiry, current_timestamp"
            . ", :operator, :parent, :child_no, :ma_id, :ca_id)";

        $params = [
            'inquiry' => $parentData['co_inquiry'],
            'operator' => $session->getLoginUserData('us_id'),
            'parent' => $parentData['co_id'],
            'child_no' => $maxChild,
            'ma_id' => $parentData['co_ma_id'],
            'ca_id' => $parentData['ca_id'],
        ];

        $db->execute($sql, $params);

        $db->commit();
        $session->set('message',
            "新しいサブジョブ [$parentData[co_id].$maxChild] を作成しました。");

        $session->unsetTargetJob();
        header('location: contact_search_result.php');
        exit;						/* 正常終了 */

    } catch (PDOEXCEPTION $e) {
        empty($db) ?: $db->rollBack();
        Cumas_Exception::log_s($logFacility, __FILE__, $e->getMessage());
        Cumas_Exception::printErr();
        exit;
    }
}


try {
    $db = CuMAS_PDO::getInstance($config);
    $jobData = isset($parentData) ? $parentData
                                  : $db->getJobDataByCoId($curJob['id']);

    // 存在しない
    if (!$jobData) {
        $session->set('message', "指定されたお問い合わせ情報が存在しません。");
        $_POST = NULL;
        header('location: contact_search_result.php');
        exit;
    }

    $view->assign("job", $jobData);

} catch (PDOEXCEPTION $e) {
    Cumas_Exception::log_s($logFacility, __FILE__, $e->getMessage());
    Cumas_Exception::printErr();
    exit;
}



try {
    $view->display();
} catch (CuMAS_Exception $e) {
    $e->log($logFacility, __FILE__);
    $e->printErr();
    exit;
}

/* End of file contact_make_sub.php */
