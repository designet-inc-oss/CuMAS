<?php
include_once '../lib/cumas_common.php';

define("SEARCH_TMPL", <<<'EOD'
SELECT ta_id,ta_category,ta_user,ta_registuser
 , to_char(ta_post, 'YYYY/MM/DD HH24:MI') as ta_post
 , ta_repmode,ta_repday,ta_registuser
 , ta_subject,ta_body,ta_comment
 , us_name,us_id,us_active
 FROM task_tab
 LEFT OUTER JOIN user_tab ON task_tab.ta_registuser = user_tab.us_id
 %1$s
 ORDER BY ta_post ASC NULLS FIRST
 LIMIT %2$d OFFSET %3$d
EOD
);
define("COUNT_TMPL", <<<'EOD'
SELECT COUNT(*)
 FROM task_tab
 LEFT OUTER JOIN user_tab ON task_tab.ta_registuser = user_tab.us_id
 %s
EOD
);

class PrepareWhereItems
{

    public function withCategoryId($where_items, $params, $ta_category)
    {
        //ca_id 検索句、パラメータの追加・更新
        $where_items['ta_category'] = "ta_category = :ta_category";
        $params['ta_category'] = $ta_category;

        return [$where_items, $params];
    }

    public function withoutSearchAllUsers($where_items, $params, $user_id)
    {
        //ta_registuser検索句、パラメータの追加・更新
        $where_items['ta_registuser'] = "ta_registuser =" . $user_id;
        return [$where_items, $params];
    }

    public function withoutCategoryId($where_items, $params)
    {

        //配列where_items からカテゴリid のwhere句を削除
        if (isset($where_items['ta_category'])) {
            unset($where_items['ta_category']);
        }

        //配列params からカテゴリid の値を削除
        if (isset($params['ta_category'])) {
            unset($params['ta_category']);
        }

        return [$where_items, $params];
    }

    public function withSearchAllUsers($where_items, $params)
    {
        //配列where_items からカテゴリid のwhere句を削除
        if (isset($where_items['ta_registuser'])) {
            unset($where_items['ta_registuser']);
        }

        //配列params からカテゴリid の値を削除
        if (isset($params['ta_registuser'])) {
            unset($params['ta_registuser']);
        }

        return [$where_items, $params];
    }

}

/**
 * メインの処理
 */

/* 詳細表示画面への遷移 */
if ($jobId = filter_input(INPUT_POST, 'selectedJob', FILTER_VALIDATE_INT)) {
    $session->unsetTargetJob();         /* この前に扱っていたジョブをクリア */
    $session->setTargetJob('id', $jobId);
    $session->set('pageNum', filter_input(INPUT_POST, 'pageNum'));
    header('location: contact_detail.php');
    exit;
}

/* 検索条件リセットボタン */
if (filter_input(INPUT_POST, 'reset', FILTER_VALIDATE_INT)) {
    $session->cut('pageNum');
    $session->cut('searchConditionsTask');
}

/* ログインしているユーザ　*/
$s = $session->getLoginUserData();

/* POSTの取得 */
$formList = [
    "select_ca_id"        => FILTER_DEFAULT,       // カテゴリID
    "all_user_disp_mode"  => FILTER_VALIDATE_INT,  // 全ユーザ表示
];

$postDataSearch = filter_input_array(INPUT_POST, $formList);

if (isset($postDataSearch["all_user_disp_mode"]) 
    && $postDataSearch["all_user_disp_mode"] === 1) {
    $view->mode_check = "checked";
} else {
    $view->mode_check = "";
}

/* 検索条件取得 */
$tmp_a = $session->getSearchConditionsTask();

/* セッションに保存している検索条件が有れば */
if ($tmp_a !== false) {

    $pwitems = new PrepareWhereItems();

    /* 全ユーザ表示 */
    if (($view->adminFlag === true) 
        && ($postDataSearch["all_user_disp_mode"] === 1)) {
        $search_all = true;
    } else {
        $search_all = false;
    }
    
    /* 全ユーザのタスクを検索 */
    if ($search_all) {
        list(
             $where_phrase_data['where_items'],
             $where_phrase_data['params']
            )
            = $pwitems->withSearchAllUsers(
                  $tmp_a['where_items'],
                  $tmp_a['params']
              );

        //ページ番号初期化
        $session->set('pageNum', 1);

    } else {
        //where句にセットするデータ更新
        list(
             $where_phrase_data['where_items'],
             $where_phrase_data['params']
            )
            = $pwitems->withoutSearchAllUsers(
                  $tmp_a['where_items'],
                  $tmp_a['params'],
                  $s["us_id"]
              );

        //ページ番号初期化
        $session->set('pageNum', 1);
    }

    // カテゴリセレクトボックス「----」以外選択
    if ($select_ca_id = filter_input(
                                     INPUT_POST, 
                                     'select_ca_id', 
                                     FILTER_VALIDATE_INT
                                    )
       ) {
        //where句にセットするデータ更新
        list(
             $where_phrase_data['where_items'], 
             $where_phrase_data['params']
            ) 
            = $pwitems->withCategoryId(
                                       $where_phrase_data['where_items'],
                                       $where_phrase_data['params'],
                                       $select_ca_id
                                      );
       
        //ページ番号初期化
        $session->set('pageNum', 1);

    // カテゴリセレクトボックス「----」選択
    } else if ($select_ca_id === 0) {
        //where句にセットするデータ更新
        list(
             $where_phrase_data['where_items'], 
             $where_phrase_data['params']
            ) 
            = $pwitems->withoutCategoryId(
                                          $where_phrase_data['where_items'],
                                          $where_phrase_data['params']
                                         );

        //ページ番号初期化
        $session->set('pageNum', 1);
    
    }

/* 初期表示 */
} else {
    /* 管理者の場合 */
    if ($view->adminFlag === true) {
        /* SQL条件句作成*/
        $where_phrase_data = [
            'where_items' => [
                "ta_registuser" => "ta_registuser = " . $s["us_id"]
            ],
            'params' => [],
        ];

    /* 一般ユーザの場合 */
    } else {
        /* SQL条件句作成*/
        $where_phrase_data = [
            'where_items' => [
                "ta_registuser" => "ta_registuser = " . $s["us_id"]
            ],
            'params' => [],
        ];
    }
}

//セレクトボックスの値保持
$view->assign('sql_ca_id', isset($where_phrase_data['params']['ta_category']) 
                               ? $where_phrase_data['params']['ta_category'] 
                               : null);

//検索条件データの更新
$session->setSearchConditionsTask(
                              $where_phrase_data['where_items'], 
                              $where_phrase_data['params']
                             );

$sql_where = "";
if (count($where_phrase_data["where_items"]) > 0) {
    //where句の作成
    $sql_where = "WHERE " . implode(' AND ', $where_phrase_data['where_items']); 
}

/* DB検索処理 */
try {
    $db = CuMAS_PDO::getInstance($config);
    
    // アクティブなカテゴリ一覧を取得
    $view->assign('category_tab', $db->getActiveCategories());

    //POSTの取得
    $formList = [
        "delete"        => FILTER_DEFAULT,       // 削除ボタン
        "targetid"      => FILTER_VALIDATE_INT,  // タスクID
    ];

    $postData = filter_input_array(INPUT_POST, $formList);

    /* 削除ボタンが押された時 */
    if (isset($postData['delete'])) {

        /* タスクIDが不正の場合  */
        if ($postData["targetid"] === false) {
            $view->message = sprintf("アクセスの方法が不正です。");
        } else {
            $tasks = $db->getTaskById($postData['targetid']);
            if ($tasks === false) {
                $view->message = sprintf("該当のタスクは削除済みです。");
            } else {
                $db->deleteTaskById($postData['targetid']);
               $view->message =sprintf("タスク \"$tasks[ta_subject]\"を削除しました。");
            }
        }
    }

    //ページ数カウント
    $countSQL = sprintf(COUNT_TMPL, $sql_where);
    $totalHit = $db->fetchAll(
                              $countSQL, 
                              $where_phrase_data['params']
                             )[0]['count'];

    /* 検索結果が0件だったらこれ以降の処理は行わない */
    if ($totalHit == 0) {
        try {
            $view->assign([
                            'pageTotal' => 0,
                            'pageNum' => 0,
                            'searchResults' => [],
                          ]);
            $session->set('pageNum', 1);
            $view->display();
            exit;
        } catch (CuMAS_Exception $e) {
            $e->log($logFacility, __FILE__);
            $e->printErr();
            exit;
        }
    }

    /*
     * 検索範囲(ページ送り)を指定
     */
    // 全ページ数を計算
    $view->pageTotal = $totalPage = ceil($totalHit / $config->linesperpage);

    // POST値が存在したら再優先する
    $page = filter_input(INPUT_POST, 'pageNum');

    // セッションにページの情報があればそれを使う。初期表示時なら1ページ目
    if (!$page) {
        $page = $session->get('pageNum') ?: 1;
    }

    // 見ようとしていたページが最終ページより手前だったら最終ページを検索
    if ($totalPage < $page) {
        $page = $totalPage;
    }

    // ページ番号が確定したのでとりあえず各所にセット
    $view->assign('pageNum', $page);        /* hidden に埋める */
    $session->set('pageNum', $page);

    /*
     * SQL検索結果の取得を何件目から始めるかを指定するための数字。
     * 0だと1件目から、1だと2件目から。
     * もしlinesperpageが10で、3ページ目を表示するならば21件目から(= 20)。
     */
    $offset = $config->linesperpage * ($page - 1);

    $searchSQL = sprintf(SEARCH_TMPL, $sql_where, $config->linesperpage, $offset);
    $searchResults = $db->fetchAll(
        $searchSQL, 
        $where_phrase_data['params']
    );

    /* 全てユーザの取得 */
    $allUsers = $db->getAllUsers();
    
    /* 全てカテゴリの取得 */
    $allCategories = $db->getAllCategories();
 
    /* ログインしているユーザ　*/
    $s = $session->getLoginUserData();

    $idx = 0; 
    foreach ($searchResults as $data) {
 
        /* 初期値 */
        $searchResults[$idx]["ta_user_name"] = "";
        $searchResults[$idx]["ta_user_active"] = "";

        $searchResults[$idx]["ta_category_name"] = "";
        $searchResults[$idx]["ta_category_active"] = "";

        foreach ($allUsers as $user) {
            if ($user["us_id"] === $data["ta_user"]) {
                 $searchResults[$idx]["ta_user_name"] = $user["us_name"];
                 $searchResults[$idx]["ta_user_active"] = $user["us_active"];
                 break;
             }
        }

        foreach ($allCategories as $category) {
             if ($data["ta_category"] === $category["ca_id"]) {
                 $searchResults[$idx]["ta_category_name"] = $category["ca_name"];
                 $searchResults[$idx]["ta_category_active"] = $category["ca_active"];
                 break;
             }
        }
 
        $idx++; 
    }

    $view->searchResults = $searchResults;

} catch (PDOEXCEPTION $e) {
    Cumas_Exception::log_s($logFacility, __FILE__, $e->getMessage());
    Cumas_Exception::printErr();
    exit;
}

/* 表示 */
try {
    $view->display();
} catch (CuMAS_Exception $e) {
    $e->log($logFacility, __FILE__);
    $e->printErr();
    exit;
}
/* End of file contact_detail.php */
