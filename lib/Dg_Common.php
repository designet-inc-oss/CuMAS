<?php
/**
 * 共通関数ライブラリ
 *
 */

/**
 *
 * 設定ファイルを読み込み、設定情報を格納
 *
 * 読み込む設定ファイルの形式は以下とします。<br>
 *  ・(項目名)=(設定値)の形式で1行に1項目定義。<br>
 *  ・#はコメント行とする。<br>
 *  ・空行は無視される。
 *
 * $conf_keyは以下の形式の連想配列とし関数呼び出し側で定義します。<br>
 *  ・$conf_key[設定ファイルの項目名] = 設定値のチェック関数
 *
 * $conf_defは以下の形式の連想配列とし関数呼び出し側で定義します。<br>
 *  ・$conf_def[設定ファイルの項目名] = デフォルト値
 * 
 * @param string $filename    設定ファイル名
 * @param array  $conf_keys   設定ファイルの項目名をキー、設定値のチェック関数を値とした連想配列
 * @param array  $conf_def    設定ファイルの項目名をキー、デフォルト値を値とした連想配列
 *
 * @return mixed 正常に読み込めた場合は設定ファイルの情報を格納した配列、ファイルに読込権がないまたは設定ファイルの形式エラーの場合はFALSEを返します。
 *
 */
function DgCommon_read_conf($filename, $conf_keys, $conf_def)
{

    global $dg_err_msg;
    global $dg_log_msg;

    /* ファイルの読み込み権チェック */
    if (DgCommon_is_readable_file($filename) === FALSE) {
        $dg_err_msg = htmlspecialchars($dg_err_msg);
        return FALSE;
    }

    /* ファイルをオープン */
    $fp = fopen($filename, "r");
    if ($fp === FALSE) {
        $dg_err_msg = "ファイルがオープンできません。(" .
                      htmlspecialchars($filename) . ")";
        $dg_log_msg = "Cannot open file.(" . $filename . ")";
        return FALSE;
    }

    /* 行の初期値 */
    $line = 0;
    $err = "";
    $log = "";

    /* ファイル読み込み */
    while (feof($fp) === FALSE) {

        /* 一行分をバッファに格納 */
        $buf = fgets($fp);
        if ($buf === FALSE) {
            break;
        }

        /* 行末の空白と改行を削除 */
        $buf = rtrim($buf);

        $line++;

        /* 行の頭が#のコメント行であれば無視 */
        $c = substr($buf, 0, 1);
        if ($c == "#" || $c == "") {
            continue;
        }

        /* 行の始めの区切り文字で分割 */
        //イコールの周りの空白を許可
        //@mamori 15/10/21
        //$data = explode("=", $buf, 2);
        //
        /* 値がnull,パラメータの先頭が空白であれば、エラー */
        //if (($data[0] == "") || ($data[1] == "") ||
        //                         substr("$data[1]", 0, 1) == " ") {
        $data = array_map("trim", explode("=", $buf, 2));
        if (($data[0] == "") || ($data[1] == "")) {
            $err .= $line . "行目の形式が不正です。(" .
                    htmlspecialchars($filename) . ")<br>";
            $log .= "Invalid line(line: " . $line . "). (" .  $filename . ") ";
            continue;
        }

        /* 項目名を小文字化して格納 */
        $key = strtolower($data[0]);

        /* 定義された項目かのチェック */
        //if(is_null($conf_keys[$key]) === TRUE) {
        if(isset($conf_keys[$key]) === false) {
            $err .= $line . "行目『" . htmlspecialchars($key) .
                    "』は定義されていない項目です。(" .
                    htmlspecialchars($filename) .  ")<br>";
            $log .= $key . " (line: " . $line .  ") is undefined item.(" .
                    $filename . ") ";
            continue;
        }

        /* 定義した検査項目によるチェック */
        if ($conf_keys[$key]($data[1]) === FALSE) {
            $err .= $line . "行目『" . htmlspecialchars($key) .
                    "』の値が不正です。(" . htmlspecialchars($filename) .
                    ")<br>";
            $log .= $key . " (line: " . $line .
                    ") is invalid.(" .  $filename . ") ";
            continue;
        }

        /* 重複チェック */
        if (isset($conf[$key]) === TRUE) {
            $err .= $line . "行目『" . htmlspecialchars($key) .
                    "』が重複しています。(" . htmlspecialchars($filename) .
                    ")<br>";
            $log .= $key . " (line: " . $line .  ") is duplicated.(" . 
                    $filename . ") ";
            continue;
        }

        /* 値を格納する */
        $conf[$key] = $data[1];

    }

    fclose($fp);

    /* 形式チェックエラー */
    if ($err != "") {
        $dg_err_msg = $err;
        $dg_log_msg = $log;
        return FALSE;
    }

    /* 項目名を取得 */
    $keys = array_keys($conf_keys);

    /* すべての項目に値がセットされているかの確認 */
    foreach ($keys as $key) {
        /* セットされておらず、デフォルト値が設定されている場合、それを代入 */
        if (!isset($conf[$key]) && isset($conf_def[$key])) {
            $conf[$key] = $conf_def[$key];
        } elseif (!isset($conf[$key]) === TRUE) {
            $err .= "項目『" . htmlspecialchars($key) .
                    "』が設定されていません。(" .
                    htmlspecialchars($filename) . ")<br>";
            $log .= $key . " must be set. (" .
                    $filename . ") ";
            continue;
        }
    }

    /* 必須チェックエラー */
    if ($err != "") {
        $dg_err_msg = $err;
        $dg_log_msg = $log;
        return FALSE;
    }

    return $conf;
}

/**
 * 
 * ファイルの読み込み権をチェックする
 *
 * @param string $filename チェック対象ファイル
 *
 * @return bool ファイルに読み込み権がある場合はTRUE、それ以外はFALSEを返します。
 *
 */
function DgCommon_is_readable_file($filename)
{
    global $dg_err_msg;
    global $dg_log_msg;

    /* STAT情報のキャッシュクリア */
    clearstatcache();

    /* 存在のチェック */
    if (file_exists($filename) === FALSE) {
        $dg_err_msg = "ファイルが存在しません。(" . $filename . ")";
        $dg_log_msg = "File does not exist.(" . $filename . ")";
        return FALSE;
    }

    /* ディレクトリかのチェック */
    if (is_dir($filename) === TRUE) {
        $dg_err_msg = "同名のディレクトリが存在します。(" .  $filename . ")";
        $dg_log_msg = "Directory with the same name exists.(" . $filename . ")";
        return FALSE;
    }

    /*  ファイルの読み込み権チェック */
    if (is_readable($filename) === FALSE) {
        $dg_err_msg = "ファイルに読み込み権がありません。(" . $filename . ")";
        $dg_log_msg = "File is not readable.(" . $filename . ")";
        return FALSE;
    }

    return TRUE;
}

/**
 *
 * ファイルの書き込み権チェック
 *
 * @param string $filename チェック対象ファイル
 *
 * @return bool ファイルに書き込み権がある場合もしくは指定されたファイルは存在しないがディレクトリに書き込み権がある場合はTRUE、それ以外はFALSEを返します。
 *
 */
function DgCommon_is_writable_file($filename)
{
    global $dg_err_msg;
    global $dg_log_msg;

    /* STAT情報のキャッシュクリア */
    clearstatcache();

    /* 存在チェック */
    if (file_exists($filename) === FALSE) {
        if (is_writable(dirname($filename)) === FALSE) {
            $dg_err_msg = "ディレクトリに書き込み権がありません。(" .
                          $filename . ")";
            $dg_log_msg = "Directory buffering the file is not readable.(" .
                          $filename . ")";
            return FALSE;
        }
        return TRUE;
    }

    /*  ディレクトリチェック */
    if (is_dir($filename) === TRUE) {
        $dg_err_msg = "同名のディレクトリが存在します。(" . $filename . ")";
        $dg_log_msg = "Directory with the same name exists.(" . $filename . ")";
        return FALSE;
    }

    /*  ファイルの書込み権チェック */
    if (is_writable($filename) === FALSE) {
        $dg_err_msg = "ファイルに書き込み権がありません。(" . $filename . ")";
        $dg_log_msg = "File is not writable.(" . $filename .  ")";
        return FALSE;
    }
    return TRUE;
}

/**
 *
 * TRUEのみ返すダミー関数(設定ファイルのチェック関数)
 *
 * @return bool どんな場合でもTRUEを返します。
 *
 */
function DgCommon_check_none()
{
    return TRUE;
}

/**
 *
 * ポート番号チェック(設定ファイルのチェック関数)
 *
 * ポート番号の範囲が1以上65535以下であることをチェックします。
 *
 * @param string $port ポート番号
 *
 * @return bool ポート番号の形式が正しければTRUE、正しくなければFALSEを返します 。
 *
 */
function DgCommon_is_port($port)
{
    /* 半角数字のみ許可 */
    $num = "0123456789";
    if (strspn($port, $num) != strlen($port)) {
        return FALSE;
    }

    /* 1から最大ポート番号まで */
    if (($port < 1) || ($port > 65535)) {
        return FALSE;
    }
    return TRUE;
}

/**
 *
 * メールアドレス形式のチェック
 *
 * 指定されたチェックレベルに基づいてチェックを行います。<br>
 * $level = 1:<br>
 *  ・文字種が半角英小文字、半角数字、半角記号(-._@)であること<br>
 *  ・256文字以内であること<br>
 *
 * $level = 2:<br>
 *  ・@よりも前の文字種が半角英大小文字、半角数字、半角記号(!#$%&'*+-/=?^_{}~)であること<br>
 *  ・@よりも前が1文字以上のこと<br>
 *  ・@より後ろの文字種が半角英大小文字、半角数字、半角記号(-._)であること<br>
 *  ・@より後ろに.が1つ以上含まれていること<br>
 *  ・@より後ろに.が連続していないこと<br>
 *  ・@より後ろが3文字以上のこと<br>
 *  ・256文字以内であること<br>
 *
 * $level = 3:<br>
 *  ・@より前の文字種が半角英小文字、半角数字、半角記号(-._)であること<br>
 *  ・@よりも前が1文字以上のこと<br>
 *  ・@より後ろのの文字種が半角英小文字、半角数字、半角記号(-._)であること<br>
 *  ・@より後ろに.が1つ以上含まれていること<br>
 *  ・@より後ろに.が連続していないこと<br>
 *  ・@より後ろが3文字以上のこと<br>
 *  ・256文字以内であること
 *
 * @param string  $mail  メールアドレス
 * @param integer $level メールアドレスのチェックレベル(1 or 2 or 3)
 *
 * @return bool メールアドレスの形式が正しければTRUE、正しくなければFALSEを返します。
 *
 */
function DgCommon_is_mailaddr($mail, $level = 2)
{
    global $dg_err_msg;
    global $dg_log_msg;

    /* チェックレベルの形式チェック */
    if ($level != 1 && $level != 2 && $level != 3) {
        $dg_err_msg = "メールアドレスのチェックレベルの形式が不正です。(" .
                      $level . ")";
        $dg_log_msg = "Check level of mailaddr is invalid.(" . $level . ")";
        return FALSE;
    }

    /* メールアドレスの長さチェック */
    if (strlen($mail) > 256) {
        $dg_err_msg = "メールアドレスの形式が不正です。(" . $mail . ")";
        $dg_log_msg = "Form of mailaddr is invalid.(" . $mail . ")";
        return FALSE;
    }

    if ($level == 1) {
        /* レベル1 */
        /* メールアドレスの形式チェック */
        $num = "0123456789";
        $sl = "abcdefghijklmnopqrstuvwxyz";
        $sym = "-._@";
        $allow_letter = $num . $sl . $sym;
        if (strspn($mail, $allow_letter) != strlen($mail)) {
            $dg_err_msg = "メールアドレスの形式が不正です。(" . $mail . ")";
            $dg_log_msg = "Form of mailaddr is invalid.(" . $mail . ")";
            return FALSE;
        }
    } else {
        /* レベル2,3 */
        /* @で二つに区切れるかのチェック */
        $buf = explode('@', $mail, 2);
        if ($buf[0] == "" || $buf[1] == "") {
            $dg_err_msg = "メールアドレスの形式が不正です。(" . $mail . ")";
            $dg_log_msg = "Form of mailaddr is invalid.(" . $mail . ")";
            return FALSE;
        }

        /* @より前のチェック */
        /* 半角英小文字、数字、以下の記号のみ許可 */
        $num = "0123456789";
        $sl = "abcdefghijklmnopqrstuvwxyz";
        $ll = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
        $sym1 = "!#$%&'*+-/=?^_{}~.";
        $sym2 = "-._";

        /* 許可文字種選択 */
        if ($level == 2) {
            $front_letter = $num . $sl . $ll . $sym1;
            $back_letter = $num . $sl . $ll . $sym2;
        } else {
            $front_letter = $num . $sl . $sym2;
            $back_letter = $num . $sl . $sym2;
        }

        if (strspn($buf[0], $front_letter) != strlen($buf[0])) {
            $dg_err_msg = "メールアドレスの形式が不正です。(" . $mail . ")";
            $dg_log_msg = "Form of mailaddr is invalid.(" . $mail . ")";
            return FALSE;
        }

        /*  @より後ろのチェック */
        if (strlen($buf[1]) < 3) {
            $dg_err_msg = "メールアドレスの形式が不正です。(" . $mail . ")";
            $dg_log_msg = "Form of mailaddr is invalid.(" . $mail . ")";
            return FALSE;
        }

        /* ドットから始まればエラー */
        if (substr($buf[1], 0, 1) == ".") {
            $dg_err_msg = "メールアドレスの形式が不正です。(" . $mail . ")";
            $dg_log_msg = "Form of mailaddr is invalid.(" . $mail . ")";
            return FALSE;
        }

        /* 1個以上のドットが必須。 */
        if (strpos($buf[1], ".") === FALSE) {
            $dg_err_msg = "メールアドレスの形式が不正です。(" . $mail . ")";
            $dg_log_msg = "Form of mailaddr is invalid.(" . $mail . ")";
            return FALSE;
        }

        /* 2個以上のドットの連続は禁止。 */
        if (strpos($buf[1], "..") !== FALSE) {
            $dg_err_msg = "メールアドレスの形式が不正です。(" . $mail . ")";
            $dg_log_msg = "Form of mailaddr is invalid.(" . $mail . ")";
            return FALSE;
        }

        if (strspn($buf[1], $back_letter) != strlen($buf[1])) {
            $dg_err_msg = "メールアドレスの形式が不正です。(" . $mail . ")";
            $dg_log_msg = "Form of mailaddr is invalid.(" . $mail . ")";
            return FALSE;
        }

    }
    return TRUE;
}

/**
 *
 * 値が1か0のいずれかであることのチェック(設定ファイルのチェック関数)
 *
 * @param string $num チェックする値
 *
 * @return bool 形式が正しければTRUE、正しくなければFALSEを返します。
 *
 */
function DgCommon_is_bool($num)
{
    if ($num != "0" && $num != "1") {
        return FALSE;
    }
    return TRUE;
}

/**
 *
 * ログファシリティの形式チェック(設定ファイルのチェック関数)
 *
 * 引数に指定されたログファシリティが以下の文字列と一致するかをチェックします。<br>
 * ("auth", "authpriv", "cron", "daemon", "kern", "local0", "local1", "local2", "local3", "local4", "local5", "local6", "local7", "lpr", "mail", "news", "syslog", "user", "uucp")
 *
 * @param string $facility チェックするログファシリティ
 *
 * @return bool 引数で指定されたログファシリティの形式が正しければTRUE、正しくなければFALSEを返します。
 *
 */
function DgCommon_is_facility($facility)
{

    /* チェックするファシリティのリスト */
    $flist = array("auth", "authpriv", "cron", "daemon", "kern", "local0",
                   "local1", "local2", "local3", "local4", "local5", "local6",
                   "local7", "lpr", "mail", "news", "syslog", "user", "uucp");

    /* 引数とリストが一致したらTRUE */
    $ret = array_search($facility, $flist);
    if ($ret === FALSE) {
        return FALSE;
    }

    return TRUE;

}

/**
 *
 * Syslogファシリティの文字列からファシリティ値に変換(openlog用)
 *
 * 変換前ファシリティ文字列 => 変換後ファシリティ値<br>
 * "auth"     => LOG_AUTH<br>
 * "authpriv" => LOG_AUTHPRIV<br>
 * "cron"     => LOG_CRON<br>
 * "daemon"   => LOG_DAEMON<br>
 * "kern"     => LOG_KERN<br>
 * "local0"   => LOG_LOCAL0<br>
 * "local1"   => LOG_LOCAL1<br>
 * "local2"   => LOG_LOCAL2<br>
 * "local3"   => LOG_LOCAL3<br>
 * "local4"   => LOG_LOCAL4<br>
 * "local5"   => LOG_LOCAL5<br>
 * "local6"   => LOG_LOCAL6<br>
 * "local7"   => LOG_LOCAL7<br>
 * "lpr"      => LOG_LPR<br>
 * "mail"     => LOG_MAIL<br>
 * "news"     => LOG_NEWS<br>
 * "syslog"   => LOG_SYSLOG<br>
 * "user"     => LOG_USER<br>
 * "uucp"     => LOG_UUCP
 *
 * @param string $facility Syslogファシリティの文字列
 *
 * @return integer ファシリティに対応したファシリティ値を返します。
 *
 */
function DgCommon_set_logfacility($facility)
{

    $fchlist = array(
                     "auth"     => LOG_AUTH,
                     "authpriv" => LOG_AUTHPRIV,
                     "cron"     => LOG_CRON,
                     "daemon"   => LOG_DAEMON,
                     "kern"     => LOG_KERN,
                     "local0"   => LOG_LOCAL0,
                     "local1"   => LOG_LOCAL1,
                     "local2"   => LOG_LOCAL2,
                     "local3"   => LOG_LOCAL3,
                     "local4"   => LOG_LOCAL4,
                     "local5"   => LOG_LOCAL5,
                     "local6"   => LOG_LOCAL6,
                     "local7"   => LOG_LOCAL7,
                     "lpr"      => LOG_LPR,
                     "mail"     => LOG_MAIL,
                     "news"     => LOG_NEWS,
                     "syslog"   => LOG_SYSLOG,
                     "user"     => LOG_USER,
                     "uucp"     => LOG_UUCP,
                    );

    return $fchlist{$facility};

}

/**
 *
 * IPアドレスの形式チェック(設定ファイルのチェック関数)
 *
 * @param string $ipaddr チェックするIPアドレス
 * @param bool   $ipv6   IPv6の形式チェックの時にTRUEを設定(デフォルト:FALSE)
 *
 * @return bool IPアドレスの形式が正しい場合にTRUE、正しくない場合はFALSEを返します。
 *
 */
function DgCommon_is_ipaddr($ipaddr, $ipv6 = FALSE)
{

    if ($ipv6 === FALSE) {
        /* IPv4の形式チェック */

        /* ドットの数のチェック */
        $ip = explode(".", $ipaddr);
        $max = count($ip);
        if ($max != 4) {
            return FALSE;
        }

        for ($i = 0; $i < $max; $i++) {
            /* 空かどうかのチェック */
            if ($ip[$i] === "") {
                return FALSE;
            }

            /* 数字しか含まれないかのチェック */
            $num = "0123456789";
            if (strspn($ip[$i], $num) != strlen($ip[$i])) {
                return FALSE;
            }

            /* 0以上255以下かどうかのチェック */
            if ($ip[$i] < 0 || $ip[$i] > 255) {
                return FALSE;
            }
        }

    } else {

        # コロンの数チェック
        $ip = explode(":", $ipaddr);
        $max = count($ip);
        if ($max < 3 || $max > 8 ) {
            return FALSE;
        }

        # IPv6の形式チェック
        $ret = @inet_pton($ipaddr);
        if ($ret === FALSE) {
            return FALSE;
        }

    }

    return TRUE;

}

/**
 *
 * バイトをメガバイトに変換
 *
 * @param integer $value  変換する数値(バイト)
 *
 * @return integer $value メガバイトに変換した整数値を返します。
 *
 */
function DgCommon_to_MB($value)
{
    if ($value <= 0) {
        return 0;
    }

    /*  1MB より小さい */
    if ($value < 1048576) {
        return 1;
    }

    $tmp = $value / 1048576;
    return (int) (round($tmp));
}


/**
 *
 * パスワードをcryptで暗号化する
 *
 * @param string $passwd 暗号化するパスワード
 *
 * @return string 暗号化されたパスワードを返します。
 *
 */
function DgCommon_crypt_passwd($passwd)
{
    $salts = array("A", "B", "C", "D", "E", "F", "G", "H", "I", "J", "K", "L",
                   "M", "N", "O", "P", "Q", "R", "S", "T", "U", "V", "W", "X",
                   "Y", "Z", "a", "b", "c", "d", "e", "f", "g", "h", "i", "j",
                   "k", "l", "m", "n", "o", "p", "q", "r", "s", "t", "u", "v",
                   "w", "x", "y", "z", "0", "1", "2", "3", "4", "5", "6", "7",
                   "8", "9", ".", "/" );

    $rand_key = array_rand($salts, 2);

    $salt = $salts[$rand_key[0]] . $salts[$rand_key[1]];

    $crypt_passwd = crypt($passwd, $salt);

    return $crypt_passwd;
}

?>
