<?php
require_once '../lib/cumas_common.php';

/**
 * ローカル関数
 */

/**
 * ローカルクラス
 */
/**
 * この画面でのDB連携を取り仕切る
 */
class DetachPDO extends CuMAS_PDO
{
    /**
     * 切り離しもとジョブのID
     * 他のジョブにある関連するメールを拾わないために使う
     *
     * @var string
     **/
    var $targetJob;

    /**
     * 分離する根っこのメールの情報
     *
     * @var array
     **/
    var $rootMailData;

    /**
     * 分離するルートのメールの情報を取得
     */
    public function getDetachRoot($rootMaId, $targetJob)
    {
        $this->targetJob = $targetJob;

        $sql = "SELECT ma_id, ma_message_id, ma_reference_id, ma_date"
             . ", ma_from_addr, ma_subject"
             . " FROM mail_tab"
             . " WHERE ma_id = ?";
        $this->rootMailData = $this->fetchAll($sql, [$rootMaId])[0];
        if (!$this->rootMailData) {
            throw new DetachPDOException("指定されたメールの情報が存在しません。");
        }
    }

    /**
     * 分離するメールツリーを取得
     */
    public function getMailTree()
    {
        $items = [
            'rootId' => $this->rootMailData['ma_id'],
            'target' => $this->targetJob,
        ];

        $sql = "WITH RECURSIVE r AS ("
             . " SELECT * FROM mail_tab WHERE ma_id=:rootId"
             . " UNION ALL"
             . "  SELECT mail_tab.* FROM r, mail_tab"
             . "  JOIN contact_mail_tab ON mail_tab.ma_id = contact_mail_tab.ma_id"
             . "  WHERE mail_tab.ma_reference_id = r.ma_message_id"
             . "  AND co_id = :target";

        if ($this->rootMailData['ma_reference_id']) {
            // ROOTが親を持っていたら、無限ループをケアしておく
            $sql .= "  AND mail_tab.ma_message_id <> :parent";
            $items['parent'] = $this->rootMailData['ma_reference_id'];
        }

        $sql .= ") SELECT * FROM r ORDER BY ma_id";

        $this->mailList = $this->fetchAll($sql, $items);
    }

    /*
     * 取得したデータがループしていたらエラー
     */
    public function checkLoop()
    {
        // rootが親を持っていなかったら絶対にループしない
        if (!$parent = $this->rootMailData['ma_reference_id']) {
            return;
        }

        // 検索結果にrootの親が入っていたらループだったということ
        foreach ($this->mailList as $mail) {
            if ($mail['ma_message_id'] == $parent) {
                throw new DetachPDOException("メールデータが破損しているため、切り離せません。");
            }
        }
    }


    /**
     * 切り離しによって新規ジョブを生成
     */
    public function makeNewJob()
    {
        $sql = "INSERT INTO contact_tab"
             . " (co_inquiry,co_lastupdate,co_ma_id,ca_id)"
             . " VALUES"
             . " (?,?,?,(SELECT ca_id from contact_tab where co_id = ?))"
             . " RETURNING co_id";

        // ma_idでソートしているので、先頭が一番もとのメールのはず
        $mail = $this->mailList[0];
        $items = [
            $mail['ma_date'],
            $mail['ma_date'],
            $mail['ma_id'],
            $this->targetJob,
        ];
        $stmt = $this->_pdo->prepare($sql);
        $stmt->execute($items);
        $newId = $stmt->fetch()['co_id'];

        $sql = "UPDATE contact_tab SET co_parent = $newId WHERE co_id = $newId";
        $this->_pdo->query($sql);
        $this->newId = $newId;
    }

    /*
     * ひも付けテーブルを更新
     */
    public function updateCMtab()
    {
        if (empty($this->newId)) {
            throw new PDOEXCEPTION('何か使い方が間違っています！');
        }

        foreach ($this->mailList as $mail) {
            $mailsToMove[] = "ma_id=" . $mail['ma_id'];
        }

        $sql = "UPDATE contact_mail_tab SET co_id = {$this->newId} WHERE ";
        $sql .= implode(' or ', $mailsToMove);

        $this->_pdo->query($sql);
    }
}
class DetachPDOException extends Exception
{
}

/**
 * mainの処理
 */
// セッションからjob情報の取り出し
$curJob = $session->getTargetJob();
if (!is_array($curJob)
    || !is_numeric($curJob['id'])
    || !is_numeric($curJob['parent'])
    || !is_numeric($curJob['detachMail'])
) {
    $session->unsetTargetJob();
    $session->set('message', '不正な画面アクセスです。');
    header('location: contact_search_result.php');
    exit;
}

// メールデータ取得
try {
    $db = DetachPDO::getInstance($config);
    $db->getDetachRoot($curJob['detachMail'], $curJob['parent']);

    $view->assign("mail", $db->rootMailData);

} catch (PDOEXCEPTION $e) {
    Cumas_Exception::log_s($logFacility, __FILE__, $e->getMessage());
    Cumas_Exception::printErr();
    exit;
} catch (DetachPDOException $de) {
    $session->set('message', $de->getMessage());
    $_POST = null;
    header('location: contact_search_result.php');
    exit;
}


/*
 * OKボタン
 */
if (filter_input(INPUT_POST, 'ok')) {
    try {
        $db->lockTable(['contact_tab', 'contact_mail_tab', 'category_tab']);
        $db->getMailTree();
        $db->checkLoop();    // 切り離し対象メールがループしている可能性

        // 新規ジョブを作成
        $db->makeNewJob();
        // ひも付けテーブルを更新
        $db->updateCMtab();

        $db->commit();

        $newJob['id'] = $db->newId;
        $newJob['subj'] = $db->mailList[0]['ma_subject'];
        $session->set('message', "新しいお問い合わせ\"[$newJob[id]] $newJob[subj]\" を作成しました。");
        $_POST = null;
        header('location: contact_search_result.php');
        exit;

    } catch (PDOEXCEPTION $e) {
        empty($db) ?: $db->rollBack();
        Cumas_Exception::log_s($logFacility, __FILE__, $e->getMessage());
        Cumas_Exception::printErr();
        exit;
    } catch (DetachPDOException $de) {
        empty($db) ?: $db->rollBack();
        $session->set('message', $de->getMessage());
        $_POST = null;
        header('location: contact_detail.php');
        exit;
    }
}



/*
 * 画面表示パート
 */
try {
    $view->display();
} catch (CuMAS_Exception $e) {
    $e->log($logFacility, __FILE__);
    $e->printErr();
    exit;
}

/* End of file contact_delete.php */
