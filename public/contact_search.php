<?php
include_once '../lib/cumas_common.php';

// ローカル関数 {{{
/*
 * 画面再表示の際に、Smartyの日付リスト関数へ渡すダミーの値を作成する
 *
 * @param array		numericな要素を持つ配列
 * @return array	日付文字列を要素に持つ配列
 */
function makeDummyDate($target)
{
    $p = filter_input(
        INPUT_POST,
        $target,
        FILTER_DEFAULT,
        FILTER_REQUIRE_ARRAY
    );

    // 必ず存在する日付となるように1999年の1月を使う
    // また、Smartyでメニューの0番目を表示させるにnullを指定する
    $ret['Year']  = $p['Year']  ? "{$p['Year']}-01-01" : null;
    $ret['Month'] = $p['Month'] ? "1999-{$p['Month']}-01" : null;
    $ret['Day']   = $p['Day']   ? "1999-01-{$p['Day']}" : null;

    return $ret;
}
// }}}

// {{{ ローカルクラス
/**
 * このページの入力値全てを担うローカルクラス
 * 入力値チェックおよび検索条件の生成を行う
 */
class InputHandler
{
    // 入力値チェックモジュール {{{
    // 日付フォームの整形モジュール {{{
    private function makeStartDate($date)
    {
        // 空欄を考慮しながら日付を組み立てる
        if (($date['Year'] + $date['Month'] + $date['Day']) == 0) {
            return "";
        }

        $year  = $date['Year']  ?: date('Y');
        $month = $date['Month'] ?: 1;
        $day   = $date['Day']   ?: 1;

        if (!checkdate($month, $day, $year)) {
            return false;
        }

        return "{$year}-{$month}-{$day}";
    }

    private function makeEndDate($date)
    {
        if (($date['Year'] + $date['Month'] + $date['Day']) == 0) {
            return "";
        }

        $year  = $date['Year']  ?: date('Y');
        $month = $date['Month'] ?: 12;
        $day   = $date['Day']
                 ?: date('d', strtotime("last day of {$year}-{$month}"));
        if (!checkdate($month, $day, $year)) {
            return false;
        }

        // 検索終端は開区間で条件を作りたいので、+1日を指定
        $end = "{$year}-{$month}-{$day}";
        return date('Y-m-d', strtotime("$end +1 day"));
    }

    /**
     * 検索条件として使えるように、日付フォームからの入力値を整形
     */
    public function setupDate($target)
    {
        // 画面に出すメッセージ用のハッシュマップ
        $DATE_ERROR = [
            'inquiry'  => 'お問い合わせ日',
            'start'    => '対応開始日',
            'complete' => '完了日',
        ];

        $post_s = filter_input(
            INPUT_POST,
            "{$target}_s",
            FILTER_DEFAULT,
            FILTER_REQUIRE_ARRAY
        );
        $post_e = filter_input(
            INPUT_POST,
            "{$target}_e",
            FILTER_DEFAULT,
            FILTER_REQUIRE_ARRAY
        );

        $start = $this->makeStartDate($post_s);
        // inputが全て空なら空文字が返される(ので、falseで型までチェック)
        if ($start === false) {
            throw new InputHandlerException(
                $DATE_ERROR[$target]." (先頭) の指定が不正です。"
            );
        }
        $end = $this->makeEndDate($post_e, false);
        if ($end === false) {
            throw new InputHandlerException(
                $DATE_ERROR[$target]." (末尾) の指定が不正です。"
            );
        }

        // 日付指定の遡上判定(※ 24h*60min*60sec = 86400)
        if (strtotime($start) > (strtotime($end) - 86400)) {
            throw new InputHandlerException(
                $DATE_ERROR[$target]." の指定が不正です。");
        }

        $this->$target = ['start' => $start, 'end' => $end];

        return $this;
    }
    // }}}

    /**
     * ステータスの入力が空だったらエラーとする
     */
    public function checkStatus()
    {
        if (empty($_POST['status'])) {
            throw new InputHandlerException(
                "最低1つのステータスを指定して下さい。",
                InputHandlerException::STATUS_NULL
            );
        }

        // POSTインジェクション対策
        foreach ($_POST['status'] as $status) {
            if (!is_numeric($status)) {
                throw new InputHandlerException(
                    "ステータスの指定が不正です。",
                    InputHandlerException::STATUS_NULL
                );
            }
        }

        return $this;
    }
    //	入力値チェックモジュール }}}

    // 検索条件の生成 {{{
    public function makeSearchConditions()
    {
        // SQL文作成用
        $items = [];
        $params = [];

        if ($var = filter_input(INPUT_POST, 'ca_id', FILTER_VALIDATE_INT)) {
            $items['ca_id'] = "ca_id = :ca_id";
            $params['ca_id'] = $var;
        }
        if ($var = filter_input(INPUT_POST, 'us_id', FILTER_VALIDATE_INT)) {
            $items['us_id'] = "us_id = :us_id";
            $params['us_id'] = $var;
        }
        if ($var = filter_input(INPUT_POST, 'operator', FILTER_VALIDATE_INT)) {
            $items['co_operator'] = "co_operator = :co_operator";
            $params['co_operator'] = $var;
        }
        if ($var = filter_input(INPUT_POST, 'from')) {
            $items['ma_from_addr'] = "ma_from_addr LIKE :ma_from_addr";
            $params['ma_from_addr'] = "%$var%";
        }
        if ($var = filter_input(INPUT_POST, 'subject')) {
            $items['ma_subject'] = "ma_subject LIKE :ma_subject";
            $params['ma_subject'] = "%$var%";
        }
        if ($var = filter_input(INPUT_POST, 'comment')) {
            $items['co_comment'] = "co_comment LIKE :co_comment";
            $params['co_comment'] = "%$var%";
        }

        /*
         * ステータス条件
         * POSTが空でない配列で、各要素が数字であることはチェック済みとする
         */
        $items['co_status'] = "("
                 . implode(" or "
                           , array_map(function($s){return "co_status = $s";}
                                       , $_POST['status'])
                           )
                 . ")";

        /*
         * 日付処理
         * 区間は [,) で作る
         * (終端は+1日で作成されている)
         */
        if ($this->inquiry['start']) {
            $items['inquiry_s'] = "co_inquiry >= :inquiry_s";
            $params['inquiry_s'] = $this->inquiry['start'];
        }
        if ($this->inquiry['end']) {
            $items['inquiry_e'] = "co_inquiry < :inquiry_e";
            $params['inquiry_e'] = $this->inquiry['end'];
        }

        if ($this->start['start']) {
            $items['start_s'] = "co_start >= :start_s";
            $params['start_s'] = $this->start['start'];
        }
        if ($this->start['end']) {
            $items['start_e'] = "co_start < :start_e";
            $params['start_e'] = $this->start['end'];
        }

        if ($this->complete['start']) {
            $items['complete_s'] = "co_complete >= :complete_s";
            $params['complete_s'] = $this->complete['start'];
        }
        if ($this->complete['end']) {
            $items['complete_e'] = "co_complete < :complete_e";
            $params['complete_e'] = $this->complete['end'];
        }

        return [$items, $params];
    }
    // }}}
}
class InputHandlerException extends EXCEPTION
{
    const STATUS_NULL = 1;
}
// }}}



/**
 * mainの処理
 */

// 検索ボタン押下時
if (filter_input(INPUT_POST, 'searchButton')) {
    try {
        $input = new InputHandler();

        // 入力値チェックし、OKだったら値を整形
        $input->checkStatus()
              ->setupDate('inquiry')
              ->setupDate('start')
              ->setupDate('complete');

        // チェックした値を使って検索条件を作成
        list($where_items, $params) = $input->makeSearchConditions();

        /* 処理成功 ＆ 画面遷移 */
        $session->cut('pageNum');
        $session->setSearchConditions($where_items, $params);
        $_POST = NULL;
        header('location: contact_search_result.php');
        exit;
    } catch (InputHandlerException $e) {
        $view->assign('message', $e->getMessage());
        $view->assign('inquiry_s', makeDummyDate('inquiry_s'));
        $view->assign('inquiry_e', makeDummyDate('inquiry_e'));
        $view->assign('start_s', makeDummyDate('start_s'));
        $view->assign('start_e', makeDummyDate('start_e'));
        $view->assign('complete_s', makeDummyDate('complete_s'));
        $view->assign('complete_e', makeDummyDate('complete_e'));
        if ($e->getCode() == InputHandlerException::STATUS_NULL) {
            $_POST['status'] = [];
        }
    }
}

// 表示用データを格納
$view->assign('incomplete', $config->incomplete); // 再表示では使わないけど
$view->assign('startyear', $config->startyear);
try {
    // ユーザとステータス, カテゴリ一覧を取得
    $db = CuMAS_PDO::getInstance($config);
    $view->assign("category_tab", $db->getActiveCategories());
    $view->assign("user_tab", $db->getActiveUsers());
    $view->assign("status_tab", $db->getAllStatus());
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
/* End of file contact_search.php */
