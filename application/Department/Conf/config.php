<?php
$configs = array(
    'HTML_CACHE_RULES' => array(
        // 定义静态缓存规则
        // 定义格式1 数组方式
        'admin:department' => array('department/index/index/{id}',600),
        'index:index' => array('department/index',600),
        'list:index' => array('department/list/{id}_{p}',60)
    )
);

return array_merge($configs);
