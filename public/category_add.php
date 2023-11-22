<?php
include_once '../lib/cumas_common.php';

class Add_Category_Exception extends Exception{}
class Add_Category_PDO extends CuMAS_PDO
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
        $sql = "SELECT ca_name,ca_ident FROM category_tab WHERE"
              ." ca_name = ? or ca_ident = ?";

        $stmt = $this->_pdo->prepare($sql);
        $stmt->execute(array(
                            $data['ca_name'],
                            $data['ca_ident'],
                            ));

        $result = $stmt->fetchAll();
        if (!empty($result)) {
            $errMsg = [
                       'nerrMsg' => "",
                       'ierrMsg' => "",
                      ];

            foreach ($result as $value) {
                if ($data['ca_name'] == $value['ca_name']) {
                    $errMsg['nerrMsg'] = "カテゴリ名";
                }
                if ($data['ca_ident'] == $value['ca_ident']) {
                    $errMsg['ierrMsg'] = "カテゴリ識別名";
                }
            }

            $errorMessage = "入力された " . implode(array_filter($errMsg, "strlen"), ",") ." は既に使われています。";
            throw new Add_Category_Exception($errorMessage);
        }
    }

   /****
    *insertCategoryTab (担当者登録処理用関数)
    *
    * 引数  :$data --> web上で入力された値
    *
    * 返り値:無し
    ***/
    public function insertCategoryTab($data)
    {
        $sql = "INSERT INTO category_tab (ca_name,ca_ident,ca_active)"
              ." VALUES (?,?,?)";

        $stmt = $this->_pdo->prepare($sql);

        $stmt->execute(array(
                            $data['ca_name'],
                            $data['ca_ident'],
                            ($data['ca_active'] === "on") ? "true" : "false",
                            ));
    }
}

//入力値チェック用のクラス
class checkCategoryData
{
    public function __construct($data)
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

//admin_flgの確認
if (!$session->isAdmin()) {
    $session->set('message', sprintf("管理者権限がありません。"));
    header('location: contact_search_result.php');
    exit;
}

// メッセージがあれば使うしなければデフォルト
$view->message = $session->cut('message') ?: "新規のカテゴリを追加します。";

//POSTの取得
$formList = [
    "add"       => FILTER_DEFAULT,
    "return"    => FILTER_DEFAULT,
    "ca_name"   => FILTER_DEFAULT,
    "ca_ident"  => FILTER_DEFAULT,
    "ca_active" => FILTER_DEFAULT,
];

$postData = filter_input_array(INPUT_POST, $formList);

//戻るボタンが押された時
if (isset($postData['return'])) {
    header('location: category_list.php');
    exit;
}

//登録ボタンが押された時
if (isset($postData['add'])) {
    try {
        //入力値チェック
        $register = new checkCategoryData($postData);
        $register->checkNull()->checkName()->checkIdent();

        $db = Add_Category_PDO::getInstance($config);
        $table = array('category_tab');
        $db->lockTable($table);

        //被りの確認
        $db->checkOverlap($postData);

        //category_tabに登録
        $db->insertCategoryTab($postData);

        $db->commit();
        $session->set('message', sprintf("カテゴリ "
                                        . $postData['ca_name']
                                        . " を登録しました。"));
        header('location: category_list.php');
        exit;

    //システムエラー
    } catch (PDOEXCEPTION $e) {
        empty($db) ?: $db->rollBack();
        Cumas_Exception::log_s($logFacility, __FILE__, $e->getMessage());
        Cumas_Exception::printErr();
        exit;

    //重複チェックにひっかかった場合
    } catch (Add_Category_Exception $e) {
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
    $postData['ca_active'] = 'on';
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
