<?php
/**
 * 1.主从自动选择，支持多从，支持强制选主库
 * 2. 支持事务
 * 3. 支持回调
 * 4. 考虑连接池
 * Created by PhpStorm.
 * User: xufengqiang
 * Date: 2017/10/13
 * Time: 11:12
 */

namespace xmysql;

class xmysql_loader
{
    const DB_TYPE_AUTO = 0;
    const DB_TYPE_MASTER = 1;
    const DB_TYPE_SLAVE = 2;

    private static $dbCache = [];
    private static $dbConfig = [];

    public static function registerDbs($config){
        foreach ($config as $name => $cfg) {
            self::$dbConfig[$name] = $cfg;
        }
    }

    public static function registerDb($name, $config){
        self::$dbConfig[$name] = $config;
    }

    public static function getDbConfig($name) {
        return isset(self::$dbConfig[$name]) ? self::$dbConfig[$name] : null;
    }

    /**
     * @param $name
     * @param $type
     * @return bool|\mysqli
     * @throws Exception
     */
    public static function getDb($name, $type) {
        if(!isset(self::$dbConfig[$name])) {
            return false;
        }

        $dbType = $type;
        if($type == self::DB_TYPE_SLAVE && !isset(self::$dbConfig[$name][$dbType])) {
            $dbType = self::DB_TYPE_MASTER;
        }

        if(isset(self::$dbCache[$name][$dbType])) {
            return self::$dbCache[$name][$dbType];
        }

        if($dbType ==  self::DB_TYPE_MASTER) {
            $dbConfig = self::$dbConfig[$name][$dbType];
        }else{
            //随机选择一个slave配置
            $dbConfigs = self::$dbConfig[$name][$dbType];
            $i=count($dbConfigs);
            if($i > 1) {
                $dbConfig = $dbConfigs[$i%rand(0, $i-1)];
            }else{
                $dbConfig = $dbConfigs[0];
            }
        }
        $db = new \mysqli($dbConfig['host'], $dbConfig['username'], $dbConfig['password'], $dbConfig['dbname'], $dbConfig['port']);
        if($db->connect_errno){
            throw new \Exception("Db connect errNo:{$db->connect_errno} errMsg:{$db->connect_error}", -1);
        }
        $charset =  empty($dbConfig['charset']) ? 'UTF8' : $dbConfig['charset'];
        $db->set_charset($charset);
        self::$dbCache[$name][$dbType] = $db;
        return $db;
    }

    public static function closeDb($name){
        $types = [xmysql::QUERY_MASTER, xmysql::QUERY_SLAVE];
        foreach ($types as $type) {
            if(isset(self::$dbCache[$name][$type])) {
                $db = self::$dbCache[$name][$type];
                $db->close();
                unset(self::$dbCache[$name][$type]);
            }
        }
    }

    public static function closeAll(){
        foreach (self::$dbCache as $name => $dbs) {
            foreach ($dbs as $db){
                $db->close();
            }
        }
        self::$dbCache = [];
    }
}