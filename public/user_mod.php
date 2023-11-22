<?php
include_once '../lib/cumas_common.php';

class Mod_Exception extends Exception{}
class Mod_PDO extends CuMAS_PDO
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
        // 自分自身が重複チェックにひっかからないように注意
        $sql = "SELECT us_name,us_mail,us_login_name FROM user_tab WHERE"
             . " (us_name = ? or us_mail = ? or us_login_name = ?)"
             . " AND us_id <> ? ";

        $stmt = $this->_pdo->prepare($sql);
        $stmt->execute(array(
                            $data['us_name'],
                            $data['us_mail'],
                            $data['us_login_name'],
                            $data['targetId'],
                        ));

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
            throw new Mod_Exception($errorMessage);
        }
    }

   /****
    * updateUserTab (担当者登録処理用関数)
    *
    * 引数  :$data --> web上で入力された値
    *
    * 返り値:編集後の名前
    ***/
    public function updateUserTab($data)
    {
        $items = [
                  'active' => $data['active'],
                  'admin_flg' => $data['admin_flg'],
                  'us_name' => $data['us_name'],
                  'us_mail' => $data['us_mail'],
                  'us_login_name' => $data['us_login_name'],
                 ];
        $sql = "UPDATE user_tab"
             . " SET us_active = :active, us_admin_flg = :admin_flg"
             . ", us_name = :us_name, us_mail = :us_mail"
             . ", us_login_name = :us_login_name";

        if ($data['us_login_passwd']) {
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
            $sql .= ", us_login_passwd = :passwd";
        }


        $sql .= " WHERE us_id = :target RETURNING us_name";
        $items['target'] = $data['targetId'];

        $stmt = $this->_pdo->prepare($sql);
        $ret = $stmt->execute($items);
        return $stmt->fetch(PDO::FETCH_COLUMN);
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
if (!$session->isAdmin()) {
    $session->set('message', "管理者権限がありません。");
    $_POST = null;
    header('location: contact_search_result.php');
    exit;
}


if (filter_input(INPUT_POST, 'return')) {
    $_POST = null;
    header('location: user_list.php');
    exit;
}

//POSTの取得
$formList = [
    "mod"               => FILTER_DEFAULT,
    "return"            => FILTER_DEFAULT,
    "us_name"           => FILTER_DEFAULT,
    "us_mail"           => FILTER_DEFAULT,
    "us_login_name"     => FILTER_DEFAULT,
    "us_login_passwd"   => FILTER_DEFAULT,
    "us_login_passwd2"  => FILTER_DEFAULT,
    "us_active"         => FILTER_DEFAULT,
    "us_admin_flg"      => FILTER_DEFAULT,
    "targetId"          => FILTER_DEFAULT,
];

$postData = filter_input_array(INPUT_POST, $formList);

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
        $register->checkNull()->checkName()->checkMail()->checkLoginName()
                 ->checkLoginPasswd();

        //active,adminflgの値をDB入力用に変更
        if (empty($postData['us_active'])) {
            $postData['active'] = 'FALSE';
        } else {
            $postData['active'] = 'TRUE';
        }
        if (empty($postData['us_admin_flg'])) {
            $postData['admin_flg'] = 'FALSE';
        } else {
            $postData['admin_flg'] = 'TRUE';
        }

        $db->lockTable('user_tab');

        //被りの確認
        $db->checkOverlap($postData);

        //mail_tabに登録
        $afterName = $db->updateUserTab($postData);

        $db->commit();
        $session->set('message', sprintf(
            "担当者 {$afterName} を編集しました。"));
        header('location: user_list.php');
        exit;

    //システムエラー
    } catch (PDOEXCEPTION $e) {
        empty($db) ?: $db->rollBack();
        Cumas_Exception::log_s($logFacility, __FILE__, $e->getMessage());
        Cumas_Exception::printErr();
        exit;

    //重複チェックにひっかかった場合
    } catch (Mod_Exception $e) {
        empty($db) ?: $db->rollBack();
        $view->message = $e->getMessage();
        $view->assign("tag", $postData);

    //入力値チェックでエラーが出た場合
    } catch (CuMAS_Exception $e) {
        $view->message = $e->getMessage();
        $view->assign("tag", $postData);
    }
} else {

    /**
     * 初期表示
     */
    if ($target = $session->cut('userIdToMod')) {
        $view->message = "選択した担当者情報を編集します。";
    } else {
        $session->set('message', "不正なアクセスです。");
        header('location: user_list.php');
        exit;
    }

    //hiddenタグによるPOST値で処理を行う。
    $postData["targetId"] = $target;

    $sql = "SELECT us_name,us_mail,us_login_name,us_active,us_admin_flg"
         . " FROM user_tab WHERE us_id = ?";
    try {
        $userData = $db->fetchAll($sql, $target)[0];
    } catch (PDOEXCEPTION $e) {
        Cumas_Exception::log_s($logFacility, __FILE__, $e->getMessage());
        Cumas_Exception::printErr();
        exit;
    }

    if (empty($userData)) {
        $session->set('message', "編集対象の担当者が存在しません。");
        header('location: user_list.php');
        exit;
    }

    //初期表示用にチェックボックスの値の初期化
    $postData['us_name']    = $userData['us_name'];
    $postData['us_mail']    = $userData['us_mail'];
    $postData['us_login_name']    = $userData['us_login_name'];
    $postData['us_active']    = $userData['us_active'];
    $postData['us_admin_flg'] = $userData['us_admin_flg'];
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
