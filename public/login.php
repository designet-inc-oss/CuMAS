<?php

$login_mode = true;     /* cumas_common.phpのモード */
include_once '../lib/cumas_common.php';

/**
 * ページローカルクラス・関数
 */
/**
 * CuMAS_PDOのカスタマイズ
 */
class LoginPDO extends CuMAS_PDO
{
    public function getLoginData($username)
    {
        $sql = "SELECT us_id,us_name,us_login_passwd,us_admin_flg"
             . " FROM user_tab"
             . " WHERE us_login_name=?"
             . " AND us_active = true";

        $stmt = $this->_pdo->prepare($sql);

        $stmt->execute(array($username));

        return $stmt->fetch();
    }
}

/**
 * 空欄チェックと、データベース参照してのパスワードチェックを行う。
 * PDOEXCEPTIONを吐く可能性がある。
 *
 * @param array $postData 取得された$_POSTデータ
 * @param array $config   設定ファイル情報
 *
 * @return mixed(array/bool)
 *  成功時には、セッション格納用に'admin'/'user'
 *  失敗したらfalse
 *
 * *-note-*
 * PHPは渡された引数のコピーを即座に作らない(lazyに作る)ので、
 * $configとかをまるごと渡してもリソースを無駄食いしたりしない。
 */
function loginCheck($postData, $config)
{

    /* 入力値のどちらかが空ならエラー */
    /* イコール3つでないとだめ */
    if ($postData["username"] === "" || $postData["password"] === "") {
        return false;
    }

    $db = LoginPDO::getInstance($config);
    $result = $db->getLoginData($postData['username']);
    if (!$result) {
        return false;
    }

    /* 入力パスワードを暗号化 */
    $postedPasswd = crypt(
        $postData["password"],
        substr($result["us_login_passwd"], 0, 11)
    );
    /* note *
     * DBには、'$1$salt8文字$暗号化ハッシュ' という文字列が格納されている。
     * cryptでMD5暗号化させるためには、第二引数に'$1$ソルト'みたいなのを渡す。
     */

    /* null比較をケアしてイコール3つ */
    if ($result["us_login_passwd"] !== $postedPasswd) {
        return false;
    }

    /* セッションにしまうための値を返す */
    return [
        "us_id"         => $result["us_id"],
        "us_name"   => $result["us_name"],
        "us_admin_flg"  => $result["us_admin_flg"] ? CuMAS_Session::ADMIN
                                                   : CuMAS_Session::USER,
    ];
}


/**
 * ページローカルクラス・関数ここまで
 */

/**
 * メイン処理ここから
 */

/*
 * POST情報の取得
 */
$formList = [
    "username"  => FILTER_DEFAULT,
    "password"  => FILTER_DEFAULT,
    "login"     => FILTER_DEFAULT,
];
$postData = filter_input_array(INPUT_POST, $formList);

/*
 * GETの取得
 */
$getError = filter_input(INPUT_GET, "s", FILTER_SANITIZE_STRING);
switch ($getError) {
    case CuMAS_Session::TIMEOUT:
        $view->assign("message", "セッションがタイムアウトしました。");
        break;

    case CuMAS_Session::ERROR:
        $view->assign("message", "セッションが無効です。");
        break;

    case CuMAS_Session::LOGOUT:
        $view->assign("message", "ログアウトしました。");
        break;

    default:
        break;
}


/**
 * ログインボタン押下時
 */
try {
    if ($postData["login"]) {
        if ($userData = loginCheck($postData, $config)) {
            /* ログイン成功 */
            $session = new CuMAS_Session($config);
            $session->setLoginData($userData);
            header('location: contact_search_result.php');
            exit;
        }
        $view->assign("message", "ユーザ名またはパスワードが間違っています。");
    }
} catch (PDOException $e) {
    Cumas_Exception::log_s($logFacility, __FILE__, $e->getMessage());
    Cumas_Exception::printErr();
    exit;
} catch (CuMAS_Exception $e) {
    $e->log($logFacility, __FILE__);
    $e->printErr();
    exit;
}

try {
    $view->display();
} catch (CuMAS_Exception $e) {
    $e->log($logFacility, __FILE__);
    $e->printErr();
    exit;
}




/* End of file login.php */
