<?php
include_once '../lib/cumas_common.php';

class Add_Exception extends Exception{}
class Add_PDO extends CuMAS_PDO
{

   /****
    *checkOverlap (重複チェック用関数) 
    *
    * 引数  :$data      --> web上で入力された値
    *
    * 返り値:無し
    ***/
    public function checkOverlap($data)
    {
        $sql = "SELECT us_name,us_mail,us_login_name FROM user_tab WHERE"
              ." us_name = ? or us_mail = ? or us_login_name = ?";

        $stmt = $this->_pdo->prepare($sql);
        $stmt->execute(array(
                            $data['us_name'],
                            $data['us_mail'],
                            $data['us_login_name']));

        $result = $stmt->fetchAll(); 
        if (!empty($result)) {
            $errMsg = array(
                            'nerrMsg' => "",
                            'merrMsg' => "",
                            'lerrMsg' => "");

            foreach($result as $value) {
                if ($data['us_name'] == $value['us_name']) {
                    $errMsg['nerrMsg'] = "担当者名";
                }
                if ($data['us_mail'] == $value['us_mail']) {
                    $errMsg['merrMsg'] = "メールアドレス";
                }
                if ($data['us_login_name'] == $value['us_login_name']) {
                    $errMsg['lerrMsg'] = "ログインID";
                }
            }

            $errorMessage = "入力された " . implode(array_filter($errMsg, "strlen"), ",") ." は既に使われています。";
            throw new Add_Exception($errorMessage);
        }
    }

   /****
    *insertUserTab (担当者登録処理用関数)
    *
    * 引数  :$data --> web上で入力された値
    *
    * 返り値:無し
    ***/
    public function insertUserTab($data)
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

        $passwd = crypt($data['us_login_passwd'], $salt);

        $sql = "INSERT INTO user_tab"
              ." (us_name,us_mail,us_login_name,us_login_passwd,us_active,us_admin_flg)"
              ." VALUES"
              ." (?,?,?,?,?,?)";

        $stmt = $this->_pdo->prepare($sql);
        $stmt->execute(array(
                            $data['us_name'],
                            $data['us_mail'],
                            $data['us_login_name'],
                            $passwd,
                            $data['active'],
                            $data['admin_flg']));
    }
}

//入力値チェック用のクラス
class checkUserData
{
    function __construct($data)
    {
        $this->us_name          = $data['us_name'];
        $this->us_mail          = $data['us_mail'];
        $this->us_login_name    = $data['us_login_name'];
        $this->us_login_passwd  = $data['us_login_passwd'];
        $this->us_login_passwd2 = $data['us_login_passwd2'];
    }

    public function checkNull()
    {
        if (empty($this->us_name)) {
            throw new CuMAS_Exception("担当者名が入力されていません。");
        }
        if (empty($this->us_mail)) {
            throw new CuMAS_Exception("メールアドレスが入力されていません。");
        }
        if (empty($this->us_login_name)) {
            throw new CuMAS_Exception("ログインIDが入力されていません。");
        }
        if (empty($this->us_login_passwd)) {
            throw new CuMAS_Exception("ログインパスワードが入力されていません。");
        }
        if (empty($this->us_login_passwd2)) {
            throw new CuMAS_Exception("ログインパスワード(確認)が入力されていません。");
        }
        return $this;
    }

    public function checkName()
    {
        if (strlen($this->us_name) > 64) {
            throw new CuMAS_Exception("担当者名の文字数が不正です。");
        }
        return $this;
    }

    public function checkMail()
    {
        if (strlen($this->us_mail) > 255) {
            throw new CuMAS_Exception("メールアドレスの文字数が不正です。");
        }
        if (!preg_match("/^[a-zA-Z0-9._\-]+@[a-zA-Z0-9._\-]+$/", $this->us_mail)) {
            throw new CuMAS_Exception("メールアドレスの形式が不正です。");
        }
        return $this;
    }

    public function checkLoginName()
    {
        if (strlen($this->us_login_name) > 64) {
            throw new CuMAS_Exception("ログインIDの文字数が不正です。");
        }
        if (!preg_match("/^[a-zA-Z0-9]+$/", $this->us_login_name)) {
            throw new CuMAS_Exception("ログインIDの文字種が不正です。");
        }
        return $this;
    }

    public function checkLoginPasswd()
    {
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

//admin_flgの確認
if (!$session->isAdmin()) {
    $session->set('message', sprintf("管理者権限がありません。"));
    header('location: contact_search_result.php');
    exit;
}

// メッセージがあれば使うしなければデフォルト
$view->message = $session->cut('message') ?: "新規の担当者を追加します。";

//POSTの取得
$formList = [
    "add"               => FILTER_DEFAULT,
    "return"            => FILTER_DEFAULT,
    "us_name"           => FILTER_DEFAULT,
    "us_mail"           => FILTER_DEFAULT,
    "us_login_name"     => FILTER_DEFAULT,
    "us_login_passwd"   => FILTER_DEFAULT,
    "us_login_passwd2"  => FILTER_DEFAULT,
    "us_active"         => FILTER_DEFAULT,
    "us_admin_flg"      => FILTER_DEFAULT,
];

$postData = filter_input_array(INPUT_POST, $formList);

//戻るボタンが押された時
if (isset($postData['return'])) {
    header('location: user_list.php');
    exit;
}

//登録ボタンが押された時
if (isset($postData['add'])) {
    try {

        //入力値チェック
        $register = new checkUserData($postData);
        $register->checkNull()->checkName()->checkMail()->checkLoginName()->checkLoginPasswd();

        //active,adminflgの値をDB入力用に変更
        if (!empty($postData['us_active'])) {
            $postData['active'] = 'TRUE';
        } else {
            $postData['active'] = 'FALSE';
        }
        if (!empty($postData['us_admin_flg'])) {
            $postData['admin_flg'] = 'TRUE';
        } else {
            $postData['admin_flg'] = 'FALSE';
        }

        $db = Add_PDO::getInstance($config);
        $table = array('user_tab');
        $db->lockTable($table);

        //被りの確認
        $db->checkOverlap($postData);

        //mail_tabに登録
        $db->insertUserTab($postData);

        $db->commit();
        $session->set('message', sprintf("担当者 "
                                        .$postData['us_name']
                                        ." を登録しました。"));
        header('location: user_list.php');
        exit;

    //システムエラー
    } catch (PDOEXCEPTION $e) {
        empty($db) ?: $db->rollBack();
        Cumas_Exception::log_s($logFacility, __FILE__, $e->getMessage());
        Cumas_Exception::printErr();
        exit;

    //重複チェックにひっかかった場合
    } catch (Add_Exception $e) {
        empty($db) ?: $db->rollBack();
        $view->message = $e->getMessage();
        $view->assign("tag", $postData);

    //入力値チェックでエラーが出た場合
    } catch (CuMAS_Exception $e) {
        $view->message = $e->getMessage();
        $view->assign("tag", $postData);
    }
} else {

    //初期表示用にチェックボックスの値の初期化
    $postData['us_active']    = 'on';
    $postData['us_admin_flg'] = "";
    $view->assign("tag", $postData);
}

try {
    $view->display();
} catch (CuMAS_Exception $e) {
    $e->log($logFacility, __FILE__);
    $e->printErr();
    exit;
}

/* End of file contact_detail.php */
