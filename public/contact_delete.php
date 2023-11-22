<?php
include_once '../lib/cumas_common.php';

/**
 * ローカル関数
 */
function deleteMailFile($mailIds, $config) {
    foreach ($mailIds as $ma_id) {
        $path = sprintf("%s/%02d/%d"
                        , $config->mailsavedir, $ma_id % 100, $ma_id);

        if (!@unlink($path)) {
            CuMAS_Exception::log_s(
            $config->syslogfascility,
            __FILE__, "Failed to delete Mail File '$path'");
        }
    }
}

/**
 * ローカル関数
 */
function deleteAttachFile($attachIds) 
{
    foreach ($attachIds as $path) {
        if (!@unlink($path)) {
            CuMAS_Exception::log_s(
                $config->syslogfascility,
                __FILE__, "Failed to delete Attachment File '$path'"
            );
        }
    }
}

/**
 * ローカルクラス
 */
class CDel_PDO extends CuMAS_PDO
{
    /**
     * contact_tabからの削除は複数回走る可能性があるため、
     * preparedステートメントを保持する
     */
    private $ctabDelStmt;

    /**
     * contact_tabから指定された1件のジョブを削除
     */
    public function deleteFromContactTab($jobId) {
        if (empty($this->ctabDelStmt)) {
            $this->ctabDelStmt = $this->_pdo
                            ->prepare("DELETE FROM contact_tab WHERE co_id=?");
        }

        $this->ctabDelStmt->execute([$jobId]);
    }

    public function deleteAttachTab($ma_id)
    {
        /* にat_idの一覧 */
        $arr_at_del = array();

        /* ファイル削除のためにat_idの一覧を取得 */
        $sql = "SELECT at_filepath from attach_tab WHERE at_mailid = ?";
        $stmt = $this->_pdo->prepare($sql);
        $stmt->execute([$jobId]);
        $del_at_id = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

        foreach ($del_at_id as $tmp) {
            $arr_at_del[] = $tmp["at_filepath"];
        }

        $cmtabStmt = $this->_pdo
                    ->prepare("DELETE FROM attach_tab WHERE at_mailid = ?");
        $cmtabStmt->execute([$ma_id]);

        return $arr_at_del;
    }

    /**
     * attach_tab, mail_tabとcontact_mail_tabから情報を削除する。
     * 削除したメールIDの一覧を返す
     *
     * @return array
     **/
    public function deleteMailData($jobId)
    {
        // ファイル削除のためにma_idの一覧を取得
        $sql = "SELECT ma_id from contact_mail_tab WHERE co_id = ?";
        $delmailstmt = $this->_pdo->prepare($sql);
        $delmailstmt->execute([$jobId]);
        $delmails = $delmailstmt->fetchAll(PDO::FETCH_COLUMN, 0);

        // 先にひも付けテーブルを削除
        $cmtabStmt = $this->_pdo
                    ->prepare("DELETE FROM contact_mail_tab WHERE co_id=?");
        $cmtabStmt->execute([$jobId]);

        $this->deleteFromContactTab($jobId);

        // 何らかの異常でメールが紐付いていなくても動作するように
        if (empty($delmails)) {
            return $delmails;
        }

        /* 添付ファイルのfilepath */
        $arr_at_filepath = array();
 
        /* attach_tabから削除 */
        foreach ($delmails as $ma_id_del) {
            $tmp_at_filepath = $this->deleteAttachTab($ma_id_del);
            $arr_at_filepath = array_merge($arr_at_filepath, $tmp_at_filepath);
        }

        // ma_tabから削除
        $mailCondition = implode(' or '
            , array_map(function ($s) {return "ma_id = $s";}
                        , $delmails));
        $mtabStmt = $this->_pdo->prepare("DELETE FROM mail_tab WHERE "
                                         . $mailCondition);
        $mtabStmt->execute();

        return array(
             "del_mail"   => $delmails,
             "del_attach" => $arr_at_filepath
        );
    }

}


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

// 削除ボタン
if (filter_input(INPUT_POST, 'deleteButton')) {
    try {
        $db = CDel_PDO::getInstance($config);
        $db->lockTable(['contact_tab', 'contact_mail_tab', 'mail_tab']);
        // これ以降投げられる例外はすべてcatch内でロールバックされる

        $jobData = $db->getJobDataByCoId($curJob['id']);

        // 削除権限をチェック
        if (!$session->isAdmin()) {
            // 担当者がいない => NG
            // or (= 担当者がいて)、自分ではない => NG
            if ($jobData['co_us_id'] === null
                || $jobData['co_us_id'] != $session->getLoginUserData('us_id'))
            {
                throw new CuMAS_Exception('このお問い合わせ情報を削除する権限がありません。');
            }
        }

        // 親ジョブだったらサブジョブをサーチ
        if (!$jobData['co_child_no']) {
            $subjobIds = $db->getSubjobByParentId($jobData['co_id']);
        }

        // サブジョブを先に消す
        if (!empty($subjobIds)) {
            // チェックがなくてサブジョブがあったらエラー
            if (!filter_input(INPUT_POST, 'forceDelete')) {
                throw new CuMAS_Exception('サブジョブが存在するため、削除できません。');
            }
            // チェックがあってサブジョブがあったらサブジョブを全て削除
            foreach ($subjobIds as $oneJob) {
                $db->deleteFromContactTab($oneJob);
            }
        }

        // 対象のジョブを削除
        if (!$jobData['co_child_no']) {
            $delData = $db->deleteMailData($jobData['co_id']);
            deleteMailFile($delData["del_mail"], $config);	/* Only LOG when failed */
            deleteAttachFile($delData["del_attach"]);	/* Only LOG when failed */
        } else {
            $db->deleteFromContactTab($jobData['co_id']);
        }

        // コミットして メッセージをセットして検索結果画面へ
        $db->commit();
        $deleteMessage = $jobData['co_child_no']
            ? "サブジョブ \"[$jobData[co_parent].$jobData[co_child_no]]$jobData[ma_subject]\" を削除しました。"
            : "お問い合わせ \"[$jobData[co_id]]$jobData[ma_subject]\" 、および関連するメールデータを削除しました。";
        $session->unsetTargetJob();
        $session->set('message', $deleteMessage);
        $_POST = NULL;
        header('location: contact_search_result.php');
        exit;

    } catch (PDOEXCEPTION $e) {
        empty($db) ?: $db->rollBack();
        Cumas_Exception::log_s($logFacility, __FILE__, $e->getMessage());
        Cumas_Exception::printErr();
        exit;
    } catch (CuMAS_Exception $ce) {
        empty($db) ?: $db->rollBack();
        $view->assign('message', $ce->getMessage());
    }
}

/*
 * 画面表示パート
 */
try {
    $db = CDel_PDO::getInstance($config);
    // 削除ボタン押下時は既に取得されている
    if (!isset($jobData)) {
        $jobData = $db->getJobDataByCoId($curJob['id']);
    }
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

/* End of file contact_delete.php */
