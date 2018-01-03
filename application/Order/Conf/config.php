<?php
$configs = array(
    'HTML_CACHE_RULES' => array(
        // 定义静态缓存规则
        // 定义格式1 数组方式
        //'Order:index' => array('Order/index/index',600),
        //'Api:post_data' => array('Order/Api/post_data',600),
        //'list:index' => array('Order/list/{id}_{p}',60)
    )
);

return array_merge($configs);
