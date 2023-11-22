<?php
/**
 * ���̴ؿ��饤�֥��
 *
 */

/**
 *
 * ����ե�������ɤ߹��ߡ����������Ǽ
 *
 * �ɤ߹�������ե�����η����ϰʲ��Ȥ��ޤ���<br>
 *  ��(����̾)=(������)�η�����1�Ԥ�1���������<br>
 *  ��#�ϥ����ȹԤȤ��롣<br>
 *  �����Ԥ�̵�뤵��롣
 *
 * $conf_key�ϰʲ��η�����Ϣ������Ȥ��ؿ��ƤӽФ�¦��������ޤ���<br>
 *  ��$conf_key[����ե�����ι���̾] = �����ͤΥ����å��ؿ�
 *
 * $conf_def�ϰʲ��η�����Ϣ������Ȥ��ؿ��ƤӽФ�¦��������ޤ���<br>
 *  ��$conf_def[����ե�����ι���̾] = �ǥե������
 * 
 * @param string $filename    ����ե�����̾
 * @param array  $conf_keys   ����ե�����ι���̾�򥭡��������ͤΥ����å��ؿ����ͤȤ���Ϣ������
 * @param array  $conf_def    ����ե�����ι���̾�򥭡����ǥե�����ͤ��ͤȤ���Ϣ������
 *
 * @return mixed ������ɤ߹��᤿��������ե�����ξ�����Ǽ�������󡢥ե�������ɹ������ʤ��ޤ�������ե�����η������顼�ξ���FALSE���֤��ޤ���
 *
 */
function DgCommon_read_conf($filename, $conf_keys, $conf_def)
{

    global $dg_err_msg;
    global $dg_log_msg;

    /* �ե�������ɤ߹��߸������å� */
    if (DgCommon_is_readable_file($filename) === FALSE) {
        $dg_err_msg = htmlspecialchars($dg_err_msg);
        return FALSE;
    }

    /* �ե�����򥪡��ץ� */
    $fp = fopen($filename, "r");
    if ($fp === FALSE) {
        $dg_err_msg = "�ե����뤬�����ץ�Ǥ��ޤ���(" .
                      htmlspecialchars($filename) . ")";
        $dg_log_msg = "Cannot open file.(" . $filename . ")";
        return FALSE;
    }

    /* �Ԥν���� */
    $line = 0;
    $err = "";
    $log = "";

    /* �ե������ɤ߹��� */
    while (feof($fp) === FALSE) {

        /* ���ʬ��Хåե��˳�Ǽ */
        $buf = fgets($fp);
        if ($buf === FALSE) {
            break;
        }

        /* �����ζ���Ȳ��Ԥ��� */
        $buf = rtrim($buf);

        $line++;

        /* �Ԥ�Ƭ��#�Υ����ȹԤǤ����̵�� */
        $c = substr($buf, 0, 1);
        if ($c == "#" || $c == "") {
            continue;
        }

        /* �ԤλϤ�ζ��ڤ�ʸ����ʬ�� */
        //��������μ���ζ�������
        //@mamori 15/10/21
        //$data = explode("=", $buf, 2);
        //
        /* �ͤ�null,�ѥ�᡼������Ƭ������Ǥ���С����顼 */
        //if (($data[0] == "") || ($data[1] == "") ||
        //                         substr("$data[1]", 0, 1) == " ") {
        $data = array_map("trim", explode("=", $buf, 2));
        if (($data[0] == "") || ($data[1] == "")) {
            $err .= $line . "���ܤη����������Ǥ���(" .
                    htmlspecialchars($filename) . ")<br>";
            $log .= "Invalid line(line: " . $line . "). (" .  $filename . ") ";
            continue;
        }

        /* ����̾��ʸ�������Ƴ�Ǽ */
        $key = strtolower($data[0]);

        /* ������줿���ܤ��Υ����å� */
        //if(is_null($conf_keys[$key]) === TRUE) {
        if(isset($conf_keys[$key]) === false) {
            $err .= $line . "���ܡ�" . htmlspecialchars($key) .
                    "�٤��������Ƥ��ʤ����ܤǤ���(" .
                    htmlspecialchars($filename) .  ")<br>";
            $log .= $key . " (line: " . $line .  ") is undefined item.(" .
                    $filename . ") ";
            continue;
        }

        /* ��������������ܤˤ������å� */
        if ($conf_keys[$key]($data[1]) === FALSE) {
            $err .= $line . "���ܡ�" . htmlspecialchars($key) .
                    "�٤��ͤ������Ǥ���(" . htmlspecialchars($filename) .
                    ")<br>";
            $log .= $key . " (line: " . $line .
                    ") is invalid.(" .  $filename . ") ";
            continue;
        }

        /* ��ʣ�����å� */
        if (isset($conf[$key]) === TRUE) {
            $err .= $line . "���ܡ�" . htmlspecialchars($key) .
                    "�٤���ʣ���Ƥ��ޤ���(" . htmlspecialchars($filename) .
                    ")<br>";
            $log .= $key . " (line: " . $line .  ") is duplicated.(" . 
                    $filename . ") ";
            continue;
        }

        /* �ͤ��Ǽ���� */
        $conf[$key] = $data[1];

    }

    fclose($fp);

    /* ���������å����顼 */
    if ($err != "") {
        $dg_err_msg = $err;
        $dg_log_msg = $log;
        return FALSE;
    }

    /* ����̾����� */
    $keys = array_keys($conf_keys);

    /* ���٤Ƥι��ܤ��ͤ����åȤ���Ƥ��뤫�γ�ǧ */
    foreach ($keys as $key) {
        /* ���åȤ���Ƥ��餺���ǥե�����ͤ����ꤵ��Ƥ����硢��������� */
        if (!isset($conf[$key]) && isset($conf_def[$key])) {
            $conf[$key] = $conf_def[$key];
        } elseif (!isset($conf[$key]) === TRUE) {
            $err .= "���ܡ�" . htmlspecialchars($key) .
                    "�٤����ꤵ��Ƥ��ޤ���(" .
                    htmlspecialchars($filename) . ")<br>";
            $log .= $key . " must be set. (" .
                    $filename . ") ";
            continue;
        }
    }

    /* ɬ�ܥ����å����顼 */
    if ($err != "") {
        $dg_err_msg = $err;
        $dg_log_msg = $log;
        return FALSE;
    }

    return $conf;
}

/**
 * 
 * �ե�������ɤ߹��߸�������å�����
 *
 * @param string $filename �����å��оݥե�����
 *
 * @return bool �ե�������ɤ߹��߸����������TRUE������ʳ���FALSE���֤��ޤ���
 *
 */
function DgCommon_is_readable_file($filename)
{
    global $dg_err_msg;
    global $dg_log_msg;

    /* STAT����Υ���å��奯�ꥢ */
    clearstatcache();

    /* ¸�ߤΥ����å� */
    if (file_exists($filename) === FALSE) {
        $dg_err_msg = "�ե����뤬¸�ߤ��ޤ���(" . $filename . ")";
        $dg_log_msg = "File does not exist.(" . $filename . ")";
        return FALSE;
    }

    /* �ǥ��쥯�ȥ꤫�Υ����å� */
    if (is_dir($filename) === TRUE) {
        $dg_err_msg = "Ʊ̾�Υǥ��쥯�ȥ꤬¸�ߤ��ޤ���(" .  $filename . ")";
        $dg_log_msg = "Directory with the same name exists.(" . $filename . ")";
        return FALSE;
    }

    /*  �ե�������ɤ߹��߸������å� */
    if (is_readable($filename) === FALSE) {
        $dg_err_msg = "�ե�������ɤ߹��߸�������ޤ���(" . $filename . ")";
        $dg_log_msg = "File is not readable.(" . $filename . ")";
        return FALSE;
    }

    return TRUE;
}

/**
 *
 * �ե�����ν񤭹��߸������å�
 *
 * @param string $filename �����å��оݥե�����
 *
 * @return bool �ե�����˽񤭹��߸���������⤷���ϻ��ꤵ�줿�ե������¸�ߤ��ʤ����ǥ��쥯�ȥ�˽񤭹��߸����������TRUE������ʳ���FALSE���֤��ޤ���
 *
 */
function DgCommon_is_writable_file($filename)
{
    global $dg_err_msg;
    global $dg_log_msg;

    /* STAT����Υ���å��奯�ꥢ */
    clearstatcache();

    /* ¸�ߥ����å� */
    if (file_exists($filename) === FALSE) {
        if (is_writable(dirname($filename)) === FALSE) {
            $dg_err_msg = "�ǥ��쥯�ȥ�˽񤭹��߸�������ޤ���(" .
                          $filename . ")";
            $dg_log_msg = "Directory buffering the file is not readable.(" .
                          $filename . ")";
            return FALSE;
        }
        return TRUE;
    }

    /*  �ǥ��쥯�ȥ�����å� */
    if (is_dir($filename) === TRUE) {
        $dg_err_msg = "Ʊ̾�Υǥ��쥯�ȥ꤬¸�ߤ��ޤ���(" . $filename . ")";
        $dg_log_msg = "Directory with the same name exists.(" . $filename . ")";
        return FALSE;
    }

    /*  �ե�����ν���߸������å� */
    if (is_writable($filename) === FALSE) {
        $dg_err_msg = "�ե�����˽񤭹��߸�������ޤ���(" . $filename . ")";
        $dg_log_msg = "File is not writable.(" . $filename .  ")";
        return FALSE;
    }
    return TRUE;
}

/**
 *
 * TRUE�Τ��֤����ߡ��ؿ�(����ե�����Υ����å��ؿ�)
 *
 * @return bool �ɤ�ʾ��Ǥ�TRUE���֤��ޤ���
 *
 */
function DgCommon_check_none()
{
    return TRUE;
}

/**
 *
 * �ݡ����ֹ�����å�(����ե�����Υ����å��ؿ�)
 *
 * �ݡ����ֹ���ϰϤ�1�ʾ�65535�ʲ��Ǥ��뤳�Ȥ�����å����ޤ���
 *
 * @param string $port �ݡ����ֹ�
 *
 * @return bool �ݡ����ֹ�η��������������TRUE���������ʤ����FALSE���֤��ޤ� ��
 *
 */
function DgCommon_is_port($port)
{
    /* Ⱦ�ѿ����Τߵ��� */
    $num = "0123456789";
    if (strspn($port, $num) != strlen($port)) {
        return FALSE;
    }

    /* 1�������ݡ����ֹ�ޤ� */
    if (($port < 1) || ($port > 65535)) {
        return FALSE;
    }
    return TRUE;
}

/**
 *
 * �᡼�륢�ɥ쥹�����Υ����å�
 *
 * ���ꤵ�줿�����å���٥�˴�Ť��ƥ����å���Ԥ��ޤ���<br>
 * $level = 1:<br>
 *  ��ʸ���郎Ⱦ�ѱѾ�ʸ����Ⱦ�ѿ�����Ⱦ�ѵ���(-._@)�Ǥ��뤳��<br>
 *  ��256ʸ������Ǥ��뤳��<br>
 *
 * $level = 2:<br>
 *  ��@��������ʸ���郎Ⱦ�ѱ��羮ʸ����Ⱦ�ѿ�����Ⱦ�ѵ���(!#$%&'*+-/=?^_{}~)�Ǥ��뤳��<br>
 *  ��@��������1ʸ���ʾ�Τ���<br>
 *  ��@������ʸ���郎Ⱦ�ѱ��羮ʸ����Ⱦ�ѿ�����Ⱦ�ѵ���(-._)�Ǥ��뤳��<br>
 *  ��@������.��1�İʾ�ޤޤ�Ƥ��뤳��<br>
 *  ��@������.��Ϣ³���Ƥ��ʤ�����<br>
 *  ��@�����3ʸ���ʾ�Τ���<br>
 *  ��256ʸ������Ǥ��뤳��<br>
 *
 * $level = 3:<br>
 *  ��@�������ʸ���郎Ⱦ�ѱѾ�ʸ����Ⱦ�ѿ�����Ⱦ�ѵ���(-._)�Ǥ��뤳��<br>
 *  ��@��������1ʸ���ʾ�Τ���<br>
 *  ��@�����Τ�ʸ���郎Ⱦ�ѱѾ�ʸ����Ⱦ�ѿ�����Ⱦ�ѵ���(-._)�Ǥ��뤳��<br>
 *  ��@������.��1�İʾ�ޤޤ�Ƥ��뤳��<br>
 *  ��@������.��Ϣ³���Ƥ��ʤ�����<br>
 *  ��@�����3ʸ���ʾ�Τ���<br>
 *  ��256ʸ������Ǥ��뤳��
 *
 * @param string  $mail  �᡼�륢�ɥ쥹
 * @param integer $level �᡼�륢�ɥ쥹�Υ����å���٥�(1 or 2 or 3)
 *
 * @return bool �᡼�륢�ɥ쥹�η��������������TRUE���������ʤ����FALSE���֤��ޤ���
 *
 */
function DgCommon_is_mailaddr($mail, $level = 2)
{
    global $dg_err_msg;
    global $dg_log_msg;

    /* �����å���٥�η��������å� */
    if ($level != 1 && $level != 2 && $level != 3) {
        $dg_err_msg = "�᡼�륢�ɥ쥹�Υ����å���٥�η����������Ǥ���(" .
                      $level . ")";
        $dg_log_msg = "Check level of mailaddr is invalid.(" . $level . ")";
        return FALSE;
    }

    /* �᡼�륢�ɥ쥹��Ĺ�������å� */
    if (strlen($mail) > 256) {
        $dg_err_msg = "�᡼�륢�ɥ쥹�η����������Ǥ���(" . $mail . ")";
        $dg_log_msg = "Form of mailaddr is invalid.(" . $mail . ")";
        return FALSE;
    }

    if ($level == 1) {
        /* ��٥�1 */
        /* �᡼�륢�ɥ쥹�η��������å� */
        $num = "0123456789";
        $sl = "abcdefghijklmnopqrstuvwxyz";
        $sym = "-._@";
        $allow_letter = $num . $sl . $sym;
        if (strspn($mail, $allow_letter) != strlen($mail)) {
            $dg_err_msg = "�᡼�륢�ɥ쥹�η����������Ǥ���(" . $mail . ")";
            $dg_log_msg = "Form of mailaddr is invalid.(" . $mail . ")";
            return FALSE;
        }
    } else {
        /* ��٥�2,3 */
        /* @����Ĥ˶��ڤ�뤫�Υ����å� */
        $buf = explode('@', $mail, 2);
        if ($buf[0] == "" || $buf[1] == "") {
            $dg_err_msg = "�᡼�륢�ɥ쥹�η����������Ǥ���(" . $mail . ")";
            $dg_log_msg = "Form of mailaddr is invalid.(" . $mail . ")";
            return FALSE;
        }

        /* @������Υ����å� */
        /* Ⱦ�ѱѾ�ʸ�����������ʲ��ε���Τߵ��� */
        $num = "0123456789";
        $sl = "abcdefghijklmnopqrstuvwxyz";
        $ll = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
        $sym1 = "!#$%&'*+-/=?^_{}~.";
        $sym2 = "-._";

        /* ����ʸ�������� */
        if ($level == 2) {
            $front_letter = $num . $sl . $ll . $sym1;
            $back_letter = $num . $sl . $ll . $sym2;
        } else {
            $front_letter = $num . $sl . $sym2;
            $back_letter = $num . $sl . $sym2;
        }

        if (strspn($buf[0], $front_letter) != strlen($buf[0])) {
            $dg_err_msg = "�᡼�륢�ɥ쥹�η����������Ǥ���(" . $mail . ")";
            $dg_log_msg = "Form of mailaddr is invalid.(" . $mail . ")";
            return FALSE;
        }

        /*  @�����Υ����å� */
        if (strlen($buf[1]) < 3) {
            $dg_err_msg = "�᡼�륢�ɥ쥹�η����������Ǥ���(" . $mail . ")";
            $dg_log_msg = "Form of mailaddr is invalid.(" . $mail . ")";
            return FALSE;
        }

        /* �ɥåȤ���Ϥޤ�Х��顼 */
        if (substr($buf[1], 0, 1) == ".") {
            $dg_err_msg = "�᡼�륢�ɥ쥹�η����������Ǥ���(" . $mail . ")";
            $dg_log_msg = "Form of mailaddr is invalid.(" . $mail . ")";
            return FALSE;
        }

        /* 1�İʾ�ΥɥåȤ�ɬ�ܡ� */
        if (strpos($buf[1], ".") === FALSE) {
            $dg_err_msg = "�᡼�륢�ɥ쥹�η����������Ǥ���(" . $mail . ")";
            $dg_log_msg = "Form of mailaddr is invalid.(" . $mail . ")";
            return FALSE;
        }

        /* 2�İʾ�ΥɥåȤ�Ϣ³�϶ػߡ� */
        if (strpos($buf[1], "..") !== FALSE) {
            $dg_err_msg = "�᡼�륢�ɥ쥹�η����������Ǥ���(" . $mail . ")";
            $dg_log_msg = "Form of mailaddr is invalid.(" . $mail . ")";
            return FALSE;
        }

        if (strspn($buf[1], $back_letter) != strlen($buf[1])) {
            $dg_err_msg = "�᡼�륢�ɥ쥹�η����������Ǥ���(" . $mail . ")";
            $dg_log_msg = "Form of mailaddr is invalid.(" . $mail . ")";
            return FALSE;
        }

    }
    return TRUE;
}

/**
 *
 * �ͤ�1��0�Τ����줫�Ǥ��뤳�ȤΥ����å�(����ե�����Υ����å��ؿ�)
 *
 * @param string $num �����å�������
 *
 * @return bool ���������������TRUE���������ʤ����FALSE���֤��ޤ���
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
 * ���ե�����ƥ��η��������å�(����ե�����Υ����å��ؿ�)
 *
 * �����˻��ꤵ�줿���ե�����ƥ����ʲ���ʸ����Ȱ��פ��뤫������å����ޤ���<br>
 * ("auth", "authpriv", "cron", "daemon", "kern", "local0", "local1", "local2", "local3", "local4", "local5", "local6", "local7", "lpr", "mail", "news", "syslog", "user", "uucp")
 *
 * @param string $facility �����å�������ե�����ƥ�
 *
 * @return bool �����ǻ��ꤵ�줿���ե�����ƥ��η��������������TRUE���������ʤ����FALSE���֤��ޤ���
 *
 */
function DgCommon_is_facility($facility)
{

    /* �����å�����ե�����ƥ��Υꥹ�� */
    $flist = array("auth", "authpriv", "cron", "daemon", "kern", "local0",
                   "local1", "local2", "local3", "local4", "local5", "local6",
                   "local7", "lpr", "mail", "news", "syslog", "user", "uucp");

    /* �����ȥꥹ�Ȥ����פ�����TRUE */
    $ret = array_search($facility, $flist);
    if ($ret === FALSE) {
        return FALSE;
    }

    return TRUE;

}

/**
 *
 * Syslog�ե�����ƥ���ʸ���󤫤�ե�����ƥ��ͤ��Ѵ�(openlog��)
 *
 * �Ѵ����ե�����ƥ�ʸ���� => �Ѵ���ե�����ƥ���<br>
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
 * @param string $facility Syslog�ե�����ƥ���ʸ����
 *
 * @return integer �ե�����ƥ����б������ե�����ƥ��ͤ��֤��ޤ���
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
 * IP���ɥ쥹�η��������å�(����ե�����Υ����å��ؿ�)
 *
 * @param string $ipaddr �����å�����IP���ɥ쥹
 * @param bool   $ipv6   IPv6�η��������å��λ���TRUE������(�ǥե����:FALSE)
 *
 * @return bool IP���ɥ쥹�η���������������TRUE���������ʤ�����FALSE���֤��ޤ���
 *
 */
function DgCommon_is_ipaddr($ipaddr, $ipv6 = FALSE)
{

    if ($ipv6 === FALSE) {
        /* IPv4�η��������å� */

        /* �ɥåȤο��Υ����å� */
        $ip = explode(".", $ipaddr);
        $max = count($ip);
        if ($max != 4) {
            return FALSE;
        }

        for ($i = 0; $i < $max; $i++) {
            /* �����ɤ����Υ����å� */
            if ($ip[$i] === "") {
                return FALSE;
            }

            /* ���������ޤޤ�ʤ����Υ����å� */
            $num = "0123456789";
            if (strspn($ip[$i], $num) != strlen($ip[$i])) {
                return FALSE;
            }

            /* 0�ʾ�255�ʲ����ɤ����Υ����å� */
            if ($ip[$i] < 0 || $ip[$i] > 255) {
                return FALSE;
            }
        }

    } else {

        # �����ο������å�
        $ip = explode(":", $ipaddr);
        $max = count($ip);
        if ($max < 3 || $max > 8 ) {
            return FALSE;
        }

        # IPv6�η��������å�
        $ret = @inet_pton($ipaddr);
        if ($ret === FALSE) {
            return FALSE;
        }

    }

    return TRUE;

}

/**
 *
 * �Х��Ȥ�ᥬ�Х��Ȥ��Ѵ�
 *
 * @param integer $value  �Ѵ��������(�Х���)
 *
 * @return integer $value �ᥬ�Х��Ȥ��Ѵ����������ͤ��֤��ޤ���
 *
 */
function DgCommon_to_MB($value)
{
    if ($value <= 0) {
        return 0;
    }

    /*  1MB ��꾮���� */
    if ($value < 1048576) {
        return 1;
    }

    $tmp = $value / 1048576;
    return (int) (round($tmp));
}


/**
 *
 * �ѥ���ɤ�crypt�ǰŹ沽����
 *
 * @param string $passwd �Ź沽����ѥ����
 *
 * @return string �Ź沽���줿�ѥ���ɤ��֤��ޤ���
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
