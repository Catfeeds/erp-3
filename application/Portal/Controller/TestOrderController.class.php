<?php
/*
 *      _______ _     _       _     _____ __  __ ______
 *     |__   __| |   (_)     | |   / ____|  \/  |  ____|
 *        | |  | |__  _ _ __ | | _| |    | \  / | |__
 *        | |  | '_ \| | '_ \| |/ / |    | |\/| |  __|
 *        | |  | | | | | | | |   <| |____| |  | | |
 *        |_|  |_| |_|_|_| |_|_|\_\\_____|_|  |_|_|
 */
/*
 *     _________  ___  ___  ___  ________   ___  __    ________  _____ ______   ________
 *    |\___   ___\\  \|\  \|\  \|\   ___  \|\  \|\  \ |\   ____\|\   _ \  _   \|\  _____\
 *    \|___ \  \_\ \  \\\  \ \  \ \  \\ \  \ \  \/  /|\ \  \___|\ \  \\\__\ \  \ \  \__/
 *         \ \  \ \ \   __  \ \  \ \  \\ \  \ \   ___  \ \  \    \ \  \\|__| \  \ \   __\
 *          \ \  \ \ \  \ \  \ \  \ \  \\ \  \ \  \\ \  \ \  \____\ \  \    \ \  \ \  \_|
 *           \ \__\ \ \__\ \__\ \__\ \__\\ \__\ \__\\ \__\ \_______\ \__\    \ \__\ \__\
 *            \|__|  \|__|\|__|\|__|\|__| \|__|\|__| \|__|\|_______|\|__|     \|__|\|__|
 */
// +----------------------------------------------------------------------
// | ThinkCMF [ WE CAN DO IT MORE SIMPLE ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013-2014 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: Dean <zxxjjforever@163.com>
// +----------------------------------------------------------------------
namespace Portal\Controller;
use Common\Controller\HomebaseController;
use Common\Lib\Currency;

/**
 * 首页
 */
class TestOrderController extends HomebaseController {
    private $not_found = <<<'NOT'
<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML 2.0//EN">
<html><head>
<title>404 Not Found</title>
</head><body>
<h1>Not Found</h1>
<p>The requested URL / was not found on this server.</p>
<hr>
<address>Apache/2.2.15 (CentOS) Server at . Port 80</address>
</body></html>
NOT;
	public function index() {
	    header('HTTP/1.0 404 Not Found.');
        echo $this->not_found;
	    exit;
    }
    
    public function order()
    {
        $key = trim(I('get.key'));
        $id = trim(I('get.id'));
        if (!$key || !$id) {
            header('HTTP/1.0 404 Not Found.');
            echo $this->not_found;
            exit;
        }
	    $order = D('Order/Order')->where(array(
	        'id_order' => $id,
            'id_increment' => $id,
            '_logic' => 'OR'
        ))->find();
        if (!$order) {
            exit(json_encode(array(
                'msg' => 'not found',
                'err' => 1
                )));
        }
        
        $order['order_info'] = D('Order/OrderInfo')->where(array(
            'id_order' => $order['id_order']
        ))->select();
        $order['order_item'] = D('Order/OrderItem')->where(array(
            'id_order' => $order['id_order']
        ))->select();
        $order['order_return'] = D('Order/OrderReturn')->where(array(
            'id_order' => $order['id_order']
        ))->select();
        $order['order_settlement'] = D('Order/OrderSettlement')->where(array(
            'id_order' => $order['id_order']
        ))->select();
        $order['order_shipping'] = D('Order/OrderShipping')->where(array(
            'id_order' => $order['id_order']
        ))->select();
        
        exit(json_encode(array(
            'err' => 0,
            'order' => $order
        )));
    }

    
}


