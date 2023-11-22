<?php
include_once '../lib/cumas_common.php';

/**
 *main処理
 */
// ガーベージコレクト
$session->cut('userIdToMod');

//admin_flgの確認
if (!$session->isAdmin()) {
    $session->set('message', "管理者権限がありません。");
    header('location: contact_search_result.php');
    exit;
}

// 編集ボタン
if ($modUserId = filter_input(INPUT_POST, 'modUser', FILTER_VALIDATE_INT)) {
    $session->set('userIdToMod', $modUserId);
    header('location: user_mod.php');
    exit;
}

// ユーザ一覧の取得
try {
    $sql = "SELECT us_id, us_name, us_mail, us_active, us_admin_flg"
         . " FROM user_tab ORDER BY us_id";
    $db = CuMAS_PDO::getInstance($config);
    $view->assign('user_tab', $db->fetchAll($sql));
} catch (PDOEXCEPTION $e) {
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
