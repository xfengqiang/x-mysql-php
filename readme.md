# 组件介绍
PHP mysql orm封装，使用msyqli扩展实现 
- 支持读写分离
- 多从库配置 
- 事务操作
- 回调函数
- SQL语句快速拼装

## 引入文件
```
require_once 'xmysql.php';
use xmysql\db;
use xmysql\xmysql_loader;
```

## 数据库配置
```
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
```

### 注册数据库
```
xmysql_loader::registerDb('mall', $config);
```

### 设置全局回调函数
``` 
xmysql::setGlobalCallBack(function (xmysql $mysql, mysqli $db, $sql) {
    echo "[SQL] {$sql} TimeUsed:".$mysql->lastQueryTime()."ms errno:".$mysql->lastErrorCode()." errmsg:".$mysql->lastErrorMsg()."\n";
});
```

### 进行sql操作
``` 
$db = new xmysql_db('mall');

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

//transaction
$db->startTx();
$db->insert("user", ['name'=>"fankxu-rollback-tx"])->query();
$ret = $db->rollbackTx();
printRet("rollback ret", $ret);

$db->startTx();
$db->insert("user", ['name'=>"fankxu-commit-tx"])->query();
$ret = $db->commitTx();
printRet("commit ret", $ret);
```

### SQL语句拼装
``` 
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
```