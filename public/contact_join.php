<?php
include_once '../lib/cumas_common.php';

/**
 * ローカル関数
 */

/**
 * ローカルクラス
 */
class JoinPDO extends CuMAS_PDO
{
    /**
     * ジョブの連結を行う
     *
     **/
    public function joinJobs($srcId, $destId)
    {
        // 連結元が残っている事をチェック
        if (!$this->getJobDataByCoId($srcId)) {
            throw new JoinPDOException("他のユーザによって、お問い合わせ情報が変更されました。");
        }
        // 連結先情報の検索
        $sql = 'SELECT co_child_no,co_ma_id,ca_id from contact_tab WHERE co_parent = ?'
             . ' ORDER BY co_child_no DESC NULLS LAST';
        $destData = $this->fetchAll($sql, [$destId]);
        if (!$destData) {
            throw new JoinPDOException("連結先のお問い合わせ情報が存在しません。");
        }

        // 親ジョブの場合nullだが、+1するとint型になる
        $nextChild = $destData[0]['co_child_no'] + 1;

        $sql = "UPDATE contact_tab"
             . " SET co_parent = ?, co_ma_id = ?, ca_id = ?"
             . "     , co_child_no = CASE WHEN co_child_no IS NULL"
             . "                               THEN $nextChild"
             . "                          ELSE co_child_no + $nextChild END"
             . " WHERE co_parent = ?";

        $this->execute($sql, [
                              $destId, 
                              $destData[0]['co_ma_id'],
                              $destData[0]['ca_id'],
                              $srcId
                             ]);

        $sql = "UPDATE contact_mail_tab"
             . " SET co_id = ?"
             . " WHERE co_id = ?";
        $this->execute($sql, [$destId, $srcId]);
    }

}
class JoinPDOException extends Exception { }

/**
 * mainの処理
 */
// セッションからjob情報の取り出し
$srcJob = $session->getTargetJob();
if (!is_array($srcJob)
    || !is_numeric($srcJob['id'])
    || !is_numeric($srcJob['joinTo'])
    || $srcJob['id'] == $srcJob['joinTo']) {

    $session->unsetTargetJob();
    $session->set('message','不正な画面アクセスです。');
    header('location: contact_search_result.php');
    exit;
}

try {
    $db = JoinPDO::getInstance($config);
} catch (PDOException $e) {
    Cumas_Exception::log_s($logFacility, __FILE__, $e->getMessage());
    Cumas_Exception::printErr();
    exit;
}
/*
 * OKボタン
 */
if (filter_input(INPUT_POST, 'joinButton')) {
    try {
        $db->lockTable(['contact_tab', 'contact_mail_tab', 'category_tab']);
        $db->joinJobs($srcJob['id'], $srcJob['joinTo']);
        $db->commit();
        $session->set('message', "お問い合わせ番号 [$srcJob[id]] を [$srcJob[joinTo]] に結合しました。");
        $session->unsetTargetJob();
        $_POST = NULL;
        header('location: contact_search_result.php');
        exit;
    } catch (PDOException $e) {
        // DBエラー
        empty($db) ?: $db->rollBack();
        Cumas_Exception::log_s($logFacility, __FILE__, $e->getMessage());
        Cumas_Exception::printErr();
        exit;
    } catch (JoinPDOException $je) {
        // 指定されたジョブが無かった等
        empty($db) ?: $db->rollBack();
        $session->set('message', $je->getMessage());
        $_POST = NULL;
        header('location: contact_detail.php');
        exit;
    }
}


/*
 * 画面表示パート
 */
try {
    $destJobData = $db->getJobDataByCoId($srcJob['joinTo']);
    if (!$destJobData) {
        $session->set('message', "連結先のお問い合わせ情報が存在しません。");
        // ここではunsetしないように！
        //$session->unsetTargetJob();	/* しない */
        header('location: contact_detail.php');
        exit;
    }

    $srcJobData = $db->getJobDataByCoId($srcJob['id']);
    if (!$srcJobData) {
        $session->set('message', "お問い合わせ情報が存在しません。");
        $_POST = NULL;
        $session->unsetTargetJob();
        header('location: contact_search_result.php');
        exit;
    }

    $view->assign("destJob", $destJobData);
    $view->assign("srcJob", $srcJobData);

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

/* End of file contact_delete.php */
