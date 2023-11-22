<?php
include_once '../lib/cumas_common.php';
require '../lib/libutil';

class AddPDO extends CuMAS_PDO
{
    public function getUsMail($login_us_id)
    {
        $sql = "SELECT us_mail FROM user_tab WHERE us_id = ?";
        $stmt = $this->_pdo->prepare($sql);
        $stmt->execute(array($login_us_id));
        return $stmt->fetchColumn();
    }

    public function isActiveCategory($ca_id)
    {
        $sql = "SELECT ca_active FROM category_tab WHERE ca_id = ?";
        $stmt = $this->_pdo->prepare($sql);
        $stmt->execute(array($ca_id));

        //取ってきたca_activeの値そのもの（boolean型）を返す。
        //fetchに失敗した場合はfalseが返る
        return $stmt->fetch(PDO::FETCH_ASSOC)['ca_active'];
    }

    public function checkUser($us_id)
    {
        $sql = "SELECT us_active FROM user_tab WHERE us_id = ?";
        $stmt = $this->_pdo->prepare($sql);
        $stmt->execute(array($us_id));
        $ret = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($ret === false) {
            $ret['us_active'] = false;
        }
        return $ret;
    }

    public function insertTaskTab($data)
    {
        $sql = ("INSERT INTO task_tab"
		. " ("
		. "ta_category,"
		. "ta_user,"
		. "ta_registuser,"
		. "ta_registdate,"
		. "ta_post,"
		. "ta_repmode,"
		. "ta_repday,"
		. "ta_subject,"
		. "ta_body,"
		. "ta_comment"
		. ")"
                .  " VALUES"
                .  " (?,?,?,?,?,?,?,?,?,?)"
                .  " RETURNING ta_id");

        $stmt = $this->_pdo->prepare($sql);
	$stmt->execute(
		array(
                    $data['ta_category'],
	            $data['ta_user'],
	            $data['ta_registuser'],
	            $data['ta_registdate'],
	            $data['ta_post'],
	            $data['ta_repmode'],
	            $data['ta_repday'],
	            $data['ta_subject'],
	            $data['ta_body'],
	            $data['ta_comment'],
		)
        );

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['ta_id'];
    }
}

/**
 * ローカルチェック関数
 */
class checkPost
{
    function __construct($data)
    {
        $this->ca_id      = $data['ca_id'];
        $this->ta_repmode = $data['ta_repmode'];
        $this->ta_post    = $data['ta_post'];
        $this->ta_subject = $data['ta_subject'];
        $this->ta_body    = $data['ta_body'];
        $this->ta_comment = $data['ta_comment'];
    }

    public function checkCategory()
    {
        if ($this->ca_id === 0) {
            throw new CuMAS_Exception("カテゴリが選択されていません。");
        }
        return $this;
    }

    public function checkRegDate()
    {
        if (strlen($this->ta_post) === 0) {
            throw new CuMAS_Exception("初回登録日時が入力されていません。");
        }

	$ret = check_fmt_datetime($this->ta_post);
	if ($ret === false) {
            throw new CuMAS_Exception("初回登録日時の形式が不正です。");
        }

        /* 毎月末 */
        if ($this->ta_repmode === "3") {
            $datetime_selected = date('Y-m-d', strtotime($this->ta_post));
            $month = date('Y-m', strtotime($this->ta_post));
            $lastDay= date('Y-m-d', strtotime('last day of ' . $month));
            if ($lastDay !== $datetime_selected) {
                throw new CuMAS_Exception("初回登録日は月末日を選択してください。");
            } 
        }

        $ta_post_parts = explode(" ", $this->ta_post);
        $nowdate = date('Y/m/d');

        if (strtotime($ta_post_parts[0]) < strtotime($nowdate)) {
            throw new CuMAS_Exception("初回登録日時は過去の日付を入力しないでください。");
        } 

        /* 毎月末 */
        return $this;
    }

    public function checkTaRepmode() 
    {
        if (strlen(trim($this->ta_repmode)) === 0) {
            throw new CuMAS_Exception("繰り返しモードがチェックされていません。");
	}
        return $this;
    }

    public function checkSubject()
    {
        if (strlen(trim($this->ta_subject)) === 0) {
            throw new CuMAS_Exception("件名が入力されていません。");
        }
        return $this;
    }

    public function checkBody()
    {
        if (strlen(trim($this->ta_body)) === 0) {
            throw new CuMAS_Exception("内容が入力されていません。");
        }
        return $this;
    }


    public function checkComment()
    {
        if (strlen($this->ta_comment) > 2048) {
            throw new CuMAS_Exception("備考の文字数が不正です。");
        }
        return $this;
    }
}

/**
 *main処理
 */
// メッセージがあれば
$view->message = $session->cut('message') ?: "";

/* 初期値 */
$view->assign("tag", array(
    "add"        => "",       // 登録ボタン
    "return"     => "",       // キャンセルボタン
    "ca_id"      => "",       // カテゴリID
    "us_id"      => "",       // 担当者ID
    "us_name"    => "",       // 担当者名
    "ta_post"    => "",       // 初回登録日時
    "ta_repmode" => "",       // 繰り返しモード
    "ta_subject" => "",       // 件名
    "ta_body"    => "",       // 本文
    "ta_comment" => "",       // コメント
));

/* POSTの取得 */
$formList = [
    "add"        => FILTER_DEFAULT,       // 登録ボタン
    "return"     => FILTER_DEFAULT,       // キャンセルボタン
    "ca_id"      => FILTER_VALIDATE_INT,  // カテゴリID
    "us_id"      => FILTER_VALIDATE_INT,  // 担当者ID
    "us_name"    => FILTER_DEFAULT,       // 担当者名
    "ta_post"    => FILTER_DEFAULT,       // 初回登録日時
    "ta_repmode" => FILTER_DEFAULT,       // 繰り返しモード
    "ta_subject" => FILTER_DEFAULT,       // 件名
    "ta_body"    => FILTER_DEFAULT,       // 本文
    "ta_comment" => FILTER_DEFAULT,       // コメント
];

/* 外部から変数を受け取り、オプションでそれらをフィルタリングする */
$postData = filter_input_array(INPUT_POST, $formList);

//戻るボタンが押された時
if (isset($postData['return'])) {
    header('location: task_list.php');
    exit;
}

/* 繰り返しモードは毎月末 */
$view->mode_enddaymonth = 2;

//登録ボタンが押された時
if (isset($postData['add'])) {
    try {
        $register = new checkPost($postData);
        $register->checkCategory()
                 ->checkRegDate()
                 ->checkTaRepmode()
                 ->checkSubject()
                 ->checkBody()
                 ->checkComment();

        $db = AddPDO::getInstance($config);
        $table = array('task_tab', 'category_tab');
        $db->lockTable($table);

        /* カテゴリの存在とアクティブフラグを確認 */
        if (!$postData['ca_id'] || 
            !$db->isActiveCategory($postData['ca_id'])) {
                throw new CuMAS_Exception("カテゴリの指定が不正です。");
        }

        /* 担当者の存在とアクティブフラグを確認 */
        if ($postData['us_id'] != 0) {
            $act = $db->checkUser($postData['us_id']);
            if ($act['us_active'] === false) {
                $db->rollBack();
                throw new CuMAS_Exception("担当者の指定が不正です。");
            }
        }

        /* 登録者のアドレス取得 */
        $s = $session->getLoginUserData();
        $us_mail = $db->getUsMail($s['us_id']);
        if ($us_mail === false) {
            $db->rollBack();
            $session->logout();
            exit;
        }

	/* 登録パラメータの計算を行う */
	$task_data = cal_param_datetime($postData["ta_repmode"], 
		                        $postData["ta_post"]);

	$task_data["ta_category"]   = $postData['ca_id'];
	$task_data["ta_user"]       = $postData['us_id'];
	$task_data["ta_registuser"] = $s["us_id"];
	$task_data["ta_registdate"] = date(DB_TIMESTAMP_FMT);;
	$task_data["ta_subject"]    = $postData["ta_subject"];
	$task_data["ta_body"]       = $postData["ta_body"];
	$task_data["ta_comment"]    = $postData["ta_comment"];

        /* task_tabに登録し、ta_idを取得する */
        $ma_id = $db->insertTaskTab($task_data);

        $db->commit();
        $session->set('message',
              sprintf("タスク \"$task_data[ta_subject]\" を追加しました。"));
        header('location: task_list.php');
        exit;

    //DB操作、本文ファイル保存でエラーが出た場合システムエラー
    } catch (PDOEXCEPTION $e) {
        empty($db) ?: $db->rollBack();
        Cumas_Exception::log_s($logFacility, __FILE__, $e->getMessage());
        Cumas_Exception::printErr();
        exit;

    //入力値チェックでエラーが出た場合再表示
    } catch (CuMAS_Exception $e) {
        $view->message = $e->getMessage();

        /* 入力データを保持 */
        $view->assign("tag", $postData);
        if ($postData["ta_repmode"] === "3") {
            $view->mode_enddaymonth = 1;
        } else {
            $view->mode_enddaymonth = 2;
        }
    }
}

try {
    $db = AddPDO::getInstance($config);
    //カテゴリテーブルからカテゴリ一覧の取得
    $view->assign("category_tab", $db->getActiveCategories());
    //ユーザテーブルから担当者一覧の取得
    $view->assign("user_tab", $db->getActiveUsers());
} catch (PDOEXCEPTION $e) {
    empty($db) ?: $db->rollBack();
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

/* End of file contact_detail.php */
