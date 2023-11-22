<?php
/**
 * 全ページ共有の処理を記述します。
 * 各ページで最初にこのファイルをインクルードしてください。
 */

// only for debug
//ini_set('display_errors', 1);

define("LOG_DEFAULT", LOG_LOCAL4);

require_once '../lib/cumas_config.php';
require_once '../lib/cumas_session.php';
require_once '../lib/cumas_smarty.php';
require_once '../lib/cumas_pdo.php';

try {
    $config = new CuMAS_Config();
} catch (CuMAS_Exception $e) {
    $e->log(LOG_DEFAULT, __FILE__);
    $e->printErr();
    exit;
}

$logFacility = DgCommon_set_logfacility($config->syslogfascility);

try {
    $view = new CuMAS_Smarty();
} catch (CuMAS_Exception $e) {
    $e->log($logFacility, __FILE__);
    $e->printErr();
    exit;
}

/* ログイン画面ではセッションチェックを行わない */
if (empty($login_mode)) {
    try {
        $session = new CuMAS_Session($config);

        // ヘッダメニューのログアウトボタン処理
        if (filter_input(INPUT_POST, 'logout')) {
            $session->logout();
        }

        $session->check();
        $view->assign('adminFlag', $session->isAdmin());
        $s = $session->getLoginUserData();
        $view->assign('userName', $s["us_name"]);

        $view->assign('message', $session->cut('message'));
    } catch (CuMAS_Exception $e) {
        $e->log($logFacility, __FILE__);
        $e->printErr();
        exit;
    } catch (CuMAS_SessionException $e) {
        $e->exitToLoginPage();
        exit;
    }
}

/* End of file cumas_common.php */
