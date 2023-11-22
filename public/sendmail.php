<?php

include_once '../lib/cumas_common.php';
include_once '../lib/libutil';

class SendMailPDO extends CuMAS_PDO
{
    public function getUsMail($login_us_id)
    {
        $sql = "SELECT us_mail FROM user_tab WHERE us_id = ?";
        $stmt = $this->_pdo->prepare($sql);
        $stmt->execute(array($login_us_id));
        return $stmt->fetchColumn();
    }

    public function selectMailTab($ma_id)
    {
        $sql = "SELECT * FROM mail_tab WHERE ma_id = ?";

        $stmt = $this->_pdo->prepare($sql);
        $stmt->execute(array($ma_id));

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result;
    }
}

//ローカルチェック関数
class checkPost
{
    function __construct($data)
    {
        $this->subject = $data['subject'];
        $this->mailTo  = $data['mailTo'];
        $this->mailCc  = $data['mailCc'];
        $this->body    = $data['body'];
    }

    public function checkSubject()
    {
        if (strlen(trim($this->subject)) === 0) {
            throw new CuMAS_Exception("件名が入力されていません。");
        }
        return $this;
    }

    public function checkMailTo()
    {
        if (strlen(trim($this->mailTo)) === 0) {
            throw new CuMAS_Exception("宛先が入力されていません。");
        }

        /* 複数宛先があれば */
        $arr_mail = mailparse_rfc822_parse_addresses($this->mailTo);
        foreach ($arr_mail as $mail) {
            $tmp_addr = filter_var($mail['address'], FILTER_VALIDATE_EMAIL);
            if (empty($mail['address'])) {
                throw new CuMAS_Exception(
                    sprintf("宛先が不正です。(%s)", $this->mailTo));
            }
            if ($tmp_addr === false) {
                throw new CuMAS_Exception(
                    sprintf("宛先が不正です。(%s)", $mail['address']));
            }
        }
        return $this;
    }

    public function checkCc()
    {
        if (strlen(trim($this->mailCc)) > 0) {

            /* 複数Ccがあれば */
            $arr_mail = mailparse_rfc822_parse_addresses($this->mailCc);
            foreach ($arr_mail as $mail) {
                $tmp_addr = filter_var($mail['address'], FILTER_VALIDATE_EMAIL);
                if (empty($mail['address'])) {
                    throw new CuMAS_Exception(
                        sprintf("Ccが不正です。(%s)", $this->mailTo));
                }
                if ($tmp_addr === false) {
                    throw new CuMAS_Exception(
                        sprintf("Ccが不正です。(%s)", $mail['address']));
                }
            }
        }
        return $this;
    }

    public function checkBody()
    {
        return $this;
    }
}

/**
 *main処理
 */
// メッセージがあれば
$view->message = $session->cut('message');

/* POSTの取得 */
$formList = [
    "sendmail"     => FILTER_DEFAULT,
    "return"       => FILTER_DEFAULT,
    "selectedMail" => FILTER_VALIDATE_INT,
    "subject"      => FILTER_DEFAULT,
    "mailTo"       => FILTER_DEFAULT,
    "mailCc"       => FILTER_DEFAULT,
    "body"         => FILTER_DEFAULT,
];

$postData = filter_input_array(INPUT_POST, $formList);

/* 不正のma_idの場合 */
if (!$postData["selectedMail"]) {
    $session->set('message', sprintf("アクセスの方法が不正です。"));
    header('location: contact_detail.php');
    exit;
}

try {
    $db = SendMailPDO::getInstance($config);

    /* 登録者のアドレス取得 */
    $s = $session->getLoginUserData();

    $us_mail = $db->getUsMail($s['us_id']);
    if ($us_mail === false) {
        $session->logout();
        exit;
    }

    $ma_id = $postData["selectedMail"];

    /* ma_idによってmail_tabの情報を取得 */
    $mail_data = $db->selectMailTab($ma_id);

    /* 本文 */
    $mailFile = sprintf("%s/%02d/%d", $config->mailsavedir,
                         $ma_id % 100, $ma_id);

    $mailBody = create_body_sendmail($mailFile,
                $mail_data["ma_from_addr"], $mail_data["ma_date"]);
    if ($mailBody === false) {
        CuMAS_Exception::log_s($logFacility, __FILE__,
                "Unable to load mail file '{$mailFile}'");
        $mailBody = "";
    }

    $view->selectedMail = $postData["selectedMail"];
    $view->ma_id = $ma_id;

    /* 差出人をセット */
    $view->fromaddr = $us_mail;

    /* To */
    $mailToStr = $mail_data["ma_from_addr"];
    if (!empty($mail_data["ma_to_addr"])) {
        $mailToStr .= "," . $mail_data["ma_to_addr"];
    }

    $arr_to_new = array();
    $arr_to = explode(",", $mailToStr);
    foreach ($arr_to as $mail) {
        $arr_parts = mailparse_rfc822_parse_addresses($mail);
        if ($arr_parts[0]["address"] !== $us_mail) {
            $arr_to_new[] = $mail;
        }
    }
    $view->mailTo = implode(",", $arr_to_new);

    /* Cc */
    $view->mailCc = "";

    if (!empty($mail_data["ma_cc_addr"])) {
        $arr_cc_new = array();
        $arr_cc_tmp = explode(",", $mail_data["ma_cc_addr"]);
        foreach ($arr_cc_tmp as $mail) {
            $arr_parts = mailparse_rfc822_parse_addresses($mail);
            if ($arr_parts[0]["address"] !== $us_mail) {
                $arr_cc_new[] = $mail;
            }
        }
        $view->mailCc = implode(",", $arr_cc_new);
    }

    /* 件名 */
    $view->subject = createSubjectSendmail($mail_data["ma_subject"]);

    /* 本文 */
    $view->body = $mailBody;

    /* メール送信ボタンが押された時 */
    if (isset($postData['sendmail'])) {
        $register = new checkPost($postData);
        $register->checkMailTo()
                 ->checkCc()
                 ->checkSubject()
                 ->checkBody();

        /* メール送信 */
        cumas_sendmail(
             $us_mail
            ,$postData["mailTo"]
            ,$postData["mailCc"]
            ,$postData["subject"]
            ,$postData["body"]
            ,$mail_data["ma_message_id"]
        );

        $session->set('message', sprintf("メールを送信しました。"));

        httpPost(
            "contact_detail.php",
            array("selectedMail" => $postData["selectedMail"])
        ); 

    /* キャンセルボタンが押された時 */
    } else if (isset($postData["return"])) {
        httpPost("contact_detail.php",
            array("selectedMail" => $postData["selectedMail"])
        );
    }
} catch (PDOEXCEPTION $e) {
    empty($db) ?: $db->rollBack();
    Cumas_Exception::log_s($logFacility, __FILE__, $e->getMessage());
    Cumas_Exception::printErr();
    exit;
} catch (CuMAS_Exception $e) {
    $e->log($logFacility, __FILE__);
    $view->message = $e->getMessage();
    $view->subject = $postData["subject"];
    $view->mailTo  = $postData["mailTo"];
    $view->mailCc  = $postData["mailCc"];
    $view->body    = $postData["body"];
}

try {
    $view->display();
} catch (CuMAS_Exception $e) {
    $e->log($logFacility, __FILE__);
    $e->printErr();
    exit;
}

/* End of file sendmail.php */
