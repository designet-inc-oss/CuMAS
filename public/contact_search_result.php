<?php
include_once '../lib/cumas_common.php';
define("SEARCH_TMPL", <<<'EOD'
SELECT co_id, co_parent, co_child_no
 , to_char(co_inquiry, 'YYYY/MM/DD HH24:MI') as co_inquiry
 , to_char(co_limit, 'YYYY/MM/DD') as co_limit
 , us_name, us_active
 , st_status, st_color
 , ma_subject, ma_from_addr
 FROM contact_tab
 LEFT OUTER JOIN user_tab ON co_us_id = us_id
 JOIN status_tab ON co_status = st_id
 JOIN mail_tab ON co_ma_id = ma_id
 %1$s
 ORDER BY co_parent DESC, co_child_no NULLS FIRST
 LIMIT %2$d OFFSET %3$d
EOD
);
define("COUNT_TMPL", <<<'EOD'
SELECT COUNT(*)
 FROM contact_tab
 LEFT OUTER JOIN user_tab ON co_us_id = us_id
 JOIN status_tab ON co_status = st_id
 JOIN mail_tab ON co_ma_id = ma_id
 %s
EOD
);

class PrepareWhereItems
{
    public function withCategoryId($where_items, $params, $ca_id)
    {
        //ca_id 検索句、パラメータの追加・更新
        $where_items['ca_id'] = "ca_id = :ca_id";
        $params['ca_id'] = $ca_id;

        return [$where_items, $params];
    }

    public function withoutCategoryId($where_items, $params)
    {

        //配列where_items からカテゴリid のwhere句を削除
        if (isset($where_items['ca_id'])) {
            unset($where_items['ca_id']);
        }

        //配列params からカテゴリid の値を削除
        if (isset($params['ca_id'])) {
            unset($params['ca_id']);
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
    $session->cut('searchConditions');
}

/* SQL条件句作成*/
$where_phrase_data = [
                      'where_items' => [
                                        'co_status' => $config->setupIncompleteCondition(),
                                       ],
                      'params' => [],
                     ];

//検索条件取得
$tmp_a = $session->getSearchConditions();
if ($tmp_a !== false) {

    $pwitems = new PrepareWhereItems();

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
                                       $tmp_a['where_items'],
                                       $tmp_a['params'],
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
                                          $tmp_a['where_items'],
                                          $tmp_a['params']
                                         );

        //ページ番号初期化
        $session->set('pageNum', 1);
    
    
    } else {
        //セレクトボックスが変更されなかった
        $where_phrase_data['where_items'] = $tmp_a['where_items'];
        $where_phrase_data['params'] = $tmp_a['params'];
    }
}

//セレクトボックスの値保持
$view->assign('sql_ca_id', isset($where_phrase_data['params']['ca_id']) 
                               ? $where_phrase_data['params']['ca_id'] 
                               : null);

//検索条件データの更新
$session->setSearchConditions(
                              $where_phrase_data['where_items'], 
                              $where_phrase_data['params']
                             );

//where句の作成
$sql_where = "WHERE " . implode(' AND ', $where_phrase_data['where_items']); 

/* DB検索処理 */
try {
    $db = CuMAS_PDO::getInstance($config);
    
    // アクティブなカテゴリ一覧を取得
    $view->assign('category_tab', $db->getActiveCategories());

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

    $view->assign('total', $totalHit);

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
    $view->searchResults = $db->fetchAll(
                                         $searchSQL, 
                                         $where_phrase_data['params']
                                        );

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
