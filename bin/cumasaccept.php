<?php
include_once '../lib/cumas_config.php';
include_once '../lib/cumas_mailparse.php';
include_once '../lib/libutil';

define("LOG_DEFAULT", LOG_LOCAL4);
define("DIR_ATTACHMENT", "attach");

/**
 *設定ファイル読み込み処理
 */
try {
    $config = new CuMAS_Config();
} catch (CuMAS_Exception $e) {
    $e->log(LOG_DEFAULT, __FILE__);
    exit;
}
$logFacility = DgCommon_set_logfacility($config->syslogfascility);

/**
 *メールデータ解析
 */
function getMailData($mparser)
{
    global $logFacility;

    $all_to = array();
    $all_cc = array();

    /* ヘッダーの取得 */
    $from = $mparser->getHeader('from');
    $to = $mparser->getHeader('to');
    $cc = $mparser->getHeader('cc');
    $references = $mparser->getHeader('references');
    $subject = $mparser->getHeader('subject');
    $message_id = $mparser->getHeader('message-id');
    $date = $mparser->getHeader('date');

    /* マルチパートをケアしながら、メールの本文を探す*/
    /* まず、text/plainを探す */
    $body = $mparser->getMessageBody('text');
    if ($body === false) {
	/* text/plainをもたないメールだった場合、text/htmlを探す */
        $body = $mparser->getMessageBody('html');
        if ($body === false) {
            $body  = "";
	}	
    }

    /* メールデータのToの整形 */
    $arr_to_tmp = mailparse_rfc822_parse_addresses($to);
    foreach ($arr_to_tmp as $tmp_to) {
        $tmp_addr = filter_var($tmp_to['address'], FILTER_VALIDATE_EMAIL);
        if ($tmp_addr === false) {
            Cumas_Exception::log_s(
                $logFacility,
                __FILE__,
                "Invalid Format toAddress"
            );
            /* NOTE: 不正なメールアドレスを保存てない */
        } else {
            $all_to[] = $tmp_addr;
        }
    }

    /* メールデータのCcの整形 */
    $arr_cc_tmp = mailparse_rfc822_parse_addresses($cc);
    foreach ($arr_cc_tmp as $tmp_cc) {
        $tmp_addr = filter_var($tmp_cc['address'], FILTER_VALIDATE_EMAIL);
        if ($tmp_addr === false) {
            Cumas_Exception::log_s(
                $logFacility,
                __FILE__,
                "Invalid Format ccAddress"
            );
            /* NOTE: 不正なメールアドレスを保存てない */
        } else {
            $all_cc[] = $tmp_addr;
        }
    }

    /* DBに保存するため、調整 */
    $mailData['toAddress'] = implode(DELIMIRER_MAIL, $all_to);;
    $mailData['ccAddress'] = implode(DELIMIRER_MAIL, $all_cc);

    /* 添付ファイルの取得 */
    $mparser->getAttachments();

    /* 添付ファイルを取得 */
    $mailData['attachments'] = $mparser->attachment_streams;

    /* メールデータの整形 */
    $addr = mailparse_rfc822_parse_addresses($from);
    $fromaddress = filter_var($addr[0]['address'], FILTER_VALIDATE_EMAIL);
    if ($fromaddress === false) {
        $mailData['fromAddress'] = $from;
        Cumas_Exception::log_s(
            $logFacility,
            __FILE__,
            "Invalid Format fromAddress"
        );
    } else {
        $mailData['fromAddress'] = $fromaddress;
    }


    if ($references !== false) {
        $tmpreferences = preg_split("/\s+/", $references, null, PREG_SPLIT_NO_EMPTY);
        $mailData['references']  = trim(array_pop($tmpreferences), "<>");
    } else {
        $mailData['references']  = "";
    }

    $mailData['messageId'] = trim($message_id, " <>");
    $mailData['date']      = date("Y-m-j H:i:s", strtotime($date)) ?: null;

    /* *note* mb_decode_mimeheaderがUTF-8 <-> ISO-2022-JPをうまく出来ない */
    $defEnc = mb_internal_encoding();
    mb_internal_encoding('EUC-JP');
    $mailData['subject'] = mb_convert_encoding(
        mb_decode_mimeheader($subject),
        'UTF-8',
        'ISO-2022-JP,UTF-8,EUC-JP,SJIS'
    );
    mb_internal_encoding($defEnc);

    /* 文字コードの順番はこれじゃないとだめ */
    $mailData['body'] = mb_convert_encoding(
        $body,
        'UTF-8',
        'ISO-2022-JP,UTF-8,EUC-JP,SJIS'
    );

    return $mailData;
}

/**
 *DB操作
 */

//引数からカテゴリIDを取得
function getCategoryIdOfNewJob($pdo, $ca_ident)
{
    $sql = "SELECT ca_id from category_tab where ca_ident = ?";    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$ca_ident]);

    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    return $result['ca_id'];
}

//mail_tabに登録
function insertMailTab($pdo, $data)
{
    $sql = ("INSERT INTO mail_tab"
         .  " (ma_message_id, ma_reference_id, ma_date, "
         .  " ma_from_addr,ma_subject, ma_to_addr, ma_cc_addr) "
         .  " VALUES"
         .  " (?,?,?,?,?,?,?) "
         .  " RETURNING ma_id");

    $stmt = $pdo->prepare($sql);
    $stmt->execute(array(
        $data['messageId'],
        $data['references'],
        $data['date'],
        $data['fromAddress'],
        $data['subject'] ?: 'no subject',
        $data["toAddress"],
        $data["ccAddress"]
    ));

    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['ma_id'];
}

//contact_tabに登録
function insertContactTab($pdo, $us_id, $ma_id, $ca_id)
{
    $nowtime = date("Y-m-j H:i:s", time());

    $sql = "INSERT INTO contact_tab"
         . " (co_us_id,co_inquiry,co_lastupdate,co_ma_id,ca_id)"
         . " VALUES"
         . " (?,?,?,?,?)"
         . " RETURNING co_id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(array(
                        $us_id,
                        $nowtime,
                        $nowtime,
                        $ma_id,
                        $ca_id,
                    ));

    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $co_id = $result['co_id'];

    $sql = "UPDATE contact_tab SET co_parent = $co_id WHERE co_id = $co_id";
    $pdo->query($sql);
    return $co_id;
}

//contact_mail_tabに登録
function insertContactMailTab($pdo, $co_id, $ma_id)
{
    $sql = "INSERT INTO contact_mail_tab "
         . "(co_id,ma_id) VALUES (?,?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array($co_id, $ma_id));
}

//contact_tabのco_us_idを更新
function updateCoUsId($pdo, $co_id, $us_id)
{
    $sql = "UPDATE contact_tab SET co_us_id = $us_id WHERE co_id = $co_id";
    $stmt = $pdo->query($sql);
}

//新規ジョブか既存ジョブかの判定
function searchJobIdByMsgId($pdo, $config, $msgId, $ca_id)
{
    $sql_incomplete = implode(
        " or ",
        array_map(
            function ($s) {
                return "co_status = $s";
            },
            $config->incomplete
        )
    );

    $sql = "SELECT contact_tab.co_id, co_us_id"
         . " FROM contact_tab"
         . " JOIN contact_mail_tab ON contact_tab.co_id = contact_mail_tab.co_id"
         . " JOIN mail_tab ON mail_tab.ma_id= contact_mail_tab.ma_id"
         . " WHERE ma_message_id = ?"
         . " AND (ca_id = ?)"
         . " AND ({$sql_incomplete})";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$msgId, $ca_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result === false) {
        return null;
    }
    return $result;
}

//担当者の確認
function searchUsIdByFromAddress($pdo, $fromaddr)
{
    $sql = "SELECT us_id FROM user_tab WHERE us_mail = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array($fromaddr));
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result === false) {
        return null;
    }
    return $result['us_id'];
}
/**
 *メールデータ登録先振り分け
 */

function isNewJob($pdo, $config, $data, $ca_id)
{
    $getData = array(
                    'co_id' => "",
                    'us_id' => null,
                    'flag'  => 0);       // 0->既存ジョブ(担当者あり)
                                         // 1->既存ジョブ(担当者無し)
                                         // 2->新規ジョブ

    //references有無確認
    if (empty($data['references'])) {
        $getData['us_id'] = searchUsIdByFromAddress($pdo, $data['fromAddress']);
        $getData['flag'] = 2;
        return $getData;
    }

    //referencesが存在する場合
    $result = searchJobIdByMsgId($pdo, $config, $data['references'], $ca_id);

    //co_idがない（元ジョブがない）
    if ($result === null) {
        $getData['us_id'] = searchUsIdByFromAddress($pdo, $data['fromAddress']);
        $getData['flag'] = 2;
        return $getData;
    }
    $getData['co_id'] = $result['co_id'];

    //co_us_idがない（担当者が未定）
    if ($result['co_us_id'] === null) {
        $getData['us_id'] = searchUsIdByFromAddress($pdo, $data['fromAddress']);

        //us_idがある(登録されているメールアドレス)
        if ($getData['us_id'] !== null) {
            $getData['flag'] = 1;
        }
    }
    return $getData;
}

/**
 * 添付ファイルを登録する
 */
function insertAttachTab($pdo, $attachments, $ma_id)
{
    global $logFacility;
    global $config;

    $data_attachments = array();
    $unknown_fn_count = 0;
    foreach($attachments as $attachment) {
 
        /*
         *  添付ファイル名を取得できず場合
         *「Attachment-ma_id-n.dat」とする
         */
        if ($attachment->filename === NULL) {
            $unknown_fn_count++;
            $filename = sprintf($config->unknownattachfilename,
                        $ma_id, $unknown_fn_count);
            Cumas_Exception::log_s(
                $logFacility,
                __FILE__,
                "Cannot get attachment file.Set default filename $filename."
            );

        /* ファイル名のMIMEデーコードに失敗した場合 */
        } else if ($attachment->filename === FALSE) {
            Cumas_Exception::log_s(
                $logFacility,
                __FILE__,
                "Cannot get mime decoding attachement filename.Rename to $filename."
            );
            $unknown_fn_count++;
            $filename = sprintf($config->unknownattachfilename,
                        $ma_id, $unknown_fn_count);

        } else {
            $filename = $attachment->filename;
        }

        $sql = ("INSERT INTO attach_tab"
             .  " (at_mailid,at_filename,at_mimetypes) "
             .  " VALUES"
             .  " (?,?,?) "
             .  " RETURNING at_id");

        $stmt = $pdo->prepare($sql);
        $stmt->execute(array(
            $ma_id,
            $filename,
            $attachment->content_type
        ));

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        /* データの保存 */
        $tmp_attachment["at_id"] = $result['at_id'];
        $tmp_attachment["stream"] = $attachment;

        $data_attachments[] = $tmp_attachment; 
    }

    return $data_attachments;
}

/**
 * 添付ファイルパスを更新する
 */
function updateAtachTab($pdo, $at_id, $at_filepath)
{
    $sql = "UPDATE attach_tab SET at_filepath = ? WHERE at_id = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(array(
        $at_filepath,
        $at_id
    ));
}

/*
 * main処理
 */

//引数のチェック
if ($argc <= 1) {
    // 引数の数が0個であるとき、メールを登録せずに終了
    Cumas_Exception::log_s(
        $logFacility,
        __FILE__,
        "Category identification name must be specified as an argument."
    );
    exit(1);
}

/* 標準入力の取得 */
$stream = fopen("php://stdin", "r");
try {
    $mparser = new MailParser();
    $mparser->setStream($stream);
    $mailData = getMailData($mparser);
} catch (CumasMailParseException $e) {
    fclose($stream);
    $mailData = array(
                'to'          => "",
                'cc'          => "",
                'fromAddress' => "",
                'references'  => "",
                'messageId'   => "",
                'date'        => null,
                'subject'     => "no subject",
                'body'        => $mparser->raw_data,
		'attachments' => array(),
		'encoding'    => ""
    );
    Cumas_Exception::log_s(
        $logFacility,
        __FILE__,
        "Invalid Format Mail Data: " . $e->getMessage()
    );
}

//DB登録
try {
    //DB接続
    $dsn = "pgsql:host=$config->dbserver;"
         . "port=$config->dbport;dbname=$config->dbname";

    $pdo = new PDO($dsn, $config->dbuser, $config->dbpasswd);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    //トランザクション開始
    $pdo->beginTransaction();
    $stmt = $pdo->query("LOCK TABLE contact_tab,mail_tab,contact_mail_tab,category_tab IN ACCESS EXCLUSIVE MODE");

    // 引数の値のチェック
    $ca_id_ofNewJob = getCategoryIdOfNewJob($pdo, $argv[1]);
    if (empty($ca_id_ofNewJob)) {
        // 引数で指定したカテゴリ識別名がDBに存在しない
        throw new CuMAS_Exception("No such category identification name ("
                                  . "$argv[1])."
                                 );
    }

    //mail_tabに登録
    $ma_id = insertMailTab($pdo, $mailData);

    //各処理の決定
    $regiData = isNewJob($pdo, $config, $mailData, $ca_id_ofNewJob);

    //新規ジョブ
    if ($regiData['flag'] === 2) {
        $regiData['co_id'] = insertContactTab(
            $pdo, $regiData['us_id'], $ma_id, $ca_id_ofNewJob);
    }

    //既存ジョブ（担当者有り）
    if ($regiData['flag'] === 1) {
        updateCoUsId($pdo, $regiData['co_id'], $regiData['us_id']);
    }
    insertContactMailTab($pdo, $regiData['co_id'], $ma_id);

    /* 添付ファイルテーブルに登録 */
    $dataAttachments = insertAttachTab($pdo, $mailData["attachments"], $ma_id);

    //本文をファイルに保存
    $mail_dir = sprintf("%02d", $ma_id % 100);
    $dirname = "{$config->mailsavedir}/{$mail_dir}";

    if (!file_exists($dirname)) {
        $result = mkdir($dirname, 0700);
        if ($result === false) {
            throw new CuMAS_Exception(
                "Failed to create mail directory ($dirname)");
        }
    }

    /* 添付ファイル保存のディレクトリ */
    $attachment_dir = "{$config->mailsavedir}/{$mail_dir}/" . DIR_ATTACHMENT;

    if (!file_exists($attachment_dir)) {

        /* 添付ファイル保存のディレクトリの作成 */
        $result = mkdir($attachment_dir, 0700, TRUE);
        if ($result === false) {
            throw new CuMAS_Exception(
                "Failed to create attachment directory ($attachment_dir)");
        }
    }

    /* 本文をファイルに保存 */
    $fp = fopen("{$dirname}/{$ma_id}", "w");
    if ($fp === false) {
        throw new CuMAS_Exception("Failed to open file ({$dirname}/{$ma_id})");
    }
    $result = fwrite($fp, $mailData['body']);
    fclose($fp);

    if ($result === false) {
        throw new CuMAS_Exception(
            "Failed to write mail text ({$dirname}/{$ma_id})");
    }

    $ret = chmod("{$dirname}/{$ma_id}", 0400);
    if ($ret === false) {
        throw new CuMAS_Exception(
            "Failed to chmod mailfile ({$dirname}/{$ma_id})");
    }

    /* 添付ファイルを保存 */
    foreach($dataAttachments as $attachment) {

        /* 添付ファイルパスを作成 */
        $at_filepath = $attachment_dir . "/" . $attachment["at_id"];

	$fp = fopen($at_filepath, 'w');
        if ($fp === false) {
	    throw new CuMAS_Exception(
	        "Failed to attachmet file ($at_filepath)"
	    );
	}

        while($bytes = $attachment["stream"]->read()) {
            fwrite($fp, $bytes);
	    if ($result === false) {
                fclose($fp);
                throw new CuMAS_Exception(
                    "Failed to write attachment file ($at_filepath)");
            }
        }

        fclose($fp);

        /* atach_tabのat_filenameを更新 */
        updateAtachTab($pdo, $attachment["at_id"], $at_filepath);

	$ret = chmod($at_filepath, 0400);
        if ($ret === false) {
            throw new CuMAS_Exception(
                "Failed to chmod mail attachfile ($at_filepath");
        }
    }

    $pdo->commit();

} catch (PDOException $e) {
    empty($db) ?: $db->rollBack();
    $ce = new CuMAS_Exception($e->getMessage());
    $ce->log($logFacility, __FILE__);
    exit(1);
} catch (CuMAS_Exception $ce) {
    empty($db) ?: $db->rollBack();
    $ce->log($logFacility, __FILE__);
    exit(1);
}

exit(0);
