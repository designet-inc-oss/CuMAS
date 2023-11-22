<?php
include_once '../lib/cumas_common.php';

/**
 *main処理
 */
// ガーベージコレクト
$session->cut('categoryIdToMod');

//admin_flgの確認
if (!$session->isAdmin()) {
    $session->set('message', "管理者権限がありません。");
    header('location: contact_search_result.php');
    exit;
}

// 編集ボタン
if ($modCategoryId = filter_input(INPUT_POST, 'modCategory', FILTER_VALIDATE_INT)) {
    $session->set('categoryIdToMod', $modCategoryId);
    header('location: category_mod.php');
    exit;
}

// カテゴリ一覧の取得
try {
    $sql = "SELECT ca_id, ca_name, ca_ident, ca_active"
         . " FROM category_tab ORDER BY ca_id";
    $db = CuMAS_PDO::getInstance($config);
    $view->assign('category_tab', $db->fetchAll($sql));
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
