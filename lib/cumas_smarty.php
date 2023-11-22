<?php
/**
 * CuMAS library - cumas_smarty.php
 *
 * Smartyを使った画面表示を行います。
 * 委譲によるAdapterパターン(的なもの)を採用しています。
 *
 * 特に引数を与えない場合、"foo.php"に対して"foo.tpl"を要求します。
 * CuMAS_Exceptionの他に、
 * SmartyException例外を発生させます。
 *
 * @author mamori@DesigNET.co.jp
 */


define('SMARTY_DIR', '/usr/share/php/Smarty/');
require_once(SMARTY_DIR . 'Smarty.class.php');

include_once 'cumas_exception.php';


class CuMAS_Smarty
{

    /**
     * テンプレートファイルの拡張子
     */
    const SUFFIX = ".tmpl";
    const TMPL_DIR = "tmpl";

    /**
     * Viewを担当するメソッドを格納する
     * Smartyを想定
     *
     * @var Smarty
     */
    protected $_view;

    /**
     * 呼び出し時点でSmartyオブジェクトの初期設定を済ます。
     * ディレクトリ構成は固定。
     *
     * @param array $params 設定したい項目があればこれで
     */
    public function __construct($params = array())
    {
        try {
            $this->_view = new Smarty();
            $this->_view->escape_html =
                !isset($params['escape_html']) ? true : $params['escape_html'];

            $this->_view->setTemplateDir(
                empty($params['template_dir']) ? "../" . self::TMPL_DIR . "/" : $params['template_dir']
            );
            $this->_view->setCompileDir(
                empty($params['compile_dir']) ? "../" . self::TMPL_DIR . "/template_c" : $params['compile_dir']
            );
            $this->_view->setConfigDir(
                empty($params['config_dir']) ? "../" . self::TMPL_DIR . "/configs" : $params['config_dir']
            );
            $this->_view->setCacheDir(
                empty($params['cache_dir']) ? "../" . self::TMPL_DIR . "/cache" : $params['cache_dir']
            );

        } catch (SmartyException $e) {
            throw new CuMAS_Exception($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * 画面表示を行います。
     * 引数を指定しなかった場合、呼び出し元のファイル名を使って
     * テンプレートファイル名を推測します。
     *
     * @param string $tmpl テンプレートファイル名
     *
     * @return none(printされます)
     * @author mamori
     **/
    public function display($tmpl = "", $fetchMode = false)
    {
        $templateName = $tmpl ?:
            pathinfo(debug_backtrace()[0]["file"])["filename"] . self::SUFFIX;
            // PHPファイルのフルパスから "foo.tmpl" を作る

        /* ファイルそのものの読み込み権だけはSmartyがチェックしてくれない */
        if (! is_readable($this->_view->template_dir[0] . $templateName)) {
            throw new CuMAS_Exception(
                "Unable to load template file '{$templateName}'"
            );
        }

        try {
            if ($fetchMode) {
                return $this->_view->fetch($templateName);
            } else {
                $this->_view->display($templateName);
                return;
            }
        } catch (SmartyException $e) {
            throw new CuMAS_Exception($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * テンプレート置換タグへのアクセサメソッド群
     * タグセットには基本的にはassign()を使うのがよい
     */
    /**
     * [ tag名 => 置換内容 ] の配列を渡すか、
     * tag名と置換内容をそのまま渡すかのどちらか
     * @param mixed $spec (array or string)
     * @param string $value
     */
    public function assign($spec, $value = null)
    {
        if (is_array($spec)) {
            $this->_view->assign($spec);
            return;
        }

        $this->_view->assign($spec, $value);
        return;
    }

    public function __get($key)
    {
        return $this->_view->getTemplateVars($key);
    }

    public function __set($key, $value)
    {
        $this->_view->assign($key, $value);
    }

    public function __isset($key)
    {
        return ($this->_view->getTemplateVars($key) !== null);
    }

    public function __unset($key)
    {
        $this->_view->clearAssign($key);
    }
    /*
     * アクセサメソッドここまで
     */
}

/* End of file cumas_smarty.php */
