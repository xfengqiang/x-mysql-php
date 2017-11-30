<?php

/**
 * Created by PhpStorm.
 * User: xufengqiang
 * Date: 2017/8/18
 * Time: 12:46
 */
namespace xmysql;

class xmysql_cond{
    const QUERY_TYPE_READ = 1;
    const QUERY_TYPE_WRITE = 2;

    /**
     * @var xmysql
     */

    private $table;
    private $conds = array();
    private $orders = array();

    private $_sql = '';
    private $queryType;

    private $page  = null;
    private $oper = null;
    private $fields = '*';


    /**
     * @param $table
     * @return xmysql_cond
     */
    public static function table($table) {
        return new xmysql_cond($table);
    }

    /**
     * @param $params
     * @return string
     */
    public static function equalCond($params, \mysqli $escapeDb=null) {
        $ret = [];
        foreach ($params as $k=>$v){
            $ret[] = "`$k`='".self::escapeValue($v, $escapeDb)."'";
        }
        return implode(',', $params);
    }

    /**
     * @param $vals
     * @return string
     */
    public static function inCond($vals, \mysqli $escapeDb=null) {
        $inCond = [];
        foreach ($vals as $val){
            $inCond[] = "'".self::escapeValue($val, $escapeDb)."'";
        }
        return implode($inCond, ',');
    }

    /**
     * xmysql_cond constructor.
     * @param $table
     */
    public function __construct($table){
        $this->table = $table;
    }

    /**
     * @param string $fields
     * @return $this
     */
    public function select($fields='*') {
        $this->oper = "SELECT";
        $this->fields = $fields;
        $this->queryType = self::QUERY_TYPE_READ;
        $this->_sql = '';
        return $this;
    }

    /**
     * @param bool $ignoreInsert
     * @return $this
     */
    public function insert($data, $ignoreInsert=false){
        $this->queryType = self::QUERY_TYPE_WRITE;
        if($ignoreInsert){
            $this->oper = 'INSERT IGNORE INTO';
        }else{
            $this->oper = 'INSERT INTO';
        }

        $this->fields = $data;
        $this->_sql = '';
        return $this;
    }

    /**
     * @param $fields
     * @return $this
     */
    public function update($fields) {
        $this->queryType = self::QUERY_TYPE_WRITE;
        $this->_sql = '';
        $this->fields = $fields;
        $this->oper = "UPDATE";
        return $this;
    }

    /**
     * @return $this
     */
    public function del(){
        $this->queryType = self::QUERY_TYPE_WRITE;
        $this->_sql = '';
        $this->oper = "DELETE";
        $this->fields = '';
        return $this;
    }

    /**
     * @param $k
     * @param $v
     * @param string $op
     * @return $this
     */
    public function andc($k, $v, $op='='){
        $this->_sql = '';
        $this->conds[] = ['k'=>$k, 'v'=>$v, 'op'=>$op, 'cond'=>'AND'];
        return $this;
    }

    /**
     * @param $k
     * @param $v
     * @param string $op
     * @return $this
     */
    public function orc($k, $v, $op='='){
        $this->_sql = '';
        $this->conds[] = ['k'=>$k, 'v'=>$v, 'op'=>$op, 'cond'=>'OR'];
        return $this;
    }

    /**
     * @param $k
     * @param $v
     * @param string $op
     * @return $this
     */
    public function equal($params){
        $this->_sql = '';
        foreach ($params as $k => $v) {
            $this->conds[] = ['k'=>$k, 'v'=>$v, 'op'=>'=', 'cond'=>'AND'];
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
        $this->_sql = '';
        $this->conds[] = ['k'=>$k, 'v'=>$vals, 'op'=>'IN', 'cond'=>$cond];
        return $this;
    }

    /**
     * @param $k
     * @param $vals
     * @param string $cond
     * @return $this
     */
    public function notIn($k, $vals, $cond='AND') {
        $this->_sql = '';
        $this->conds[] = ['k'=>$k, 'v'=>$vals, 'op'=>'NOT IN', 'cond'=>$cond];
        return $this;
    }

    /**
     * @param $k
     * @param string $type
     * @return $this
     */
    public function order() {
        $this->_sql = '';
        $args = func_get_args();
        switch (count($args)){
            case 1:
                    if(is_array($args[0])) {
                        foreach ($args[0] as $k=>$type) {
                            $this->orders[] = "`{$k}` {$type}";
                        }
                    }else{ //string
                        $this->orders[] = "`{$args[0]}`";
                    }
                break;
            case 2:
                $this->orders[] = "`{$args[0]}` {$args[1]}";
                break;
            default:
                //wrong
                break;
        }


        return $this;
    }

    /**
     * @param $_
     * @return $this
     */
    public function limit($_) {
        $this->page = func_get_args();
        return $this;
    }


    /**
     * @return string
     */
    public function sql(\mysqli $db=null) {
        if($this->_sql) {
            return $this->_sql;
        }

        if($this->oper == "SELECT" || $this->oper == "DELETE") {
            $this->_sql = $this->oper." ".$this->fields." FROM `".$this->table."`";
        }else if($this->oper == "UPDATE"){
            $fields = $this->getSetFields($db, $this->fields);
            $this->_sql = $this->oper." `{$this->table}` SET".$fields." ";
        }else { //INSERT
            $fields = $this->getSetFields($db, $this->fields);
            $this->_sql = $this->oper." `".$this->table."` SET ".$fields;
        }

        if($this->conds){
            $this->_sql .= " WHERE";
            foreach ($this->conds as $idx=>$cond) {
                if($idx==0) {
                    $this->_sql .= " `{$cond['k']}`";
                }else{
                    $this->_sql .= " {$cond['cond']} `{$cond['k']}`";
                }
                if($cond['op'] == 'IN' || $cond['op'] == 'NOT IN') {
                    $incond = [];
                    foreach ($cond['v'] as $v) {
                        $incond[]="'".self::escapeValue($v, $db)."'";
                    }
                    $this->_sql .= "{$cond['op']} ".sprintf("(%s)", implode($incond, ','));
                }else{
                    $this->_sql .= "{$cond['op']}'".self::escapeValue($cond['v'], $db)."'";
                }
            }
        }

        if($this->orders) {
            $this->_sql .= " ORDER BY ".implode($this->orders, ",");
        }

        if($this->page) {
            switch (count($this->page)) {
                case 1:
                    $this->_sql .= " LIMIT {$this->page[0]}";
                    break;
                default:
                    $this->_sql .= " LIMIT {$this->page[0]},{$this->page[1]}";
                    break;
            }
        }
        return $this->_sql;
    }

    public function getQueryType() {
        return $this->queryType;
    }

    /**
     * @param $v
     * @param \mysqli $db
     * @return mixed
     */
    private static function escapeValue($v,  $db) {
        return $db ? $db->escape_string($v) : mysql_real_escape_string($v);
    }

    /**
     * @param \mysqli $db
     * @param $fields
     * @return string
     */
    private function getSetFields($db, $fields){
        if(is_string($fields)) {
            return $fields;
        }
        $vals = [];
        foreach ($fields as $k => $v) {
            $vals[] = "`{$k}`='".self::escapeValue($v, $db)."'";
        }
        return  implode($vals, ',');
    }

}

function testCond() {
    //select
    $sql = xmysql_cond::table('gjj_invite_activity_user')
        ->select('id,team_id')
        ->equal(['user_id'=>1])
        ->where('query_status', 0, '>')
        ->limit(10, 10)
        ->order(['create_time'=>'DESC'])
        ->sql();

    echo "[SELECT] {$sql}\n";

    //insert
    $sql = xmysql_cond::table('gjj_invite_activity_user')
        ->insert(['user_id'=>1,'user_name'=>'fankxu'])
        ->sql();

    echo "[INSERT] {$sql}\n";

    //update
    $sql = xmysql_cond::table('gjj_invite_activity_user')
        ->update(['user_id'=>1,'user_name'=>'fankxu'])
        ->where('id', 1)
        ->sql();
    echo "[UPDATE] {$sql}\n";
}

//testCond();