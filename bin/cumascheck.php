<?php
/**
 * 未完了ジョブの通知を行います。
 * 設定ファイルを読み込み、そこで指定された期限を超えたジョブを探し
 * 担当者に定型メッセージを送信します。
 * 担当者が未定であればアクティブユーザ全員に投げます。
 */

define("LOG_DEFAULT", LOG_LOCAL4);
define("MAIL_TMPL_DIR", "../etc/");
define("MAIL_TMPL", "NoticeMail.txt");
define("DEFAULT_MAIL_FROM", "noreply@localhost.localdomain");
define("DEFAULT_SUBJECT", "Notification from CuMAS");
include_once '../lib/cumas_config.php';
include_once '../lib/cumas_pdo.php';
include_once '../lib/cumas_smarty.php';

try {
    $config = new CuMAS_Config();
} catch (CuMAS_Exception $e) {
    $e->log(LOG_DEFAULT, __FILE__);
    exit(1);
}
$logFacility = DgCommon_set_logfacility($config->syslogfascility);


try {
    // 遅延ジョブの一覧を取得
    $db = CuMAS_PDO::getInstance($config);
    $lateJobs = $db->fetchAll(makeLateJobsQuery($config));
    // もし0件だったら何もせず終了
    if (!$lateJobs) {
        exit(0);
    }

    $mailTmpl = new CuMAS_Smarty(
        ["escape_html" => false, "template_dir" => MAIL_TMPL_DIR]
    );

    /* mb_send_mail用設定 */
    mb_language("japanese");
    mb_internal_encoding("UTF-8");

    foreach ($lateJobs as $job) {
        // テンプレート置換タグのデータを格納
        $tags = makeTemplateTags($job);
        $tags['latedays'] = $config->latedays;
        $mailTmpl->assign($tags);

        // メールテンプレート解析
        // 第二引数を指定するとフェッチモード
        $mailData = parseMailTemplate($mailTmpl->display(MAIL_TMPL, true));

        // 送信者を判定。送信できる人が誰もいなかったら即終了。
        // これ以上ループしても無意味なため。
        if (!$mailTo = makeMailTo($job)) {
            throw CuMAS_Exception("All users are inactive.");
        }
        /* note *
         * 送信できる人がいないというのは、アクティブユーザが誰もいない場合
         * なので、おそらくなにかおかしなことが起こっている。
         * 最低限CuMASの管理者はアクティブのはず（でないとログインできない）
         * (もしくはアクティブユーザ全員にメルアドが設定されていない)
         */

        // それぞれのジョブに対して送信
        mb_send_mail(
            $mailTo,
            $mailData['subject'],
            $mailData['body'],
            $mailData['options']
        );
    }
} catch (PDOException $e) {
    $ce = new CuMAS_Exception($e->getMessage());
    $ce->log($logFacility, __FILE__);
    exit(1);
} catch (CuMAS_Exception $ce) {
    $ce->log($logFacility, __FILE__);
    exit(1);
}

exit(0);




//////////////////////////
// 以下、ローカル関数
//////////////////////////

/**
 * 検索条件を作る。
 * configで設定される2条件（未完了・日数）を満たし、かつ
 * 対応期日が（あれば）まだ来てないもの。
 *
 * @arg cumas_config
 * @return string
 */
function makeLateJobsQuery($config)
{
    /*
     * 完了ステータス条件を作る
     * WHERE (co_status = 0 or co_status = 1 or co_status = 2)
     * の（）の中身みたいの。
     * incompleteは数字の配列であることが読み込みチェックで保証されている
     * また、numericな文字列の配列となっている。
     */
    $sql_incomplete = implode(
        " or ",
        array_map(
            function ($s) { return "co_status = $s"; },
            $config->incomplete
        )
    );

    return "SELECT"
         . " co_id, co_child_no, co_parent"
         . ", to_char(co_inquiry, 'YYYY-MM-DD HH24:MI:SS') as co_inquiry"
         . ", to_char(co_lastupdate, 'YYYY-MM-DD HH24:MI:SS') as co_lastupdate"
         . ", to_char(co_limit, 'YYYY-MM-DD HH24:MI:SS') as co_limit"
         . ", us_name, us_mail, us_active, st_status"
         . ", ma_from_addr, ma_subject"
         . ", ca.ca_name"
         . " FROM contact_tab"
         . " LEFT OUTER JOIN user_tab ON co_us_id = us_id"
         . " JOIN status_tab ON co_status = st_id"
         . " JOIN mail_tab ON co_ma_id = ma_id"
         . " LEFT OUTER JOIN category_tab ca ON contact_tab.ca_id = ca.ca_id"
         . " WHERE"
         . "  co_lastupdate < (current_timestamp + '-{$config->latedays} days')"
         . " AND (co_limit < current_date OR co_limit IS NULL)"
         . " AND ({$sql_incomplete})";
    /*
     * 可変部分は数字であることが保証されている
     * (ので、エスケープの必要なし)
     */
}

/**
 * 検索結果からテンプレートタグ置換データを整形
 * @arg array
 * @return array $tags
 */
function makeTemplateTags($job)
{
    /* カテゴリ名をセット */
    $tags["category"] = $job["ca_name"];

    /* URLに使うジョブ番号をセット */
    $tags["co_id"] = $job["co_id"];

    /* 表示するお問い合わせ番号 */
    if ($job["co_child_no"]) {
        $tags["contact_no"] = "{$job['co_parent']}.{$job['co_child_no']}";
    } else {
        $tags["contact_no"] = $job["co_id"];
    }

    // 担当者がいなければnull、非アクティブならfalseなのでこの条件でよい。
    $tags["user"] = $job["us_active"] ? $job["us_name"] : " - ";

    $tags["status"]  = $job["st_status"];
    $tags["sender"]  = $job["ma_from_addr"] ?: " - ";
    $tags["subject"] = $job["ma_subject"];

    $tags["inquiry"]    = $job["co_inquiry"]    ?: " - ";
    $tags["lastupdate"] = $job["co_lastupdate"] ?: " - ";
    $tags["limit"]      = $job["co_limit"]      ?: " - ";
    // *note*: '?:'はエルビス演算子

    return $tags;
}

/**
 * 1件のジョブから、通知メールの送信先を作成する。
 * 全ユーザのリストをstaticでもつ(必要になったときに1度だけ検索する)
 *
 * @args   array
 * @return string
 **/
function makeMailTo($job)
{
    global $config;
    static $allUsersList = "";

    // 担当者がいて、かつアクティブならその人に送信する
    // いなければnull、非アクティブならfalseなのでこの条件でよい
    if ($job["us_active"]) {
        return $job["us_mail"];
    }

    // 以下、アクティブユーザ全員に送りたい場合

    // 既に検索済みならばそれを返す
    if ($allUsersList !== "") {
        return $allUsersList;
    }

    // singletonパターン
    $db = CuMAS_PDO::getInstance($config);
    $activeUsers = $db->fetchAll(
        "SELECT us_mail FROM user_tab WHERE us_active = true"
    );

    // 配列の配列なので素直にimplode()できない
    foreach ($activeUsers as $x) {
        if ($x["us_mail"]) {
            $tmpList[] = $x["us_mail"];
        }
    }

    $allUsersList = implode(',', $tmpList);

    // アクティブな人がいなかったらメール送らない
    // もしくはだれもメールアドレスを持ってないとき
    if (empty($allUsersList)) {
        $allUsersList = false;
    }

    return $allUsersList;
}

/**
 * メールテンプレートを解析する。
 * テンプレートにはFromヘッダやSubjectヘッダが記述されるかもしれない
 *
 * @arg string
 * @return array
 */
function parseMailTemplate($tmpl)
{
    // 改行＋改行を探してヘッダ分割
    $parts = explode(PHP_EOL . PHP_EOL, $tmpl, 2);
    if (!isset($parts[1])) {
        // ヘッダが無かったら全部本体
        return [
            'subject' => DEFAULT_SUBJECT,
            'body' => $tmpl,
            'options' => DEFAULT_MAIL_FROM,
        ];
    }

    // ヘッダ解析
    $headers = [
        "from" => "",
        "subject" => "",
        "option" => "",
    ];
    foreach (explode(PHP_EOL, $parts[0]) as $line) {
        $oneHeader = array_map('trim', explode(":", $line, 2));
        if (!isset($oneHeader[1])) {
            throw new CuMAS_Exception(
                "Mail template has wrong header. : '$line'"
            );
        }
        switch (strtolower($oneHeader[0])) {
            case 'subject':
                $headers["subject"] = $oneHeader[1];
                break;

            case 'from':
                $tmpFrom = explode('<', $oneHeader[1], 2);
                /* <>を含まない形式だったらそのままアドレスとして使う */
                if (!isset($tmpFrom[1])) {
                    $headers["from"] = $oneHeader[1];
                    break;
                }

                /* 前半をmimeエンコード */
                $headers["from"] = mb_encode_mimeheader(
                    mb_convert_encoding(trim($tmpFrom[0]), "JIS", "UTF-8")
                );

                $headers["from"] .= " <${tmpFrom[1]}";
                break;

            /* Toヘッダはテンプレートファイルには書かない */
            case 'to':
                CuMAS_Exception::log_s(
                    $logFacility,
                    __FILE__,
                    "Mail template isn't allowed 'To' header."
                );
                break;

            default:
                /* 最後にFromをつけるので改行は全部つけちゃってOK */
                $headers["option"] .= "{$line}\r\n";
                break;
        }
    }       /* ヘッダ解析ここまで */

    return [
        'subject' => $headers['subject'] ?: DEFAULT_SUBJECT,
        'body' => $parts[1],
        'options' => $headers["option"] ."From:".$headers["from"] ?: DEFAULT_MAIL_FROM
    ];
}

/* vim: set filetype=php: */
/* End of file cumascheck.php */
