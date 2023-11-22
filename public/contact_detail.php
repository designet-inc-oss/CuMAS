<?php
include_once '../lib/cumas_common.php';
include_once '../lib/libutil';

/**
 * ページローカルクラス・関数
 */
/**
 * CuMAS_PDOのカスタマイズ
 */
class CD_PDO extends CuMAS_PDO
{
    public function getUsMail($login_us_id)
    {
        $sql = "SELECT us_mail FROM user_tab WHERE us_id = ?";
        $stmt = $this->_pdo->prepare($sql);
        $stmt->execute(array($login_us_id));
        return $stmt->fetchColumn();
    }

    public function getLatestData($co_id)
    {
        $sql = "SELECT co_us_id, co_status, co_lastupdate, co_parent,"
             . " co_child_no"
             . " FROM contact_tab WHERE co_id = ?";
        $s = $this->_pdo->prepare($sql);
        $s->execute(array($co_id));
        return $s->fetch();
    }

    public function updateJob($co_id, $post, $original, $incomplete)
    {
        // prepared statementに確定で埋める値
        $items = [
                     'status' => $post['status'],
                     'co_id' => $co_id,
                     'comment' => $post['comment'],
                 ];
        $sql = "UPDATE contact_tab"
             . " SET co_status = :status"
             . ", co_lastupdate = current_timestamp"
             . ", co_comment = :comment";

        // 対応予定日は既に値チェックされている
        if (is_numeric($post['limit']['Year'])) {
            $dateStr = $post['limit']['Year']
                     . "-" . $post['limit']['Month']
                     . "-" . $post['limit']['Day'];
            $sql .= ", co_limit = '$dateStr'";
        } else {
            // Yearが数字でないということは、----/--/--が指定されたということ
            $sql .= ", co_limit = null";
        }
        // 対応開始日時を変更すべきかどうか
        if ($original["co_status"] == 0 && $post['status'] != 0) {
            $sql .= ", co_start = current_timestamp";
        }
        // 完了日時を変更すべきかどうか
        if (in_array($original['co_status'], $incomplete)
            && !in_array($post['status'], $incomplete)) {
            $sql .= ", co_complete = current_timestamp";
        }

        // 担当者情報が渡っていたら修正する
        // 管理者ならば0～の整数が、そうでないならnullがPOSTされる
        if (is_numeric($post['us_id'])) {
            $items['us_id'] = $post['us_id'] ?: null;	// 0をセットしない!!!
            $sql .= ", co_us_id = :us_id";
        } elseif (!$original['co_us_id']) {
            // 元の担当者が空だったら、編集者を担当者にセットする
            $sql .= ", co_us_id = :us_id";
            $items['us_id'] = $_SESSION['userData']['us_id'];
        }

        $sql .= " WHERE co_id = :co_id";
        $upStmt = $this->_pdo->prepare($sql);
        $upStmt->execute($items);

    }

}	/* end of CD_PDO */


/**
 * 入力値チェックを行うためのローカルクラス
 */
class JobValidator
{
    public function __construct($postData)
    {
        $this->status = (int)$postData['status'];
        $this->year = (int)$postData['limit']['Year'];
        $this->month = (int)$postData['limit']['Month'];
        $this->day = (int)$postData['limit']['Day'];
        $this->comment = $postData['comment'];
    }

    public function checkLimit()
    {
        // 全て空欄ならそれはOK
        if ($this->month + $this->day + $this->year == 0) {
            return $this;
        }

        if (!checkdate($this->month, $this->day, $this->year)) {
            throw new CuMAS_Exception("対応予定日の指定が不正です。");
        }

        $limit = strtotime($this->year . "-" . $this->month . "-" . $this->day);
        // 今日の朝0時00分
        if (strtotime('midnight') > $limit) {
            throw new CuMAS_Exception("対応予定日に過去の日付は指定できません。");
        }

        return $this;
    }

    public function checkComment()
    {
        if (strlen($this->comment) > 2048) {
            throw new CuMAS_Exception("備考の文字数が不正です。");
        }

        return $this;
    }
}

/**
 * 連結ボタン押下時の処理を扱う
 */
class JoinHandler
{
    public $sess;
    public $job;

    public function __construct($sess, $job)
    {
        $this->sess = $sess;
        $this->job = $job;
    }

    /**
     * 連結先指定欄の入力値チェック
     *
     * @param string 入力値
     */
    public function validator($num)
    {
        if ($this->job['child_no']) {
            throw new JoinHandlerException("サブジョブだけを他のジョブに連結させることはできません。");
        }
        if (!is_numeric($num)) {
            throw new JoinHandlerException("連結先のお問い合わせIDは半角数字で指定して下さい。");
        }
        if (strpbrk($num, '.')) {
            throw new JoinHandlerException("サブジョブを連結先に指定することはできません。");
        }
        if ($num == $this->job['id']) {
            throw new JoinHandlerException("自分自身に連結させることは出来ません。");
        }
    }

    /**
     * ユーザが削除権を持つかどうかを判定する
     **/
    public function checkUser()
    {
        // 管理者ならOK
        if ($this->sess->isAdmin()) {
            return;
        }

        // 以下は管理者ではない場合

        if (empty($this->job['us_id'])) {
            throw new JoinHandlerException("このお問い合わせ情報を連結させる権限がありません。");
        }
        if ($this->job['us_id'] != $this->sess->getLoginUserData('us_id')) {
            throw new JoinHandlerException("このお問い合わせ情報を連結させる権限がありません。");
        }

        return;
    }
}

class JoinHandlerException extends Exception {}

/**
 * 分離ボタン処理
 */
class DetachHandler
{
    public function validator($id)
    {
        if (empty($id) || !is_numeric($id)) {
            throw new DetachHandlerException("独立させるメールを選択して下さい。");
        }

        $this->targetId = $id;
    }

    public function checkRootMail($rootMail)
    {
        if ($this->targetId == $rootMail) {
            throw new DetachHandlerException("1通目のメールは切り離せません。");
        }
    }
}
class DetachHandlerException extends Exception {}

/*
 * ローカルクラス・関数ここまで
 */

/**
 * main処理
 */

/* 遅延通知メールから直接ジョブ指定でアクセスする時は、GETが来る */
$getId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($getId) {
    $session->setTargetJob(['id' => $getId]);
}

/* なんにせよSESSIONからIDを取得。 */
$curJob = $session->getTargetJob();
if (!is_array($curJob) || !is_numeric($curJob['id'])) {
    $session->unsetTargetJob();
    $session->set('message', '不正な画面アクセスです。');
    header('location: contact_search_result.php');
    exit;
}


/* - note -
 * 以下、$curJobの存在は信頼してよい
 */

/* POSTの取得 */
$formList = [
    "us_id"         => FILTER_VALIDATE_INT,
    "status"        => FILTER_VALIDATE_INT,
    "limit"         => [ 'flags' => FILTER_REQUIRE_ARRAY ],
    "comment"       => FILTER_DEFAULT,
    "selectedMail"  => FILTER_VALIDATE_INT,
    "sort"          => FILTER_VALIDATE_INT,
    "updateButton"  => FILTER_DEFAULT,
    "returnButton"  => FILTER_DEFAULT,
    "target_at_id"  => FILTER_VALIDATE_INT,
    "download"      => FILTER_DEFAULT,
    "sendmail"      => FILTER_DEFAULT,
];
$postData = filter_input_array(INPUT_POST, $formList);

/**
 * 戻るボタン処理
 */
if ($postData['returnButton']) {
    $session->unsetTargetJob();
    $_POST = null;
    header('location: contact_search_result.php');
    exit;
}

/**
 * サブジョブ作成ボタン
 * サブジョブのサブジョブは作れないようにする
 */
if (filter_input(INPUT_POST, 'subjobButton')) {
    if (!$curJob['child_no']) {
        $_POST = null;
        header('location: contact_make_sub.php');
        exit;
    }
    $view->assign('message', 'サブジョブのサブジョブは作成できません。');
}

/**
 * ジョブ連結ボタン
 */
if (filter_input(INPUT_POST, 'joinButton')) {
    try {
        $join = new JoinHandler($session, $curJob);

        $join->validator(filter_input(INPUT_POST, 'joinTo'));
        $join->checkUser();

        $session->setTargetJob('joinTo', $_POST['joinTo']);
        $_POST = null;
        header('location: contact_join.php');
        exit;
    } catch (JoinHandlerException $e) {
        $view->assign('message', $e->getMessage());
    }
}

/**
 * 分割ボタン
 */
if (filter_input(INPUT_POST, 'detach')) {
    try {
        $detach = new DetachHandler();
        $detach->validator(filter_input(INPUT_POST, 'selectedMail'));
        $detach->checkRootMail($session->getTargetJob()['rootMaId']);

        $session->setTargetJob('detachMail', $detach->targetId);
        $_POST = null;
        header('location: contact_detach.php');
        exit;

    } catch (DetachHandlerException $e) {
        $view->assign('message', $e->getMessage());
    }
}

/**
 * データ更新処理ここから
 */
if ($postData['updateButton']) {
    try {
        /* 入力値チェック */
        $updater = new JobValidator($postData);
        $updater->checkLimit()->checkComment();

        $db = CD_PDO::getInstance($config);
        // トランザクション開始
        $db->lockTable('contact_tab');

        /* 更新処理の衝突チェック */
        $original = $db->getLatestData($curJob['id']);

        if ($original['co_lastupdate'] != $curJob['lastupdate']) {
            $db->rollBack();
            // 更新合戦に負けたら編集中のデータは消える
            unset($postData['status'], $postData['comment'], $postData['limit']);
            throw new CuMAS_Exception("このジョブ情報は他のユーザによって変更されました。");
        }

        /* contact_tabを更新 */
        $db->updateJob($curJob['id'], $postData, $original, $config->incomplete);

        $db->commit();
        $session->set(
            'message',
            sprintf(
                'お問い合わせ No.%s を更新しました。',
                $original['co_child_no'] ?
                $original['co_parent'] . '.' . $original['co_child_no'] :
                $curJob['id']
            )
        );
        $session->unsetTargetJob();
        $_POST = null;
        header('location: contact_search_result.php');
        exit;                                             /* 正常終了 */
    } catch (PDOEXCEPTION $e) {
        empty($db) ?: $db->rollBack();
        Cumas_Exception::log_s($logFacility, __FILE__, $e->getMessage());
        $session->unsetTargetJob();
        Cumas_Exception::printErr();
        exit;
    } catch (CuMAS_Exception $e) {
        /*
         * 入力値チェック or 編集合戦に負けた時にthrowされる。
         * 後者の場合にはその時点でrollback()されている。
         */
        $view->message = $e->getMessage();
    }
} else if ($postData["sendmail"]) {
    $view->message = "独立させるメールを選択して下さい。";
}

/**
 * ここから画面表示ルーチン
 */
/*
 * 対応予定日、備考に関しては、POSTされた値があればそれを再表示する。
 */
if (isset($postData['comment'])) {
    $view->comment = $postData['comment'];
}

if (isset($postData['limit'])) {
    // Smartyが日付を要求するのでダミーの日付をセットする
    $view->limitYear = is_numeric($postData['limit']['Year']) ?
        "{$postData['limit']['Year']}-01-01" : null;
    $view->limitMonth = is_numeric($postData['limit']['Month']) ?
        "1999-{$postData['limit']['Month']}-01" : null;
    $view->limitDay = is_numeric($postData['limit']['Day']) ?
        "1999-01-{$postData['limit']['Day']}" : null;
}

// hiddenなどをここでセット
$view->assign("post", $postData);
#var_dump($session->get('message'));

/*
 * 画面表示パート
 */
try {
    $db = CD_PDO::getInstance($config);

    // 担当者とステータス一覧の表示
    // (権限によるスイッチはテンプレートで行う)
    $view->assign("user_tab", $db->getActiveUsers());
    $view->assign("status_tab", $db->getAllStatus());

    // 画面左側の詳細情報テーブル用のデータを取得
    $jobData = $db->getJobDataByCoId($curJob['id']);
    // 存在しない or 0件
    if (!$jobData) {
        $session->set('message', "指定されたお問い合わせ情報が存在しません。");
        $_POST = null;
        header('location: contact_search_result.php');
        exit;
    }

    $view->assign("job", $jobData);

    /* 画面表示時点でのlastupdateをSESSIONに格納（編集の衝突回避） */
    $session->setTargetJob([
                            'lastupdate' => $jobData['raw_lastupdate'],
                            'parent'     => $jobData['co_parent'],
                            'child_no'   => $jobData['co_child_no'],
                            'us_id'      => $jobData['co_us_id'],
                            'ca_id'      => $jobData['ca_name'],
                           ]);

    /* 再表示かどうかで対応予定日と備考欄の値をスイッチ */
    if (!isset($view->comment)) {
        $view->assign("comment", $jobData['co_comment']);
    }
    if (!isset($view->limitYear)
        && !isset($view->limitMonth)
        && !isset($view->limitDay)) {
        $view->assign([
                        'limitYear'  => $jobData['co_limit'],
                        'limitMonth' => $jobData['co_limit'],
                        'limitDay'   => $jobData['co_limit'],
                      ]);
    }

    /* 画面右側で出すメール一覧を取得 */
    $mails = $db->getAllMailByCoId($jobData['co_parent'], $postData['sort']);

    $idx = 0;
    foreach ($mails as $tmp_row) {
        /* 添付ファイルがあるかチェック */
        $mails[$idx]["at_flag_attach"] = $db->checkExistAttach($tmp_row["ma_id"]);
        $idx++; 
    }

    $view->assign('mailTable', $mails);
    $session->setTargetJob('rootMaId', $jobData['co_ma_id'] ?: "");

    /* メールが選択されていたらデータ取得 */
    if ($postData['selectedMail']) {

        // 件名
        $sql = "SELECT * FROM mail_tab WHERE ma_id = ?";
        $mailData = $db->fetchAll($sql, [$postData['selectedMail']]);
        $view->assign('mailSubject', $mailData[0]['ma_subject']);
        $view->assign('mailFromAddr', $mailData[0]['ma_from_addr']);
        $view->assign('mailToAddr', $mailData[0]['ma_to_addr']);
        $view->assign('mailCcAddr', $mailData[0]['ma_cc_addr']);

        // 本文
        $mailFile = sprintf("%s/%02d/%d", $config->mailsavedir,
            $postData['selectedMail'] % 100, $postData['selectedMail']);
        $mailBody = @file_get_contents($mailFile);
        if ($mailBody === false) {
            CuMAS_Exception::log_s($logFacility, __FILE__,
                "Unable to load mail file '{$mailFile}'");
            $mailBody = "Mail Data is bloken.";
        }

        $view->assign("mailBody", $mailBody);

        /* 添付ファイル */
        $sql = "SELECT * FROM attach_tab WHERE at_mailid = ?";
        $view->assign('mailAttach', $db->fetchAll($sql, [$postData['selectedMail']]));

    /* メール転送ダウンロードボタン */
    } else if ($postData["download"]) {
        $sql = "SELECT * FROM attach_tab WHERE at_id = ?";
        $attach_data = $db->fetchAll($sql, array($postData["target_at_id"]));
        if (count($attach_data) === 0) {
            $view->message = "添付ファイルが存在していません。";
        } else {
            $view->message = downloadFile($attach_data[0]);
        }

    } else {
        $view->assign("mailBody", "");
        $view->assign("mailAttach", array());
    }

    $view->display();
} catch (PDOEXCEPTION $e) {
    Cumas_Exception::log_s($logFacility, __FILE__, $e->getMessage());
    Cumas_Exception::printErr();
    exit;
} catch (CuMAS_Exception $e) {
    $e->log($logFacility, __FILE__);
    $e->printErr();
    exit;
}

/* End of file contact_detail.php */
