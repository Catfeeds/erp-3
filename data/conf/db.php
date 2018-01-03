<?php
/**
 * 配置文件
 */
return array(
    'DB_TYPE' => 'mysql',
    'DB_HOST' => '',//
    'DB_NAME' => 'new_erp',
    'DB_USER' => 'root',
    'DB_PWD' => '',
    'DB_PORT' => '3306',
    'DB_PREFIX' => 'erp_',
    'DB_DEPLOY_TYPE'=> 1, // 设置分布式数据库支持
    'DB_RW_SEPARATE'=>false,//设置分布式数据库的读写是否分离，默认的情况下读写不分离

//    'REDIS_HOST'=> '',
//    'REDIS_PORT'=> ,
//    'DATA_CACHE_TIME'       => 0,      // 数据缓存有效期 0表示永久缓存
//    'DATA_CACHE_COMPRESS'   => true,   // 数据缓存是否压缩缓存
//    'DATA_CACHE_CHECK'      => false,   // 数据缓存是否校验缓存
//    'DATA_CACHE_PREFIX'     => '',     // 缓存前缀
//    'DATA_CACHE_TYPE'       => 'Redis',  // 数据缓存类型,
//    'REDIS_CTYPE'           => 2, //连接类型 1:普通连接 2:长连接
//    'REDIS_TIMEOUT'         => 0, //连接超时时间(S) 0:永不超时

    //密钥
    "AUTHCODE" => 'Fubp6CoTAupVlZXF88',
    //cookies
    "COOKIE_PREFIX" => 'WZpYIW_',
);