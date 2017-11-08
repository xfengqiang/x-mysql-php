<?php
/**
 * Created by PhpStorm.
 * User: xufengqiang
 * Date: 2017/10/13
 * Time: 11:29
 */


require_once 'xmysql.php';

use xmysql\xmysql;
use xmysql\xmysql_loader;

$config = [
    xmysql_loader::DB_TYPE_MASTER=>[
        'host'=>'127.0.0.1',
        'port'=>3306,
        'username'=>'root',
        'password'=>'root',
        'dbname'=>'mall',
        'charset'=>'utf8',
    ],
    xmysql_loader::DB_TYPE_SLAVE=>[
        [
            'host'=>'127.0.0.1',
            'port'=>3306,
            'username'=>'root',
            'password'=>'root',
            'dbname'=>'mall',
            'charset'=>'utf8',
        ]
    ]
];

xmysql_loader::registerDb('mall', $config);
xmysql::setGlobalCallBack(function (xmysql $mysql, mysqli $db, $sql) {
    echo "[SQL] {$sql} TimeUsed:".$mysql->lastQueryTime()."ms errno:".$mysql->lastErrorCode()." errmsg:".$mysql->lastErrorMsg()."\n";
});

$db = new xmysql('mall');

function printRet($msg, $data) {
    echo "$msg ".json_encode($data)."\n";
}
//test raw
$ret = $db->query('SELECT * FROM user');
printRet("query", $ret);

$ret = $db->queryRow('SELECT * FROM user WHERE id=1');
printRet("query row", $ret);

//and condition
$ret = $db->select('user', 'name')->andc('id', 1)->queryRow();
printRet("test cond", $ret);

//equal condition
$ret = $db->select("user")->equal(['id'=>1])->queryRow();
printRet("test equal", $ret);

//in condition
$ret = $db->select("user")->in('id', [1])->queryRow();
printRet("test in", $ret);
//insert
$ret = $db->insert("user", ['name'=>'test-'.time()])->query();
echo "last insert id ".$db->lastInsertId()."\n";

printRet("test insert ", $ret);
//update
$ret = $db->update("user", ['ctime'=>time()])->andc('id', 1)->query();
printRet("test update", $ret);
//error
$ret = $db->insert("user", ['id'=>1])->query();
printRet("test error", $ret);

//count
$ret = $db->select("user", "count(*) as cnt")->queryRow();
printRet("test count", $ret);

//delete
$ret = $db->del("user")->andc('name','fankxu-rollback-tx')->orc('name','fankxu-commit-tx')->query();
printRet("test del", $ret);

$db->startTx();
$db->insert("user", ['name'=>"fankxu-rollback-tx"])->query();
$ret = $db->rollbackTx();
printRet("rollback ret", $ret);

$db->startTx();
$db->insert("user", ['name'=>"fankxu-commit-tx"])->query();
$ret = $db->commitTx();
printRet("commit ret", $ret);

//$db->commitTx();
