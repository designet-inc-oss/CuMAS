<?php

class CuMAS_Exception extends Exception {
    const SYSERR = <<<EOD
<!DOCTYPE html>
<html>
<head>
<meta http-equiv="content-type" content="text/html; charset=utf-8">
<title>メール問い合わせ管理システム</title>
</head>
<body>
<Div Align="center">
  <h1>システムエラー</h1>
  <p>
  システムにエラーが発生したため、現在お問い合わせ管理システムはご利用出来ません。
  <br>
  システム管理者にお問い合わせください。
  </p>
</DIV>
</body>
</html>
EOD;

    public function log($facility, $filename) {
        openlog(basename($filename), LOG_PID, $facility);
        syslog(LOG_ERR, $this->message);
    }

    public static function log_s($facility, $filename, $_message) {
        openlog(basename($filename), LOG_PID, $facility);
        syslog(LOG_ERR, $_message);
    }

    public static function printErr() {
        printf(self::SYSERR);
    }
}

/* End of file cumas_exception.php */
