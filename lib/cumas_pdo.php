<?php
/**
 * CuMAS library - データベース処理
 *
 * PDOの委譲クラスを提供する。
 * 各画面でこれを継承し、必要なメソッドを追加して使う。
 * 共通関数があればここに追加してください。
 *
 * singletonパターンで実装される（≒ new禁止）。
 *
 * 今のところ、概ねPDOEXCEPTIONを投げるようになっている。
 */

include_once 'cumas_exception.php';

/**
 * CuMAS_PDO
 */
class CuMAS_PDO
{
    /**
     * 自分自身のインスタンスを保持する(singleton)
     */
    private static $_instance = null;

    /**
     * データベースの二重ロックを防ぐフェイルセーフ
     */
    private $isLocked = false;

    /**
     * PDOのインスタンスを格納する
     * (PDOのコンストラクタはprivateであることが要求されるため、継承できない)
     * (publicでも本当はいいんだけどフェイルセーフのためprotected)
     */
    protected $_pdo;

    /**
     * 複数回実行されるステートメントオブジェクトを格納しておく
     */
    protected $allMailStmt;


    /*******************
     * メソッドここから
     *******************/

    /**
     * 勝手なcloneを禁止する
     */
    public final function __clone() { }

    /**
     * getInstance()からのみ呼ばれる。
     * postgresqlを指定して接続を行う。
     * PDOのエラー処理を例外モードに切り替える。
     */
    protected function __construct($config)
    {
        $dsn = "pgsql:host={$config->dbserver};"
             . "port={$config->dbport};dbname={$config->dbname}";

        $this->_pdo = new PDO($dsn, $config->dbuser, $config->dbpasswd);
        $this->_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->_pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    /**
     * このインスタンスをロードする唯一の方法
     *
     * @return static class object (呼び出し元クラスを生成する)
     **/
    public static function getInstance(CuMAS_Config $config)
    {
        /* new static()だとこれ自身ではなく継承先クラスを生成できる */
        return self::$_instance ?: self::$_instance = new static($config);
    }

    /**
     * 最低限の機能は実装する
     * 実行方法や取得方法を指定したい場合、個別にメソッドを作成する
     */
    public function fetchAll($sql, $params = array())
    {
        if (!is_array($params)) {
            $params = array($params);
        }
        $s = $this->_pdo->prepare($sql);
        $s->execute($params);
        return $s->fetchAll();
    }
    public function execute($sql, $params = array())
    {
        $s = $this->_pdo->prepare($sql);
        $s->execute($params);
    }

    /**
     * DBのロックとコミットとロールバック
     * $this->isLockedを触るのはこれらのみ
     */
    public function lockTable($table, $mode = 'ACCESS EXCLUSIVE MODE')
    {
        if ($this->isLocked) {
            return;
        }
        if (is_array($table)) {
            $table = implode(",", $table);
        }

        $this->_pdo->beginTransaction();
        $ret = $this->_pdo->query("LOCK TABLE $table IN $mode");
        if ($ret === false) {
            throw new PDOException("Failed to Lock table 'contact_tab'.");
        }
        $this->isLocked = true;
    }
    public function commit()
    {
        $this->_pdo->commit();
        $this->isLocked = false;
    }
    public function rollBack()
    {
        $this->_pdo->rollBack();
        $this->isLocked = false;
    }

    /**
     * status_tabに登録されているステータス一覧を取得する。
     *
     * @return array st_id => st_statusな連想配列
     **/
    public function getAllStatus()
    {
        return $this->_pdo
                    ->query('SELECT st_status FROM status_tab ORDER BY st_id')
                    ->fetchAll(PDO::FETCH_COLUMN, 0);
    }
    /**
     * st_colorも一緒に返す
     */
    public function getStatusWithColor()
    {
        return $this->_pdo
                    ->query('SELECT st_status,st_color FROM status_tab')
                    ->fetchAll();
    }

    /**
     * お問い合わせ情報をcontact_tabから取得する
     */
    public function getJobDataByCoId($targetId)
    {
        $sql = "SELECT co_id, ma_subject, worker.us_name, co_status, co_us_id"
             . ", co_limit, co_comment, ma_from_addr, co_parent, co_child_no"
             . ", to_char(co_inquiry, 'YYYY/MM/DD HH24:MI') AS co_inquiry"
             . ", to_char(co_start, 'YYYY/MM/DD HH24:MI') AS co_start"
             . ", to_char(co_complete, 'YYYY/MM/DD HH24:MI') AS co_complete"
             . ", to_char(co_lastupdate, 'YYYY/MM/DD HH24:MI') AS co_lastupdate"
             . ", co_lastupdate AS raw_lastupdate"
             . ", ope.us_name as ope_name"
             . ", st_status, st_color"
             . ", co_ma_id"
             . ", contact_tab.ca_id"
             . ", ca.ca_name"
             . " FROM contact_tab"
             . " LEFT OUTER JOIN user_tab worker ON co_us_id = worker.us_id"
             . " LEFT OUTER JOIN user_tab ope ON co_operator = ope.us_id"
             . " JOIN status_tab ON co_status = st_id"
             . " JOIN mail_tab ON co_ma_id = ma_id"
             . " LEFT OUTER JOIN category_tab ca ON contact_tab.ca_id = ca.ca_id"
             . " WHERE co_id = ?";
        $s = $this->_pdo->prepare($sql);
        $s->execute(array($targetId));

        /* co_idの一意性により結果は1件と保証される (ので、fetch()でよい) */
        return $s->fetch();
    }

    /**
     *category_tabからアクティブなカテゴリのca_idとca_nameを取ってくる
     * @return array 2つの要素をもつ連想配列
     */
    public function getActiveCategories()
    {
        $sql = "SELECT ca_id,ca_name FROM category_tab where ca_active = TRUE"
             . " ORDER BY ca_id";
        return $this->_pdo->query($sql)->fetchAll();
    }

    /**
     *category_tabから全てカテゴリのca_idとca_nameを取ってくる
     * @return array 2つの要素をもつ連想配列
     */
    public function getAllCategories()
    {
        $sql = "SELECT ca_id,ca_name,ca_active FROM category_tab"
             . " ORDER BY ca_id";
        return $this->_pdo->query($sql)->fetchAll();
    }

    /**
     *user_tabからアクティブなユーザのus_idとus_nameを取ってくる
     * @return array 2つの要素をもつ連想配列
     */
    public function getActiveUsers()
    {
        $sql = "SELECT us_id,us_name FROM user_tab where us_active = TRUE"
             . " ORDER BY us_id";
        return $this->_pdo->query($sql)->fetchAll();
    }

    /**
     *user_tabから全てユーザのus_idとus_nameを取ってくる
     * @return array 2つの要素をもつ連想配列
     */
    public function getAllUsers()
    {
        $sql = "SELECT us_id,us_name,us_active FROM user_tab"
             . " ORDER BY us_id";
        return $this->_pdo->query($sql)->fetchAll();
    }

    /**
     * 親ジョブIDをもとにサブジョブを検索する
     */
    public function getSubjobByParentId($parent)
    {
        $sql = "SELECT co_id FROM contact_tab where co_parent = ? AND co_child_no is not NULL";

        $s = $this->_pdo->prepare($sql);
        $s->execute([$parent]);
        $subjobIds = $s->fetchAll(PDO::FETCH_COLUMN, 0);

        return $subjobIds;
    }

    public function getAllMailByCoId($co_id, $sort = 1)
    {
        if (empty($this->allMailStmt)) {
            $sql = "SELECT mail_tab.ma_id, ma_from_addr, ma_date, ma_subject, ma_from_addr"
                 . " FROM mail_tab"
                 . " JOIN contact_mail_tab ON contact_mail_tab.ma_id = mail_tab.ma_id"
                 . " WHERE co_id = ?"
                 . " ORDER BY ma_date";
            if ($sort == -1) {
                $sql .= " DESC";
            }
            $this->allMailStmt = $this->_pdo->prepare($sql);
        }
        $this->allMailStmt->execute(array($co_id));
        return $this->allMailStmt->fetchAll();
    }

    public function getTaskById($id)
    {
        $sql = "SELECT ta_subject from task_tab WHERE ta_id = ?";
        $taskStmt = $this->_pdo->prepare($sql);
        $taskStmt->execute(array($id));
        return $taskStmt->fetch();
    }

    /**
     *ta_idによってtask_tabからタスクを削除する
     * @return void
     */
    public function deleteTaskById($id)
    {
        $sql = "DELETE FROM task_tab WHERE ta_id = ?";
        $taskStmt = $this->_pdo->prepare($sql);
        $taskStmt->execute(array($id));
    }

    /**
     * メールは添付ファイルがあるか確認する
     * @return True|False
     */
    public function checkExistAttach ($ma_id)
    {
        $sql = "SELECT count(*) FROM attach_tab WHERE at_mailid = ?";
        $taskStmt = $this->_pdo->prepare($sql);
        $taskStmt->execute(array($ma_id));
        $data = $taskStmt->fetch();
        if ($data["count"] > 0) {
            return true;
        }
        return false;
    }
}
/* End of file cumas_pdo.php */
