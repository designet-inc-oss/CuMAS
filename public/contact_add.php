<?php
include_once '../lib/cumas_common.php';
include_once '../lib/libutil';


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

    public function insertMailTab($data, $us_mail)
    {
        $sql = ("INSERT INTO mail_tab"
             .  " (ma_date,ma_from_addr,ma_subject) "
             .  " VALUES"
             .  " (?,?,?) "
             .  " RETURNING ma_id");

        $stmt = $this->_pdo->prepare($sql);
        $stmt->execute(array(
                            $data['inqstr'],
                            $us_mail,
                            $data['subject']));

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['ma_id'];
    }

    public function insertContactTab($data, $login_us_id, $ma_id)
    {
        if ($data['us_id'] == 0) {
            $data['us_id'] = null;
        }

        $sql = "INSERT INTO contact_tab"
             . " (co_us_id,co_inquiry,co_lastupdate,co_comment,co_operator,co_ma_id,ca_id)"
             . " VALUES"
             . " (?,?,?,?,?,?,?)"
             . " RETURNING co_id";

        $stmt = $this->_pdo->prepare($sql);
        $stmt->execute(array(
                            $data['us_id'],
                            $data['inqstr'],
                            $data['inqstr'],
                            $data['comment'],
                            $login_us_id,
                            $ma_id,
                            $data['ca_id'],
                        ));

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $co_id = $result['co_id'];

        $sql = "UPDATE contact_tab SET co_parent = $co_id WHERE co_id = $co_id";
        $this->_pdo->query($sql);
        return $co_id;
    }

    public function insertContactMailTab($co_id, $ma_id)
    {
        $sql = "INSERT INTO contact_mail_tab "
             . "(co_id,ma_id) VALUES (?,?)";
        $stmt = $this->_pdo->prepare($sql);
        $stmt->execute(array($co_id, $ma_id));
    }
}

//ローカルチェック関数
class checkPost
{
    function __construct($data)
    {
        $this->inqstr  = $data["inqstr"];
        $this->subject = $data['subject'];
        $this->comment = $data['comment'];
    }

    public function checkSubject()
    {
        if (strlen(trim($this->subject)) === 0) {
            throw new CuMAS_Exception("件名が入力されていません。");
        }
        return $this;
    }

    public function checkIncDate()
    {
        $ret = check_fmt_datetime($this->inqstr);
        if ($ret === false) {
            throw new CuMAS_Exception("お問い合わせ日時が不正です。");
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
 *main処理
 */
// メッセージがあれば
$view->message = $session->cut('message') ?: "新規のお問い合わせを追加します。";

//POSTの取得
$formList = [
    "update"   => FILTER_DEFAULT,
    "return"   => FILTER_DEFAULT,
    "ca_id"    => FILTER_VALIDATE_INT,
    "us_id"    => FILTER_VALIDATE_INT,
    "us_name"  => FILTER_DEFAULT,
    "inqstr"   => FILTER_DEFAULT,
    "subject"  => FILTER_DEFAULT,
    "body"     => FILTER_DEFAULT,
    "comment"  => FILTER_DEFAULT,
];

$postData = filter_input_array(INPUT_POST, $formList);

//戻るボタンが押された時
if (isset($postData['return'])) {
    header('location: contact_search_result.php');
    exit;
}
//登録ボタンが押された時
if (isset($postData['update'])) {
    try {
        $register = new checkPost($postData);
        $register->checkSubject()->checkIncDate()->checkComment();

        $db = AddPDO::getInstance($config);
        $table = array('contact_tab', 'mail_tab', 'contact_mail_tab', 'category_tab');
        $db->lockTable($table);

        //カテゴリの存在とアクティブフラグを確認
        if (!$postData['ca_id'] || 
            !$db->isActiveCategory($postData['ca_id'])) {
                throw new CuMAS_Exception("カテゴリの指定が不正です。");
        }

        //担当者の存在とアクティブフラグを確認
        if ($postData['us_id'] != 0) {
            $act = $db->checkUser($postData['us_id']);
            if ($act['us_active'] === false) {
                $db->rollBack();
                throw new CuMAS_Exception("担当者の指定が不正です。");
            }
        }

        //登録者のアドレス取得
        $s = $session->getLoginUserData();
        $us_mail = $db->getUsMail($s['us_id']);
        if ($us_mail === false) {
            $db->rollBack();
            $session->logout();
            exit;
        }

        $postData['inqstr'] = date(DB_TIMESTAMP_FMT, strtotime($postData['inqstr']));

        //mail_tabに登録し、ma_idを取得する
        $ma_id = $db->insertMailTab($postData, $us_mail);

        //contact_tabに登録し、co_idを取得する
        $co_id = $db->insertContactTab($postData, $s['us_id'], $ma_id);

        //contact_mail_tabに登録する
        $db->insertContactMailTab($co_id, $ma_id);

        //内容をファイルに登録
        $dirname = sprintf("%02d", $ma_id % 100);
        $dirname = "{$config->mailsavedir}/{$dirname}";

        if (!file_exists($dirname)) {
            $result = mkdir($dirname, 0700);
            if ($result === false) {
                throw new PDOEXCEPTION("Failed to create mail directory ($dirname)");
            }
        }

        $fp = @fopen("{$dirname}/{$ma_id}", "w");
        if ($fp === false) {
            throw new PDOEXCEPTION("Failed to open file ({$dirname}/{$ma_id})");
        }
        $result = @fwrite($fp, $postData['body']);
        if ($result === false) {
            throw new PDOEXCEPTION("Failed to write mail text ({$dirname}/{$ma_id})");
        }
        fclose($fp);
        chmod("{$dirname}/{$ma_id}", 0400);
        $db->commit();
        $session->set('message', sprintf("お問い合わせ \"[$co_id] $postData[subject]\" の登録に成功しました。"));
        header('location: contact_search_result.php');
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
        $view->assign("tag", $postData);
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
