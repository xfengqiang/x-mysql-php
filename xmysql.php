<?php
/**
 * 1.主从自动选择，支持多从，支持强制选主库
 * 2. 支持事务
 * 3, 支持回调
 * Created by PhpStorm.
 * User: xufengqiang
 * Date: 2017/10/13
 * Time: 11:12
 */

namespace xmysql;

require 'xmysql_loader.php';
require 'xmysql_cond.php';

class xmysql
{
    private static $globalCallBack = null; //默认全局回调函数

    private $_errNo = 0;
    private $_errMsg = '';
    private $_dbName = '';

    /**
     * @var xmysql_cond
     */
    private $_sqlCond = null;


    private $_enableProfile = true;
    private $_lastQueryTime = 0;
    private $_callback = null;

    /**
     * @var \mysqli
     */
    private $_lastQueryDb = null;
    private $_lastSql = null;


    /**
     * @var mysqli
     */
    private $_dbConn;
    /**
     * @var bool
     */
    private $_inTx = false;

    public static function setGlobalCallBack($callback) {
        self::$globalCallBack =  $callback;
    }

    public function __construct($dbName)
    {
        $this->_dbName = $dbName;
    }


    /**
     * @param $func
     * @return $this
     */
    public function callback($func) {
        $this->_callback = $func;
        return $this;
    }

    /**
     * @param string $fields
     * @return $this
     */
    public function select($table, $fields='*') {
        $this->_sqlCond = xmysql_cond::table($table)->select($fields);
        return $this;
    }

    /**
     * @param bool $ignoreInsert
     * @return $this
     */
    public function insert($table,  $data, $ignoreInsert=false){
        $this->_sqlCond = xmysql_cond::table($table)->insert($data, $ignoreInsert);
        return $this;
    }

    /**
     * @param $fields
     * @return $this
     */
    public function update($table, $data) {
        $this->_sqlCond = xmysql_cond::table($table)->update($data);
        return $this;
    }


    /**
     * @param $fields
     * @return $this
     */
    public function del($table) {
        $this->_sqlCond = xmysql_cond::table($table)->del();
        return $this;
    }


    /**
     * @return $this
     */
    public function where() {
        return $this;
    }

    /**
     * @param $k
     * @param $v
     * @param string $op
     * @return $this
     */
    public function andc($k, $v, $op='='){
        if($this->_sqlCond) {
            $this->_sqlCond->andc($k, $v, $op);
        }
        return $this;
    }

    /**
     * @param $k
     * @param $v
     * @param string $op
     * @return $this
     */
    public function orc($k, $v, $op='='){
        if($this->_sqlCond) {
            $this->_sqlCond->orc($k, $v, $op);
        }
        return $this;
    }

    /**
     * @param $k
     * @param $v
     * @param string $op
     * @return $this
     */
    public function equal($params){
        if($this->_sqlCond) {
            $this->_sqlCond->equal($params);
        }
        return $this;
    }

    /**
     * @param $k
     * @param $vals
     * @param string $cond
     * @return $this
     */
    public function in($k, $vals, $cond='AND') {
        if($this->_sqlCond) {
            $this->_sqlCond->in($k, $vals, $cond);
        }
        return $this;
    }

    /**
     * @param $k
     * @param $vals
     * @param string $cond
     * @return $this
     */
    public function notIn($k, $vals, $cond='AND') {
        if($this->_sqlCond) {
            $this->_sqlCond->notIn($k, $vals, $cond);
        }
        return $this;
    }

    /**
     * @param $order ['id'=>'desc']
     * @return $this
     */
    public function order($order) {
        if($this->_sqlCond) {
            $this->_sqlCond->order($order);
        }
        return $this;
    }

    /**
     * @param $offset
     * @param $limit
     * @return $this
     */
    public function limit($offset, $limit) {
        if($this->_sqlCond) {
            $this->_sqlCond->limit($offset, $limit);
        }
        return $this;
    }

    /**
     * @param $type
     * @return bool|\mysqli
     */
    public function db($type){
        return xmysql_loader::getDb($this->_dbName, $type);
    }

    public function exec($sql) {
        return $this->query($sql, xmysql_loader::DB_TYPE_MASTER);
    }

    public function queryByCond(xmysql_cond $cond, $type=xmysql_loader::DB_TYPE_AUTO) {
        $this->_sqlCond = $cond;
        return $this->query('', $type, false);
    }

    public function queryRowByCond(xmysql_cond $cond, $type=xmysql_loader::DB_TYPE_AUTO) {
        $this->_sqlCond = $cond;
        return $this->query('', $type, true);
    }

    public function query($sql='', $type=xmysql_loader::DB_TYPE_AUTO, $isRow=false) {
        if(!$sql)  {
            if(!$this->_sqlCond) {
                $this->_errMsg = "No sql condition was given";
                $this->_errNo = -1;
                return false;
            }

            if($type==xmysql_loader::DB_TYPE_AUTO) {
                $type = $this->_sqlCond->getQueryType();
            }

            if($type == xmysql_loader::DB_TYPE_AUTO) {
                $this->_errMsg = "The sql condition was not proper initialized";
                $this->_errNo = -2;
                return false;
            }

            $db = $this->_inTx ? $this->_lastQueryDb : self::db($type);
            $sql = $this->_sqlCond->sql($db);
        }else{
            $type = $this->getQueryType($sql, $type);
            $db = $this->_inTx ? $this->_lastQueryDb : self::db($type);
        }

        $this->_errMsg = '';
        $this->_errNo = 0;

        $this->_lastQueryDb = $db;
        if($this->_enableProfile) {
            $startTime = microtime(true);
        }

        $this->_lastSql = $sql;
        $ret = $db->query($sql);

        if($this->_enableProfile) {
            $endTime = microtime(true);
            $this->_lastQueryTime = $endTime-$startTime;
        }
        if($this->_callback) {
            call_user_func_array($this->callback, [$this, $db, $sql]);
        }
        if(self::$globalCallBack) {
            call_user_func_array(self::$globalCallBack, [$this, $db, $sql]);
        }

        if(is_bool($ret)) {
            return $ret;
        }
        return $isRow ? $ret->fetch_row() : $ret->fetch_all();
    }

    public function queryRow($sql='', $type=xmysql_loader::DB_TYPE_AUTO){
       return $this->query($sql, $type, true);
    }

    private function getQueryType($sql, $type) {
        if($type != xmysql_loader::DB_TYPE_AUTO) {
            return $type;
        }
        //insert update
       $sql = strtolower(ltrim($sql));
        if(substr($sql, 0, 4) == 'show' || substr($sql, 0, 6)=='select') {
            return xmysql_loader::DB_TYPE_SLAVE;
        }
        return xmysql_loader::DB_TYPE_MASTER;
    }

    public function lastErrorCode(){
        if(!$this->_lastQueryDb) {
            return $this->_errNo;
        }
        return $this->_lastQueryDb->errno;
    }
    public function lastErrorMsg(){
        if(!$this->_lastQueryDb) {
            return $this->_errMsg;
        }
        return $this->_lastQueryDb->error;
    }

    public function lastSql() {
        return $this->_lastSql;
    }

    public function rowsAffected(){
        if(!$this->_lastQueryDb) {
            return 0;
        }
        return $this->_lastQueryDb->affected_rows;
    }

    public function lastInsertId(){
        if(!$this->_lastQueryDb) {
            return false;
        }
        return $this->_lastQueryDb->insert_id;
    }

    public function lastQueryTime() {
        return $this->_lastQueryTime;
    }

    public function getDbName() {
        return $this->_dbName;
    }

    public function isInTx() {
        return $this->_inTx;
    }

    public function startTx(){
        if($this->_inTx) {
            return false;
        }
        $this->_lastQueryDb = xmysql_loader::getDb($this->_dbName, xmysql_loader::DB_TYPE_MASTER);
        $this->_inTx  =  $this->_lastQueryDb->begin_transaction();
        return $this->_inTx;
    }
    public function commitTx(){
        $ret = false;
        if($this->_inTx) {
            $ret = $this->_lastQueryDb->commit();
            $this->_inTx = false;
        }

        return $ret;
    }
    public function rollbackTx(){
        $ret = false;
        if($this->_inTx) {
            $ret = $this->_lastQueryDb->rollback();
            $this->_inTx = false;
        }
        return $ret;
    }

}