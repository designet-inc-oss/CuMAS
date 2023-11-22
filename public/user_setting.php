<?php
include_once '../lib/cumas_common.php';

class Mod_Exception extends Exception{}
class Mod_PDO extends CuMAS_PDO
{
   /****
    * updateUserTab (担当者登録処理用関数)
    *
    * 引数  :$data --> web上で入力された値
    *
    * 返り値:編集後の名前
    ***/
    public function updateUserTab($data)
    {

        $salts = array("A", "B", "C", "D", "E", "F", "G", "H", "I", "J", "K", "L",
                       "M", "N", "O", "P", "Q", "R", "S", "T", "U", "V", "W", "X",
                       "Y", "Z", "a", "b", "c", "d", "e", "f", "g", "h", "i", "j",
                       "k", "l", "m", "n", "o", "p", "q", "r", "s", "t", "u", "v",
                       "w", "x", "y", "z", "0", "1", "2", "3", "4", "5", "6", "7",
                       "8", "9", ".", "/" );

        $rand_key = array_rand($salts, 8);
        $salt = "$1$".$salts[$rand_key[0]]
                     .$salts[$rand_key[1]]
                     .$salts[$rand_key[2]]
                     .$salts[$rand_key[3]]
                     .$salts[$rand_key[4]]
                     .$salts[$rand_key[5]]
                     .$salts[$rand_key[6]]
                     .$salts[$rand_key[7]]."$";

        $items['passwd'] = crypt($data['us_login_passwd'], $salt);

        $sql = "UPDATE user_tab"
             . " SET us_login_passwd = :passwd"
             . " WHERE us_id = :target";
        $items['target'] = $data['targetId'];

        $stmt = $this->_pdo->prepare($sql);
        $ret = $stmt->execute($items);
    }
}

//入力値チェック用のクラス
class checkUserData
{
    function __construct($data)
    {
        $this->us_login_passwd  = $data['us_login_passwd'];
        $this->us_login_passwd2 = $data['us_login_passwd2'];
    }

    public function checkNull()
    {
        if (empty($this->us_login_passwd)) {
            throw new CuMAS_Exception("ログインパスワードが入力されていません。");
        }
        if (empty($this->us_login_passwd2)) {
            throw new CuMAS_Exception("ログインパスワード(確認)が入力されていません。");
        }
        return $this;
    }

    public function checkLoginPasswd()
    {
        if ($this->us_login_passwd === "") {
            return $this;
        }

        $length = strlen($this->us_login_passwd);
        if ($length < 8 || $length > 64) {
            throw new CuMAS_Exception("ログインパスワードの文字数が不正です。");
        }
        if (!preg_match("/^[a-zA-Z0-9]+$/", $this->us_login_passwd)) {
            throw new CuMAS_Exception("ログインパスワードの文字種が不正です。");
        }
        if ($this->us_login_passwd !== $this->us_login_passwd2) {
            throw new CuMAS_Exception("入力されたパスワードが一致しません。");
        }
        return $this;
    }
}

/**
 *main処理
 */
$view->assign('message', "担当者設定を変更します。");

if (filter_input(INPUT_POST, 'return')) {
    $_POST = null;
    header('location: user_list.php');
    exit;
}

//POSTの取得
$formList = [
    "mod"               => FILTER_DEFAULT,
    "us_login_passwd"   => FILTER_DEFAULT,
    "us_login_passwd2"  => FILTER_DEFAULT,
];

$postData = filter_input_array(INPUT_POST, $formList);

$postData['targetId'] = $target = $session->getLoginUserData('us_id');


try {
    $db = Mod_PDO::getInstance($config);
} catch (PDOEXCEPTION $e) {
    Cumas_Exception::log_s($logFacility, __FILE__, $e->getMessage());
    Cumas_Exception::printErr();
    exit;
}

//登録ボタンが押された時
if (isset($postData['mod'])) {
    try {
        //入力値チェック
        $register = new checkUserData($postData);
        $register->checkNull()->checkLoginPasswd();

        $db->lockTable('user_tab');

        //mail_tabに登録
        $afterName = $db->updateUserTab($postData);

        $db->commit();
        $view->assign('message', "担当者設定を更新しました。");

    //システムエラー
    } catch (PDOEXCEPTION $e) {
        empty($db) ?: $db->rollBack();
        Cumas_Exception::log_s($logFacility, __FILE__, $e->getMessage());
        Cumas_Exception::printErr();
        exit;

    //入力値チェックでエラーが出た場合
    } catch (CuMAS_Exception $e) {
        $view->message = $e->getMessage();
    }
}

/**
 * 初期表示
 */

$sql = "SELECT us_name,us_mail,us_login_name,us_active,us_admin_flg"
     . " FROM user_tab WHERE us_id = ?";
try {
    $userData = $db->fetchAll($sql, $target)[0];
} catch (PDOEXCEPTION $e) {
    Cumas_Exception::log_s($logFacility, __FILE__, $e->getMessage());
    Cumas_Exception::printErr();
    exit;
}

//初期表示用にチェックボックスの値の初期化
$postData['us_name']    = $userData['us_name'];
$postData['us_mail']    = $userData['us_mail'];
$postData['us_login_name']    = $userData['us_login_name'];
$view->assign("tag", $postData);


try {
    $view->display();
} catch (CuMAS_Exception $e) {
    $e->log($logFacility, __FILE__);
    $e->printErr();
    exit;
}

/* End of file contact_detail.php */
