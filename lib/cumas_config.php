<?php
/**
 * Cumas library - cumas_config.php
 *
 * 設定ファイルを読み込みます。
 * Dg_Commonを利用します。
 *
 * CuMAS_Exceptionを投げます。
 */
$dg_err_msg;
$dg_log_msg;


require_once 'cumas_exception.php';
require_once 'Dg_Common.php';

/**
 * 追加となるチェック関数
 */
function _is_plus($num)
{
    return is_numeric($num) && $num >= 0;
}
function _is_natural($num)
{
    return is_numeric($num) && $num > 0;
}

function _is_no_empty($str)
{
    if (empty($str)) {
        return false;
    }
    return true;
}

function _is_RW_dir($path)
{
    return is_dir($path) && is_readable($path) && is_writable($path);
}

function _is_statusId($ids)
{
    foreach (explode(",", $ids) as $oneId) {
        if (! _is_plus(trim($oneId))) {
            return false;
        }
    }
    return true;
}

class CuMAS_Config
{
    const CONFIG_PATH = "../etc/cumas.conf";

    protected $keys = [
        "dbserver" => "DgCommon_is_ipaddr",
        "dbport" => "DgCommon_is_port",
        "dbname" => "DgCommon_check_none",
        "dbuser" => "DgCommon_check_none",
        "dbpasswd" => "DgCommon_check_none",
        "syslogfascility" => "DgCommon_is_facility",
        "linesperpage" => "_is_natural",
        "mailsavedir" => "_is_RW_dir",
        "incomplete" => "_is_statusId",
        "latedays" => "_is_plus",
        "startyear" => "_is_plus",
        "sessiontimeout" => "_is_natural",
        "hostname" => "_is_no_empty",
        "unknownattachfilename" => "_is_no_empty",
    ];

    protected $default = [
        "SyslogFascility" => "local4",
        "LinesPerPage" => "20",
        "LateDays" => "3",
        "SessionTimeout" => "600",
    ];

    function __construct()
    {
        global $dg_log_msg;
        $config = DgCommon_read_conf(
            self::CONFIG_PATH,
            $this->keys,
            $this->default
        );
        if (! $config) {
            throw new CuMAS_Exception($dg_log_msg);
        }

        /* このクラスのプロパティに設定値をセット */
        foreach ($config as $key => $value) {
            $this->$key = $value;
        }

        if (isset($this->incomplete)) {
            $this->incomplete = explode(",", $this->incomplete);
        }
    }

    /**
     * SQL文で使える形式で、未完了ステータスを整形して返す
     * (co_status = 1 or co_status = 2 or co_status = 3)
     */
    public function setupIncompleteCondition()
    {
        $str = implode(
            " or ",
            array_map(
                function ($s) {
                                return "co_status = $s";
                },
                $this->incomplete
            )
        );
        return "($str)";
    }
}

/* End of file cumas_config.php */
