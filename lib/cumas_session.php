<?php
/**
 * CuMAS library - ユーザ認証のセッション管理
 * 基本的には、$_SESSIONへのアクセスはこのクラスを通して行うべき。
 * 直接アクセスしてもよいが、仕様変更に対して柔軟性を欠く。
 *
 * CuMASにおいて、$_SESSIONは最低限次のような形をしているものとする。
 * (これ以外に、messageおよびtargetjobをもつページが存在する。)
 * var_dump($_SESSION);
 * array(2) {
 *   ["userData"]=>
 *   array(3) {
 *     ["us_id"]	=> int		ログインユーザの担当者ID
 *     ["us_name"]	=> string	ログインユーザの登録名
 *     ["us_admin_flg"]	=> int(マクロ)	ログインユーザの権限
 *   }
 *   ["loginData"]=>
 *   array(2) {
 *     ["remoteAddress"] => string(IPアドレス)		アクセス元IPアドレス
 *     ["lastUpdate"]    => int(Unix time stamp)	最終アクセス時間
 *   }
 * }
 */

class CuMAS_SessionException extends Exception {
    /**
     * セッション異常時にログインページヘ遷移させる
     *
     * @return none: exitする
     * @author mamori
     **/
    public function exitToLoginPage()
    {
        header('location: login.php?s=' . $this->message);
        exit;
    }
}

/**
 * CuMAS_Session
 */
class CuMAS_Session {
    /**
     * 管理者権限あるなしのマクロ。
     * falseと同値な値を使わないこと！
     *
     * @var int
     **/
    const ADMIN = 1;
    const USER  = 2;

    /**
     * セッションエラー時にログイン画面へ渡すGETの値
     * falseと同値な値を使わないこと！
     *
     * @var int
     **/
    const TIMEOUT = 1;
    const ERROR   = 2;
    const LOGOUT  = 3;


    /**
     * セッションが開始されているかどうかのフラグ
     *
     * @var bool
     **/
     protected $isStarted = false;

    /**
     * コンストラクタでセットした後はさわらないこと！
     * @var numeric
     */
    protected $timeoutLimit;

    /****
     * メソッドここから
     ****/

    public function __construct(CuMAS_Config $config) {
        $this->timeoutLimit = $config->sessiontimeout;
    }

    /**
     * session_start()をする。2重スタートを回避する
     *
     * @throws CuMAS_Exception    session_start()失敗はさすがにシステムエラー
     * (でもsession_start()がエラーを返すのはPHP5.3以降だった！)
     **/
    public function start() {
        if ($this->isStarted)
            return;

        if (!session_start()) {
            /* after PHP5.3 */
            require_once '../lib/cumas_exception.php';
            throw new CuMAS_Exception("Faild to start session.");
        }

        $this->isStarted = true;
        return;
    }

    protected function destroy() {
        $this->start();
        $_SESSION = [];

        $sessName = session_name();
        if (isset($_COOKIE[$sessName]))
            setcookie($sessName, '', time() - 3600);

        session_destroy();
        $this->isStarted = false;
        if (isset($_POST)) {
            $_POST = null;
        }
    }

    public function logout() {
        $this->destroy();
        header('location: login.php?s=' . self::LOGOUT);
        exit;
    }

    /**
     * セッションデータのやりくり
     */
    public function issetKey($key) {
        return !!$this->get($key);
    }
    public function set($key, $value) {
        $_SESSION[$key] = $value;
    }
    public function get($key) {
        return empty($_SESSION[$key]) ? null : $_SESSION[$key];
    }
    public function cut($key) {
        if(!isset($_SESSION[$key])) {
            return null;
        }
        $ret = $_SESSION[$key];
        unset($_SESSION[$key]);
        return $ret;
    }

    /**
     * ログイン成功時に呼ばれる。
     *
     * @param string $username ログインフォームに入力された値
     * @param string $role     ユーザの権限(staticなプロパティ)
     **/
    public function setLoginData($userData) {
        $this->start();
        /* 既にあるセッション情報は全て破棄 */
        $_SESSION = [];

        /* 新たにセッション情報を登録する */
        $_SESSION["userData"]  = [];
        $_SESSION["userData"]["us_id"]  = $userData["us_id"];
        $_SESSION["userData"]["us_name"]  = $userData["us_name"];
        $_SESSION["userData"]["us_admin_flg"]  = $userData["us_admin_flg"];


        $_SESSION["loginData"] = [
            "remoteAddress"	=> filter_input(INPUT_SERVER, "REMOTE_ADDR"),
            "lastUpdate"	=> $_SERVER["REQUEST_TIME"],
        ];
    }

    /**
     * ログインユーザの、ログイン時にDBから取得した値を取り出す
     */
    public function getLoginUserData($key = null)
    {
        return $key ? $_SESSION['userData'][$key] : $_SESSION['userData'];
    }

    /**
     * ログインユーザが管理者権限を持っているかどうかを判定
     * @return bool
     */
    public function isAdmin()
    {
        return $_SESSION['userData']['us_admin_flg'] == self::ADMIN;
    }

    /**
     * 各ページでのセッションチェック関数
     */
    public function check() {
        $this->start();

        if (!isset($_SESSION["loginData"])) {
            $this->destroy();
            throw new CuMAS_SessionException(self::ERROR);
        }

        if ($_SESSION["loginData"]["remoteAddress"] !=
            filter_input(INPUT_SERVER, "REMOTE_ADDR"))
        {
            $this->destroy();
            throw new CuMAS_SessionException(self::ERROR);
        }

        $age = $_SERVER["REQUEST_TIME"] - $_SESSION["loginData"]["lastUpdate"];
        if ($age > $this->timeoutLimit) {
            $this->destroy();
            throw new CuMAS_SessionException(self::TIMEOUT);
        }

        /* lastUpdateを更新 */
        $_SESSION["loginData"]["lastUpdate"] = $_SERVER["REQUEST_TIME"];
    }

    /**
     * ジョブの更新や削除などを行う画面において、現在取扱中のデータの
     * セッション管理をする。
     **/
    public function setTargetJob($key, $value = null)
    {
        // 存在しなかったら初期化
        if (!isset($_SESSION['targetJob'])) {
            $_SESSION['targetJob'] = [];
        }

        if ($value !== null) {
            $_SESSION['targetJob'][$key] = $value;
        } else if (is_array($key)) {
            // + は重複するキーを上書きしない
            $_SESSION['targetJob'] = $key + $_SESSION['targetJob'];
        } else {
            throw new CuMAS_Exception("TargetJob can store only string");
        }
    }
    public function getTargetJob()
    {
        return isset($_SESSION['targetJob']) ? $_SESSION['targetJob'] : NULL;
    }
    public function unsetTargetJob()
    {
        unset($_SESSION['targetJob']);
    }


    /**
     * 検索条件のプリペアドステートメントとセットする値を取得する。
     *
     * @return array	'statement', 'params'
     **/
    public function getSearchConditions()
    {
        if (empty($_SESSION['searchConditions'])) {
            return false;
        }

        if (!isset($_SESSION['searchConditions']['where_items'])
            || !isset($_SESSION['searchConditions']['params']))
        {
            return false;
        }

        return $_SESSION['searchConditions'];
    }

    /**
     * 検索条件をセッションにセットする
     *
     * @return none
     **/
    public function setSearchConditions($where_items = array(), $params = array())
    {
        $this->start();
        $_SESSION['searchConditions']['where_items'] = $where_items;
        $_SESSION['searchConditions']['params'] = $params;
    }


    /**
     * 検索条件のプリペアドステートメントとセットする値を取得する。
     *
     * @return array	'statement', 'params'
     **/
    public function getSearchConditionsTask()
    {
        if (empty($_SESSION['searchConditionsTask'])) {
            return false;
        }

        if (!isset($_SESSION['searchConditionsTask']['where_items'])
            || !isset($_SESSION['searchConditionsTask']['params']))
        {
            return false;
        }

        return $_SESSION['searchConditionsTask'];
    }

    /**
     * 検索条件をセッションにセットする
     *
     * @return none
     **/
    public function setSearchConditionsTask($where_items = array(), $params = array())
    {
        $this->start();
        $_SESSION['searchConditionsTask']['where_items'] = $where_items;
        $_SESSION['searchConditionsTask']['params'] = $params;
    }


}

/* End of file cumas_session.php */
