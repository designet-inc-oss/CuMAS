<?php
include_once '../lib/cumas_common.php';

class Mod_Category_Exception extends Exception{}
class Mod_Category_PDO extends CuMAS_PDO
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
        $sql = "SELECT ca_name,ca_ident FROM category_tab WHERE"
             . " (ca_name = ? or ca_ident = ?) AND ca_id <> ? ";

        $stmt = $this->_pdo->prepare($sql);
        $stmt->execute(array(
                            $data['ca_name'],
                            $data['ca_ident'],
                            $data['targetId'],
                        ));

        $result = $stmt->fetchAll();
        if (!empty($result)) {
            $errMsg = [
                       'nerrMsg' => "",
                       'ierrMsg' => "",
                      ];

            foreach($result as $value) {
                if ($data['ca_name'] == $value['ca_name']) {
                    $errMsg['nerrMsg'] = "カテゴリ名";
                }
                if ($data['ca_ident'] == $value['ca_ident']) {
                    $errMsg['ierrMsg'] = "カテゴリ識別名";
                }
            }

            $errorMessage = "入力された " . implode(array_filter($errMsg, "strlen"), ",") ." は既に使われています。";
            throw new Mod_Category_Exception($errorMessage);
        }
    }

   /****
    * updateCategoryTab (担当者登録処理用関数)
    *
    * 引数  :$data --> web上で入力された値
    *
    * 返り値:編集後の名前
    ***/
    public function updateCategoryTab($data)
    {
        $items = [
                  'ca_name' => $data['ca_name'],
                  'ca_ident' => $data['ca_ident'],
                  'ca_active' => ($data['ca_active'] === "on") ? "true" : "false",
                 ];
        $sql = "UPDATE category_tab SET ca_name = :ca_name"
             . ", ca_active = :ca_active, ca_ident = :ca_ident"
             . " WHERE ca_id = :target RETURNING ca_name";
        $items['target'] = $data['targetId'];

        $stmt = $this->_pdo->prepare($sql);
        $ret = $stmt->execute($items);
        return $stmt->fetch(PDO::FETCH_COLUMN);
    }
}

//入力値チェック用のクラス
class checkCategoryData
{
    function __construct($data)
    {
        $this->ca_name  = $data['ca_name'];
        $this->ca_ident = $data['ca_ident'];
    }

    public function checkNull()
    {
        if (empty($this->ca_name)) {
            throw new CuMAS_Exception("カテゴリ名が入力されていません。");
        }
        if (empty($this->ca_ident)) {
            throw new CuMAS_Exception("カテゴリ識別名が入力されていません。");
        }
        return $this;
    }

    public function checkName()
    {
        if (strlen($this->ca_name) > 64) {
            throw new CuMAS_Exception("カテゴリ名の文字数が不正です。");
        }
        return $this;
    }

    public function checkIdent()
    {
        if (strlen($this->ca_ident) > 64) {
            throw new CuMAS_Exception("カテゴリ識別名の文字数が不正です。");
        }
        if (!preg_match("/^[a-zA-Z0-9-]+$/", $this->ca_ident)) {
            throw new CuMAS_Exception("カテゴリ識別名の形式が不正です。");
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
    header('location: category_list.php');
    exit;
}

//POSTの取得
$formList = [
    "mod"               => FILTER_DEFAULT,
    "return"            => FILTER_DEFAULT,
    "ca_name"           => FILTER_DEFAULT,
    "ca_ident"           => FILTER_DEFAULT,
    "ca_active"         => FILTER_DEFAULT,
    "targetId"          => FILTER_DEFAULT,
];

$postData = filter_input_array(INPUT_POST, $formList);

try {
    $db = Mod_Category_PDO::getInstance($config);
} catch (PDOEXCEPTION $e) {
    Cumas_Exception::log_s($logFacility, __FILE__, $e->getMessage());
    Cumas_Exception::printErr();
    exit;
}

//登録ボタンが押された時
if (isset($postData['mod'])) {
    try {
        //入力値チェック
        $register = new checkCategoryData($postData);
        $register->checkNull()->checkName()->checkIdent();

        $db->lockTable('category_tab');

        //被りの確認
        $db->checkOverlap($postData);

        //category_tabに登録
        $afterName = $db->updateCategoryTab($postData);

        $db->commit();
        $session->set('message', "カテゴリ {$afterName} を編集しました。");
        header('location: category_list.php');
        exit;

    //システムエラー
    } catch (PDOEXCEPTION $e) {
        empty($db) ?: $db->rollBack();
        Cumas_Exception::log_s($logFacility, __FILE__, $e->getMessage());
        Cumas_Exception::printErr();
        exit;

    //重複チェックにひっかかった場合
    } catch (Mod_Category_Exception $e) {
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
    if ($target = $session->cut('categoryIdToMod')) {
        $view->message = "選択した担当者情報を編集します。";
    } else {
        $session->set('message', "不正なアクセスです。");
        header('location: category_list.php');
        exit;
    }

    //hiddenタグによるPOST値で処理を行う。
    $postData["targetId"] = $target;

    $sql = "SELECT ca_name,ca_ident,ca_active"
         . " FROM category_tab WHERE ca_id = ?";
    try {
        $categoryData = $db->fetchAll($sql, $target)[0];
    } catch (PDOEXCEPTION $e) {
        Cumas_Exception::log_s($logFacility, __FILE__, $e->getMessage());
        Cumas_Exception::printErr();
        exit;
    }

    if (empty($categoryData)) {
        $session->set('message', "編集対象のカテゴリが存在しません。");
        header('location: category_list.php');
        exit;
    }

    //初期表示用にチェックボックスの値の初期化
    $postData['ca_name']    = $categoryData['ca_name'];
    $postData['ca_ident']    = $categoryData['ca_ident'];
    $postData['ca_active']    = $categoryData['ca_active'];
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
