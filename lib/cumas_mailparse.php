<?php

/* バイトぶん読み込んだ */
define("LENGTH_READ",  4096);

/* MIMEタイプ */
define("DF_MIME_TYPE", "application/octet-stream");

/* 添付ファイルファイル名 */
define("DF_ATTACH_FN", "Attachment-%s-%s.dat");

class MailParser
{
    /**
     * リソースID
     */
    public $resource;

    /**
     * メールへのファイルポインター
     */
    public $stream;

    /**
     * メールのテキスト
     */
    public $raw_data;

   /**
    * 添付ファイルの配列
    */
    public $attachment_streams;

   /**
    * メールのすべてチパート 
    */
    private $parts;

   /**
    * 初期化する
    * @return
    */
    public function __construct() 
    {
	 $this->raw_data = "";
         $this->attachment_streams = array();
    }

   /**
    * 保持しているリソースを解放する
    * @return void
    */
    public function __destruct() 
    {
        /* MailParseリソースをクリアする */
        if (is_resource($this->resource)) {
            mailparse_msg_free($this->resource);
        }

	/* 添付リソースを削除する */
        foreach($this->attachment_streams as $mparse_attachment) {
            $mparse_attachment->close_stream();
        }
    }

   /**
    * ストリームの設定とデータの解析
    * @return void
    */
    public function setStream($stream) 
    {
        $raw_data = "";
        $this->resource = mailparse_msg_create();
	if ($this->resource === false) {
            throw new CumasMailParseException("mailparse create msg failed.");
        }

	/* メッセージを解析 */
        while(!feof($stream)) {
            $raw_data .= fread($stream, LENGTH_READ);
	    if ($raw_data === false) {
                throw new CumasMailParseException("read from stream failed.");
            }
	}

	/* メールテキストを保存 */
        $this->raw_data = $raw_data;

	/* データをパースし、バッファに追加 */
	$ret = mailparse_msg_parse($this->resource, $raw_data);
	if ($ret === false) {
            throw new CumasMailParseException("mailparse parse msg failed.");
        }

        $this->parse();
    }

   /**
    * メッセージを部分に分解する
    * @return void
    * @private
    */
    private function parse() 
    {
        $this->parts = array();

	/* 指定したメッセージ内の MIME セクション名の配列を返す */
        $structure = mailparse_msg_get_structure($this->resource);

        foreach($structure as $part_id) {
	    /* MIME メッセージの指定したセクションに関するハンドルを返す */
            $part = mailparse_msg_get_part($this->resource, $part_id);

	    /* メッセージに関する情報の連想配列を返す */
            $this->parts[$part_id] = mailparse_msg_get_part_data($part);
        }
    }

   /**
    * MIMEパーツのヘッダーを返す
    * @param $part Array
    * @return Array
    */
    private function getPartHeaders($part) 
    {
        if (isset($part['headers'])) {
            return $part['headers'];
	}
	return false;
    }

   /**
    * ヘッダ名の値を取得する
    * @param String $name
    * @return String
    */
    public function getHeader($name) 
    {
        if (isset($this->parts[1])) {
            $headers = $this->getPartHeaders($this->parts[1]);
            if (isset($headers[$name])) {
                return $headers[$name];
            }
        }
	return "";
    }

   /**
    * テキストからMIMEパーツから本文を取得する
    * @param $part Array
    * @return String Mime Body Part
    */
    private function getPartBodyFromText($part) {
	$start = $part['starting-pos-body'];
	$end = $part['ending-pos-body'];
	$body = substr($this->raw_data, $start, $end-$start);
	return $body;
    }

    /**
    * メールメッセージの本文を指定された形式で返します
    * @param $type Object[optional]
    * @return Mixed String Body 見つからない場合 False
    */
    public function getMessageBody($type = 'text') 
    {
        $body = false;
        $mime_types = array(
            'text'=> 'text/plain',
            'html'=> 'text/html'
        );

	if (!in_array($type, array_keys($mime_types))) {
            throw new CumasMailParseException(
                'Invalid type specified. "type" can either be text or html.');
	}

        foreach($this->parts as $part) {

            /* content-typeを存在しないまたは
             * content-typeはtext/plain・text/htmlではない場合
             */
            if ($this->getPartContentType($part) !== $mime_types[$type]) {
                continue;
            }

            /*
             * content-dispositionの値はattachmentの場合、本文とみなしない
             */
            if (isset($part['content-disposition']) &&
                strpos($part['content-disposition'], "attachment") !== false) {
                continue;
            }           

	    $headers = $this->getPartHeaders($part);

            $transfer_encoding = "";
	    if (isset($part["transfer-encoding"]) && 
                ($part["transfer-encoding"] !== "")) {
                $transfer_encoding = $part["transfer-encoding"];
	    } else {
                if (isset($headers["content-transfer-encoding"])) {
                    $transfer_encoding = $headers['content-transfer-encoding'];
                }
            }

            $body = $this->decode($this->getPartBodyFromText($part),
                                  $transfer_encoding);
       }

       return $body;
    }

    /**
     * エンコードタイプに応じて文字列をデコードします。
     * @param $encodedString  元のエンコードされた状態の文字列
     * @param $encodingType   パーツのContent-Transfer-Encodingヘッダーの
     *                        エンコーディングタイプ
     * @return String the decoded string.
     */
    private function decode($encodedString, $encodingType) 
    {
        if (strtolower($encodingType) === 'base64') {
            return base64_decode($encodedString);
        } else if (strtolower($encodingType) === 'quoted-printable') {
            return quoted_printable_decode($encodedString);
        } else {
            return $encodedString;
        }
    }

   /**
    * 添付ファイルの内容を出現順に返します
    * @param $type Object[optional]
    * @return Array
    */
    public function getAttachments() 
    {
        $attachments = array();
  
        $dispositions = array("attachment");

        foreach($this->parts as $part) {

	    /* content-dispositionの値を取得 */
            $disposition = $this->getPartContentDisposition($part);

            /* content-disposisionがattachmentであるパート
             * を添付ファイルとみなす 
             */
            if (in_array($disposition, $dispositions)) {

                $attachments[] = new MailParserAttachment(
                    $this->getAttachFilename($part), 
                    $this->getPartContentType($part), 
                    $this->getAttachmentStream($part),
                    $disposition,
                    $this->getPartHeaders($part)
		);
            }
	}

	$this->attachment_streams = $attachments;
    }

   /**
    * 添付ファイル名を取得する
    * @param $part Array
    * @return NULL       ファイル名を取得できず
    * @return FALSE MIME ヘッダフィールドのデコードに失敗
    * @return String     ファイル名
    */
    public function getAttachFilename($part)
    {
        $disposition_filename = "";
        $content_name = "";

        /* content-dispositionヘッダのfilename*n*パラメータの値 */
        if (isset($part['disposition-filename'])) {
            $disposition_filename = $part['disposition-filename'];
        }

        /* content-typeヘッダのnameパラメータの値 */
        if (isset($part["content-name"])) {
            $content_name = $part["content-name"];
        }

        if ($disposition_filename !== "") {
            /* MIME ヘッダフィールドをデコード */
            $attach_fn = iconv_mime_decode($disposition_filename);
            if ($attach_fn === false) {
                $attach_fn = FALSE;
            }
        } else if ($content_name !== "") {
            /* MIME ヘッダフィールドをデコード */
            $attach_fn = iconv_mime_decode($content_name);
            if ($attach_fn === false) {
                $attach_fn = FALSE;
            }
        } else {
            /* ファイル名を取得できず場合、NULLをセット */
            $attach_fn = NULL;
        }
        
        return $attach_fn;
    }

   /**
    * MIMEパーツのContentTypeを返します
    * @param $part Array
    * @return String
    */
    private function getPartContentType($part) 
    {
        if (isset($part['content-type'])) {
            return $part['content-type'];
        }

        /* MIMEタイプが不明の場合 */
        return DF_MIME_TYPE;
   }

   /**
    * 添付ファイルの本文を読み取り、一時ファイルリソースを保存します
    * @param $part Array
    * @return $temp_fp
    */
    private function getAttachmentStream($part) 
    {
        $temp_fp = tmpfile();   

        /* content-transfer-encodingのエンコード方式に従って、
         * デコードした生の形式のファイルを保存する
         */
        array_key_exists('content-transfer-encoding', $part['headers']) ?
            $encoding = $part['headers']['content-transfer-encoding'] : $encoding = '';

        try {
	    $attachment = $this->decode(
                              $this->getPartBodyFromText($part), $encoding);
            fwrite($temp_fp, $attachment, strlen($attachment));
	    fseek($temp_fp, 0);
	} catch (Exception $ex) {
            throw new CumasMailParseException(
                "Could not write to temporary files: $temp_fp");
        }

        return $temp_fp;
    }

    /**
    * Content Dispositionを返す
    * @return String
    * @param $part Array
    */
    private function getPartContentDisposition($part) 
    {
        if (isset($part['content-disposition'])) {
            return $part['content-disposition'];
        }
        return false;
    }
}

/**
 * Model of an Attachment
 */
class MailParserAttachment 
{
   /**
    * ファイル名
    */
    public  $filename;

   /**
    * MIMEタイプ
    */
    public  $content_type;

   /**
    * Content-Disposition (attachment or inline)
    */
    public $content_disposition;

   /**
    * 添付ヘッダーの配列
    */
    public $headers;

   /**
    * ストリーム
    */
    private  $stream;

    public function __construct($filename, $content_type, $stream,
	       $content_disposition = 'attachment', $headers = array()) {
        $this->filename = $filename;
        $this->stream = $stream;
        $this->content_disposition = $content_disposition;
        $this->headers = $headers;

        /* NOTICE
         * MIMEタイプが不明の場合、php-pecl-mailparseには
         * text/plainを採用しているがデージーネットは
         * application/octet-streamとする仕様に変更
         */
        $this->_getContentType($content_type);
    }

    /**
     * content-typeから、MIMEタイプを取得する
     * @return String
     */
    private function _getContentType($content_type)
    { 
        if (!isset($this->headers["content-type"])) {
            $this->content_type = DF_MIME_TYPE;
        } else {
            $this->content_type = $content_type;
        }
    }

    /**
    * ストリームを閉じる
    * @return String
    */
    public function close_stream()
    {
         fclose($this->stream);
    }

   /**
    * 添付ファイル名を取得する
    * @return String
    */
    public function getFilename() {
        return $this->filename;
    }

   /**
    * 添付Content-Typeを取得する
    * @return String
    */
    public function getContentType() {
        return $this->content_type;
    }

   /**
    * 添付Content-Dispositionを取得する
    * @return String
    */
    public function getContentDisposition() {
        return $this->content_disposition;
    }

   /**
    * 添付Headersを取得する
    * @return String
    */
    public function getHeaders() {
        return $this->headers;
    }

   /**
    * 完了するまで、一度に数バイトずつ内容を読み取る
    * @return String
    * @param $bytes Int[optional]
    */
    public function read($bytes = LENGTH_READ) {
        return feof($this->stream) ? false : fread($this->stream, $bytes);
    }
}

class CumasMailParseException extends Exception
{
}

?>
