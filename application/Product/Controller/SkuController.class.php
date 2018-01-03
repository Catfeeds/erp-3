<?php
namespace Product\Controller;
use Common\Controller\AdminbaseController;

/**
 * 产品模块
 * @Author morrowind
 * @qq 752979972
 * Class IndexController
 * @package Product\Controller
 */
class SkuController extends AdminbaseController{
	protected $product;
	public function _initialize() {
		parent::_initialize();
		$this->product=D("Common/Product");
	}

    /**
     * SKU列表
     */
    public function index(){
        $where =array();
        if(isset($_GET['inner_name'])&& $_GET['inner_name']){
            $where['p.inner_name'] = array('like','%'.$_GET['inner_name'].'%');
        }
        if(isset($_GET['sku'])&& $_GET['sku']){
//            $sku = $_GET['sku'];
//            $sku_where = "sku='$sku'";
//            $productIds = D("Common/ProductSku")->where($sku_where)->getField('id_product',true);
//            if($productIds){
//                $where['p.id_product'] = array('in',$productIds);
//            }else{
//                $where['p.id_product'] = 0;
//            }
            $key_where['ps.sku'] = array('like','%'.$_GET['sku'].'%');
            $key_where['ps.barcode'] = array('like','%'.$_GET['sku'].'%');
            $key_where['_logic'] = 'or';
            $where['_complex'] = $key_where;
        }
        $department_id = isset($_SESSION['department_id'])?$_SESSION['department_id']:array(0);
        $where['p.id_department'] = isset($_GET['id_department']) && $_GET['id_department'] != ''?array('EQ',$_GET['id_department']):array('IN',$department_id);
        if(isset($_GET['id_department']) && $_GET['id_department']){
            $where['p.id_department']= $_GET['id_department'];
        }
        $where['ps.status'] = 1;// 使用的SKU状态
        $M = new \Think\Model;
        $pro_table = D("Common/Product")->getTableName();
        $pro_s_table = D("Common/ProductSku")->getTableName();
        $find_count = $M->table($pro_table.' AS p LEFT JOIN '.$pro_s_table.' AS ps ON p.id_product=ps.id_product')
            ->field('count(*) as count')->where($where)->find();
        $count= $find_count['count'];
        $page = $this->page($count,20);

        $proList = $M->table($pro_table.' AS p LEFT JOIN '.$pro_s_table.' AS ps ON p.id_product=ps.id_product')
            ->field('p.id_department,ps.sku,ps.barcode,ps.model,ps.option_value,ps.purchase_price,ps.weight,p.inner_name,p.id_product,p.thumbs,ps.id_product_sku')->where($where)
            ->order("p.id_product DESC")->limit($page->firstRow . ',' . $page->listRows)->select();

        $value_model  = D("Common/ProductOptionValue");
        if($proList && count($proList)){
            foreach($proList as $key=>$item){
                $option_value = $item['option_value'];
                if($option_value){
                    $get_value = $value_model->where('id_product_option_value in('.$option_value.')')->getField('title',true);
                    $proList[$key]['value'] = $get_value?implode('-',$get_value):'';
                    $proList[$key]['img'] = json_decode($item['thumbs'],true);
                }
            }
        }
        $department_id  = $_SESSION['department_id'];
        $department = D('Common/Department')->where('type=1')->cache(true,6000)->select();
        $department  = $department?array_column($department,'title','id_department'):array();
        add_system_record(sp_get_current_admin_id(), 4, 2, '查看SKU列表');
        $this->assign("department_id", $department_id);
        $this->assign('department',$department);
        $this->assign("proList",$proList);
        $this->assign("page", $page->show('Admin'));
        $this->display();
	}    
    
    public function export_list() {
        $M = new \Think\Model;
        $pro_table = D("Common/Product")->getTableName();
        $pro_s_table = D("Common/ProductSku")->getTableName();
        $productWhere =array();
        if(isset($_GET['inner_name'])&& $_GET['inner_name']){
            $productWhere['p.inner_name'] = array('like','%'.$_GET['inner_name'].'%');
        }
        if(isset($_GET['sku'])&& $_GET['sku']){
//            $productIds = D("Product/ProductSku")->where('sku like "%'.$_GET['sku'].'%"')->group('id_product')->getField('id_product',true);
//            if($productIds){
//                $productWhere['p.id'] = array('in',$productIds);
//            }else{
//                $productWhere['p.id'] = 0;
//            }
            $key_where['ps.sku'] = array('LIKE', '%' . $_GET['sku'] . '%');
            $key_where['ps.barcode'] = array('LIKE', '%' . $_GET['sku'] . '%');
            $key_where['_logic'] = 'or';
            $productWhere['_complex'] = $key_where;
        }
        $department_id = isset($_SESSION['department_id'])?$_SESSION['department_id']:array(0);
        $productWhere['p.id_department'] = isset($_GET['id_department']) && $_GET['id_department'] != ''?array('EQ',$_GET['id_department']):array('IN',$department_id);
        if(isset($_GET['id_department']) && $_GET['id_department']){
            $productWhere['p.id_department']= $_GET['id_department'];
        }
        $productWhere['ps.status'] = 1;// 使用的SKU状态
        $proList = $M->table($pro_table.' AS p LEFT JOIN '.$pro_s_table.' AS ps ON p.id_product=ps.id_product')
            ->field('p.id_department,ps.id_product_sku,ps.sku,ps.barcode,ps.model,ps.option_value,ps.purchase_price,ps.weight,p.inner_name,p.id_product')->where($productWhere)->order("p.id_product DESC")
            ->select();
        $departmentList = D('Common/Department')->where('type=1')->getField('id_department,title');
        $value_model  = D("Common/ProductOptionValue");
        if($proList && count($proList)){
            foreach($proList as $key=>$item){
                $option_value = $item['option_value'];
                if($option_value){
                    $get_value = $value_model->where('id_product_option_value in('.$option_value.')')->getField('title',true);
                    $proList[$key]['value'] = $get_value?implode('-',$get_value):'';
                }
            }
        }

        vendor("PHPExcel.PHPExcel");
        vendor("PHPExcel.PHPExcel.IOFactory");
        $excel = new \PHPExcel();
        $column = array('内部名称','部门','SKU','条形码','属性','SKU_ID','采购价','重量');
        $j = 65;
        foreach ($column as $col) {
            $excel->getActiveSheet()->setCellValue(chr($j).'1', $col);
            ++$j;
        }
        
        $idx = 2;
        foreach($proList as $key=>$product){
            $productName = $product['inner_name']?$product['inner_name']:$product['title'];
            $rowData = array($productName,$departmentList[$product['id_department']],' '.$product['sku'],$product['barcode'],$product['value'],$product['id_product_sku'],$product['purchase_price'],$product['weight']);
            $j = 65;
            foreach ($rowData as $col) {
                $excel->getActiveSheet()->setCellValue(chr($j).$idx, $col);
                ++$j;
            }
            ++$idx;
        }
        add_system_record(sp_get_current_admin_id(), 7, 2, '导出SKU列表');
        $excel->getActiveSheet()->setTitle(date('Y-m-d').'SKU列表.xlsx');
        $excel->setActiveSheetIndex(0);
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . date('Y-m-d') . 'SKU列表.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        $writer->save('php://output');die();
    }
    public function update_status(){
        set_time_limit(0);
        $all_sku = D("Common/ProductSku")->select();
        $opt_val_model = D("Common/ProductOptionValue");
        echo '总共'.count($all_sku).'<br />';
        $no_user = 0;$user_count = 0;
        foreach($all_sku as $sku){
            if($sku['option_value']!=0){
                $implode = $sku['option_value']?explode(',',$sku['option_value']):array(0);
                $count_current = count($implode);
                $where   = array('id_product'=>$sku['id_product'],'id_product_option_value'=>array('IN',$implode));
                $get_value_count = $opt_val_model->where($where)->count();
                if($count_current!=$get_value_count){
                    $id_product_sku = $sku['id_product_sku'];
                    //echo $sku['option_value'].'==='.$get_value_count.'<br />';
                    D("Common/ProductSku")->where(array('id_product_sku'=>$id_product_sku))->save(array('status'=>0));
                    $no_user++;
                }else{
                    $user_count++;
                }
            }else{
                //先添加产品没有设置属性，后面再设置属性，所以需要再查下一次
                $other = D("Common/ProductSku")->where(array('id_product'=>$sku['id_product']))->count();
                if($other>1){
                    D("Common/ProductSku")->where(array('id_product_sku'=>$sku['id_product_sku']))->save(array('status'=>0));
                    $no_user++;
                    echo $sku['option_value'].'==='.$other.'<br />';
                }else{
                    $user_count++;
                }
            }
        }
        echo '使用中'.$user_count.'  没有使用的'.$no_user.'<br />';
        echo '执行完成';
    }
    public function update_order_sku(){
        set_time_limit(0);
        $order_name = D("Order/Order")->getTableName();
        //$item_where['o.id_increment']  = array('GT',80090009);
        $item_where['o.id_order_status'] = array('IN',array(1,2,3,4,5,6,7,8,9));
        $item_where['_string'] = '(oi.id_product is null or oi.id_product=0) or (oi.id_product_sku is null or oi.id_product_sku=0)';
        $count = D("Common/OrderItem")->alias('oi')->field('oi.*,o.id_department,o.id_domain')
            ->join($order_name.' o ON (o.id_order = oi.id_order)', 'LEFT')->where($item_where)
            ->count();
        $page_size = 10000;
        $page = ceil($count/$page_size);
        $temp_data = array();
        $temp_order = array();
        /** @var \Domain\Model\DomainModel $domain_model */
        $domain_model = D('Domain/Domain');
        $domain = $domain_model->get_all_domain();
        for($i=0;$i<$page;$i++){
            $firstRow = $i*$page_size;
            $listRows = ($i+1)*$page_size;
            echo $firstRow.','.$listRows.'<br />';
            $order_item = D("Common/OrderItem")->alias('oi')->field('oi.*,o.id_department,o.id_domain')
                ->join($order_name.' o ON (o.id_order = oi.id_order)', 'LEFT')
                ->where($item_where)->limit($firstRow. ',' .$listRows)->select();
            foreach($order_item as $key=>$item){
                $id_order_item   = $item['id_order_item'];
                $id_product_sku  = (int)$item['id_product_sku'];
                $id_product      = (int)$item['id_product'];
                $id_department   = $item['id_department'];
                $id_domain       = $item['id_domain'];
                if(!$id_product_sku){
                    $temp_data[$id_department]['sku_empty'][$id_domain] = $domain[$id_domain];
                    $temp_order[$id_department][] = $item['id_order'];
                }
                if($id_product){
                    $where = array('id_product'=>$id_product,'id_product_sku'=>$id_product_sku);
                    $sku_data = D("Common/ProductSku")->where($where)->find();
                    if($sku_data['sku']){
                        $set_data  = array('sku'=>$sku_data['sku']);
                        D("Common/OrderItem")->where(array('id_order_item'=>$id_order_item))->save($set_data);
                    }else{
                        $temp_data[$id_department]['product_empty'][$id_domain] = $domain[$id_domain];
                        $temp_order[$id_department][] = $item['id_order'];
                    }
                }else{
                    //产品ID 不存在，记录订单和部门
                    $temp_data[$id_department]['product_empty'][$id_domain] = $domain[$id_domain];
                    $temp_order[$id_department][$item['id_order']] = $item['id_order'];
                }
            }
        }
        if($temp_data){
            foreach($temp_data as $b=>$temp){
                echo $b.'组==';
                $product_arr =array_flip($temp['product_empty']);
                print_r($product_arr);
                $sku_arr =array_flip($temp['sku_empty']);
                print_r($sku_arr);
                echo '<br />';
            }
        }
        print_r($temp_order);
        $logTxtFile = 'PostOrderData/update_order_sku.txt';
        $logContent = json_encode($temp_data).PHP_EOL.PHP_EOL.json_encode($temp_order);
        file_put_contents('./'.C("UPLOADPATH").$logTxtFile,$logContent,FILE_APPEND);
    }

    /**
     * 设置无效的产品SKU
     */
    public function invalid_setting_sku(){
        set_time_limit(0);
        $use_product_id = array(681,407,602,2301,697,666,2287,2006,2679,2629,2010,1847,3057,1858,1897,2397,2771,2563,2653,2008,2055,686,691,2127,2129,1838,1933,2193,2649,2573,674,330,2983,2985,2989,2991,2993,2995,2997,2999,3001,3003,2413,2415,2463,2385,2261,2263,2265,2267,2087,625,2399,2303,2513,1925,2199,2209,1918,1830,2067,2187,2151,2549,2799,2893,606,237,2045,673,2119,1770,646,590,685,2873,2875,2877,2879,2881,2883,2885,1767,1909,615,1834,369,292,2273,671,1657,2865,1687,2955,1648,611,1649,658,651,1812,2903,2797,2947,2781,2685,589,1661,688,635,636,1669,607,692,2153,1787,1799,1800,2821,2869,2537,2461,2485,358,1723,2251,1769,2727,1992,2551,2515,626,1785,609,1867,2311,1913,648,605,660,2723,627,653,2161,2117,2165,628,1746,622,638,2279,1872,2635,632,414,3027,678,679,684,1876,2269,689,1757,1759,608,2527,1836,2935,1683,2347,654,2367,643,614,616,1873,3029,693,1682,2845,2847,2849,2851,680,2453,2945,2977,647,2387,2517,2449,2315,2313,1994,1971,2219,3097,696,2593,2557,1827,1828,1686,2623,2001,1862,1882,652,2271,2459,2147,672,257,3019,2645,1775,624,1660,2731,1930,1814,1857,2831,2531,2535,1924,1655,2307,1663,1664,2079,2081,2083,2505,2541,619,1659,1665,1685,2587,2163,665,629,675,2813,1645,2521,2619,2601,1964,2785,1662,2339,1856,1914,3049,3051,2509,1651,1792,1656,620,1910,1896,1890,591,3007,3011,3013,3015,3017,634,1887,1861,2257,2361,2003,656,1702,2469,2471,2357,495,1712,1886,2553,630,2489,1677,1678,1650,1807,1868,1821,2495,639,1681,2253,2667,633,1810,2167,2169,2171,2173,2175,1714,649,645,1795,3089,2547,1741,1826,2059,2801,2237,1806,1695,610,1744,640,2283,663,1819,2291,1721,1934,2137,1658,2565,2007,2717,2523,2741,650,2835,2837,2839,2841,662,2345,496,2035,2041,668,618,621,1877,1879,1717,1750,2907,2185,595,677,1937,655,1762,1923,1965,641,2575,644,2235,2012,2815,2641,659,2499,1996,664,2951,2071,2061,2063,687,642,2561,1735,2203,1719,2141,1854,661,617,1811,1997,2585,1736,657,1653,1804,2507,1647,1646,676,669,667,2969,2241,1777,1883,1654,287,2159,2481,3061,670,637,521,411,389,479,459,454,424,516,464,328,352,562,534,450,390,355,298,524,586,554,347,309,310,291,594,335,470,290,441,448,344,546,263,382,307,551,432,338,308,359,456,364,557,461,455,533,574,322,329,604,442,420,244,243,445,506,564,426,422,537,306,517,555,476,477,478,451,452,437,596,235,545,3109,3101,3103,370,367,253,254,255,380,362,429,379,449,419,425,509,301,350,434,404,583,396,467,503,536,528,547,581,541,515,597,305,304,592,392,603,599,393,314,535,381,375,400,579,377,361,530,339,507,327,553,373,502,378,486,520,415,542,428,512,409,569,593,341,462,469,543,387,560,296,295,510,337,518,457,549,395,386,578,261,439,511,356,601,447,499,481,245,340,538,567,522,346,333,353,385,563,584,427,570,576,430,241,232,412,587,316,493,540,500,514,539,504,490,485,558,489,498,315,276,406,256,391,446,336,258,565,488,556,519,492,471,505,513,497,466,408,418,230,577,402,423,431,354,460,444,323,458,399,598,585,494,334,544,3147,3149,3151,342,234,240,275,561,349,374,388,443,435,413,532,383,325,324,463,401,468,300,326,480,600,372,348,317,2639,289,288,293,238,271,368,568,397,363,365,487,3159,267,559,433,438,588,280,501,345,303,526,299,482,483,491,297,571,366,566,531,484,436,410,269,523,580,239,421,405,294,529,525,351,508,384,573,394,416,465,548,440,321,572,575,582,403,1881,2027,1117,1483,1463,1467,1088,1238,1798,1080,1537,1222,1969,2943,1321,1014,2099,3063,1071,2149,1606,1358,1147,1107,1998,1280,1278,1155,2501,1119,1309,1224,2545,1592,1031,1426,1456,1539,1870,1320,1590,1598,1599,3025,1283,1530,1634,2817,1550,1357,1171,1368,1554,2103,2925,2579,1276,1142,2843,1242,1905,1449,1188,1729,1728,1730,1432,1508,1901,1902,1903,2023,1039,1470,1167,1446,1316,1317,1318,1254,1249,1192,1208,2755,2097,1444,1285,2715,2677,1232,2627,1293,1095,1390,1391,1389,1392,1393,1395,1245,2713,1272,2115,1460,2609,1165,1499,2543,1610,1423,1441,1771,2631,1045,1310,1261,1972,1495,1404,1148,1561,1940,1154,1084,1622,1145,1065,1415,1184,2673,1693,1017,1524,2707,1301,1300,1299,1303,1302,1874,1350,2341,1699,1051,1296,1349,1608,1026,1343,1346,1347,1345,1348,1134,1436,1226,1146,1360,1516,1473,1422,1378,1330,1097,2089,1419,1865,1114,1451,1977,1993,1288,1888,1187,2729,1496,2403,2493,1471,1957,2621,1256,1237,2705,1138,1412,1219,1967,1528,1252,2047,1149,1387,1305,1024,1509,2643,1545,1229,1860,2525,1274,1428,1429,1340,2775,2657,1544,1258,1575,1417,1137,1607,2825,1488,1364,2971,1904,1023,1690,1844,2929,1760,1381,1382,1341,1538,1845,2773,1637,1457,1259,1668,1067,2895,1078,1384,1855,1221,1916,2757,1430,1523,1337,1540,2967,1490,1413,2603,1186,1183,2791,2597,1135,1454,2343,1271,1956,1506,1604,1900,1376,1169,1037,1063,1927,2353,1517,1234,1893,1438,3059,1559,1574,1465,1670,1152,1326,1842,2703,1568,2075,1377,1386,1175,1260,1268,1227,1126,1959,1459,1203,1204,1206,1143,2625,1878,2637,1335,1244,2379,3055,1773,1355,2767,1279,1281,1500,1501,1502,1275,2807,1569,1172,2697,1584,2787,1105,1027,1028,1214,1522,1898,1899,2145,1266,2183,1353,2769,1308,1235,1818,1141,1363,1513,1802,1786,1979,2335,1086,1625,2927,1593,3083,3081,2941,1052,2589,2911,1315,1191,1123,1440,1589,1197,1136,1514,1765,1450,1210,1241,1739,1277,1253,1322,1099,1701,1151,1632,1421,2833,1319,1289,1529,1531,1816,1029,1518,1122,1644,3099,1976,1213,1416,1043,1180,1324,1168,1286,1069,1217,1263,1515,1475,1474,1476,1112,1591,1056,1255,1484,2827,1284,1342,1401,1215,2965,1583,1704,2409,1218,1312,1504,1619,2329,2331,2683,2655,2923,3053,1796,1233,1116,1560,1211,2765,1803,1733,1111,1231,1129,1209,1153,1749,1697,2701,2661,1125,1692,1198,1442,1133,1200,2647,2939,1403,1823,1824,1825,2577,1667,1160,1754,1115,1159,1156,1367,1369,1409,1408,1407,1406,1121,1491,1076,1372,1371,1399,1127,1212,1420,2733,1443,1262,1291,1797,1118,1694,2091,1766,1781,1059,1140,1748,1106,1325,1472,2349,1193,2891,1906,1109,2783,2591,2699,2607,2777,2555,1605,1020,1570,1571,1572,1124,1555,2659,1691,2691,2689,1612,1743,1189,1643,1642,1431,1110,1033,1752,1726,1177,1178,1297,1128,1265,1075,1447,1482,1573,1439,2599,1626,1269,1585,1586,1162,2871,2567,1374,2611,1246,2571,2613,1737,1120,1194,1396,1596,1638,1223,1920,1405,1758,1427,1543,1174,1864,1833,1835,1103,2581,1185,1264,1639,1040,1176,1287,1542,1207,1173,1273,1130,2887,1365,1434,1435,2981,2053,1332,1333,1452,1370,1486,2569,1158,1132,1398,1487,1247,1507,1327,1328,1329,1230,1248,2325,1562,1755,1468,1082,2709,1519,1520,1521,1257,1331,2018,2019,2020,2021,2022,1041,1498,2051,1228,1492,2043,2761,1101,1161,2811,1375,2763,1379,1863,2931,1093,1477,1385,2779,1239,1995,2421,2369,2819,1205,2675,3087,1843,1582,1251,1497,1388,1722,1510,1195,1298,1131,1336,1788,2719,1794,1960,1201,2979,2391,2393,2615,2381,2633,1199,2351,1157,1594,1163,1366,1359,1270,1240,1535,1597,1455,1338,1339,1433,1581,2131,1620,1485,2917,1179,1181,1091,1144,1323,2669,1182,1216,1402,1461,1448,1624,1425,1991,1170,1588,1196,2135,1778,1202,2695,1558,1541,1139,1418,1595,1846,1793,1616,3175,1552,1587,1505,1466,2605,1849,717,1696,2359,2745,1961,813,814,1895,3005,740,791,792,1751,841,721,2363,713,1945,1946,737,2028,818,819,733,846,848,800,764,862,845,847,804,2721,856,1007,730,744,716,2133,1912,1947,868,747,707,1955,869,822,784,1747,743,2533,738,2973,783,2243,865,757,731,787,2987,837,1628,2002,724,2026,1613,777,779,2793,2795,725,746,852,741,714,807,1725,726,2949,2751,748,790,812,769,820,1982,1715,745,2125,811,760,2227,749,830,1839,860,853,854,824,831,832,808,2157,2285,711,806,735,1931,2229,732,2247,734,710,763,864,722,828,2445,780,867,2529,752,861,795,759,755,1850,774,1841,815,2595,796,826,788,838,770,1948,825,851,772,2239,823,1689,1674,2511,739,786,708,2749,3085,709,1859,857,843,827,775,1986,1950,850,798,754,1974,1975,715,2245,866,3091,2963,1641,729,833,758,793,794,1885,1809,2759,720,849,855,723,836,1636,1983,1952,2205,842,797,1951,2233,753,3077,781,2365,1928,859,789,1710,835,736,1740,727,2213,863,2295,1949,785,1711,719,839,1707,1621,805,2441,1679,751,2005,844,834,829,2693,756,718,712,2029,994,980,962,1791,1848,958,1004,946,2959,918,922,915,997,1926,936,875,900,983,978,1727,1734,909,908,3033,929,886,937,896,894,887,1943,1944,979,891,966,903,2899,988,870,951,899,911,1880,888,2433,2411,938,1921,1953,952,878,2297,948,939,2231,932,890,984,981,902,904,2725,1706,873,919,957,941,892,954,907,885,2111,991,976,928,2191,2189,975,1919,879,949,995,1790,964,906,971,881,2919,1671,961,968,927,1673,933,889,967,893,963,897,1999,2077,895,1875,972,912,1837,1962,1963,940,973,955,2225,996,953,2024,2025,950,1805,969,990,1001,925,926,1617,993,923,989,2201,2123,977,3031,3035,2221,2223,2031,3105,965,916,913,914,1939,992,2309,2255,1984,1985,917,985,910,901,880,2443,2439,947,2735,1732,2933,2937,970,2275,2277,1008,1917,898,2455,2539,2457,1829,2371,2375,877,956,2747,1980,1981,2429,1718,872,1784,2217,924,2249,920,1672,2823,1789,1006,986,942,943,944,945,2033,2037,2039,1676,921,982,1614,930,931,1840,883,2259,2855,876,1915,934,935,974,1908,2207,998,2181,2289,2897,2859,1738,987,2417,959,905,2889,1698,960,1,2,3,4,5,6,7,8,9,10,11,13,12,15,14,16,109,17,18,19,20,21,22,23,24,25,27,28,29,26,30,31,32,33,34,35,36,39,37,38,40,41,42,43,44,45,46,47,48,49,50,51,52,53,54,57,58,55,56,59,60,61,62,63,64,65,66,67,68,69,70,71,72,73,74,75,113,77,76,78,79,80,82,81,84,85,86,87,88,108,110,111,89,90,91,92,93,94,96,95,97,98,99,101,100,104,102,103,105,112,114,115,116,117,118,119,120,121,122,123,124,125,126,127,128,129,130,131,132,133,134,135,136,137,138,139,140,141,142,143,144,145,146,147,148,149,150,151,152,153,154,155,156,157,158,159,160,161,162,163,164,165,166,167,168,169,170,171,172,173,174,1618,176,175,177,178,179,180,181,182,183,184,185,186,187,188,189,190,191,228,682,694,1635,695,699,701,702,703,999,1009,1010,1011,1012,1013,1640,1675,1680,1684,1688,1700,1703,1705,1708,1716,1753,1756,1761,1763,1772,1776,1808,1813,1817,1820,1822,1831,1866,1869,1871,1889,1938,1941,1958,1966,1970,1973,1978,1990,2004,2009,2011,2014,2015,2069,2073,2085,2093,2102,2105,2109,2113,2155,2177,2179,2195,2215,2281,2293,2305,2321,2323,2327,2333,2337,2373,2377,2383,2395,2401,2407,2425,2427,2431,2435,2437,2447,2451,2473,2475,2477,2479,2483,2487,2497,2503,2559,2651,2663,2737,2739,2753,2743,2789,2803,2805,2809,2853,2857,2861,2863,2867,2901,2905,2909,3021,3023,3043,3047,3069,3073,3093,3113,3115,3119,3123,3075,197,2405,1988,2000,1801,208,215,1615,196,1742,220,192,210,203,200,3041,3045,3095,199,214,1666,206,1987,207,1779,1005,217,3067,205,202,193,198,3065,1907,1968,213,2095,204,201,1989,1852,2423,1724,221,222,223,224,225,2197,2317,2107,1709,683,216,1932,2057,2465,2467,1768,227,1774,194,1942,1884,2319,1911,2049,2389,1853,2419,209,3071,3037,3039,218,219,1851,1935,1936,700,1929,226,3079,3107,3111,3117,3121,3125,3127,3129,3131,3133,3135,3137,3139,3141,3143,3145,3153,3155,3157,3161,3163,3165,3167,3169,3171,3173,3177,3179,3181);
        $all_sku = D("Common/ProductSku")->where(array('status'=>1))->select();
        $opt_val_model = D("Common/ProductOptionValue");
        echo '总共'.count($all_sku).'<br />';
        $no_user = 0;$user_count = 0;
        $warehouse_product = D('Common/WarehouseProduct');
        foreach($all_sku as $sku){
            $id_product_sku  = $sku['id_product_sku'];
            if($sku['option_value']!=0){
                $implode         = $sku['option_value']?explode(',',$sku['option_value']):array(0);
                $count_current   = count($implode);
                $where           = array('id_product'=>$sku['id_product'],'id_product_option_value'=>array('IN',$implode));
                $get_value_count = $opt_val_model->where($where)->count();

                if(!in_array($sku['id_product'],$use_product_id) or $count_current!=$get_value_count){
                    //echo $sku['option_value'].'==='.$get_value_count.'<br />';
                    D("Common/ProductSku")->where(array('id_product_sku'=>$id_product_sku))->save(array('status'=>0));
                    $no_user++;
                }else{
                    $this->add_warehouse_qty($sku);
                    $user_count++;
                }
            }else{
                //先添加产品没有设置属性，后面再设置属性，所以需要再查下一次
                $other = D("Common/ProductSku")->where(array('id_product'=>$sku['id_product']))->count();
                if(!in_array($sku['id_product'],$use_product_id) or $other>1){
                    D("Common/ProductSku")->where(array('id_product_sku'=>$sku['id_product_sku']))->save(array('status'=>0));
                    $no_user++;
                    echo $sku['option_value'].'==='.$other.'<br />';
                }else{
                    $this->add_warehouse_qty($sku);

                    $user_count++;
                }
            }
        }
        echo '使用中'.$user_count.'  没有使用的'.$no_user.'<br />';
        echo '执行完成';
    }
    public function add_warehouse_qty($sku){
        if($sku['id_product'] && $sku['id_product_sku']){
            $warehouse_product = D('Common/WarehouseProduct');
            $all_warehouse     = array(1,2,7);
            foreach($all_warehouse as $ware_id){
                $add_ware = array(
                    'id_warehouse' => $ware_id,
                    'id_product' => $sku['id_product'],
                    'id_product_sku' => $sku['id_product_sku']
                );
                $find_ware = $warehouse_product->where($add_ware)->find();
                if(!$find_ware){
                    $add_ware['quantity'] = 0;
                    $add_ware['road_num'] = 0;
                    $warehouse_product->data($add_ware)->add();
                }
            }
        }
    }
    
    /**
     * 批量开启产品状态
     */
    public function batch_update_status() {
        $infor = array(
            'error' => array(),
            'warning' => array(),
            'success' => array()
        );
        $total = 0;
        if (IS_POST) {
            $data = I('post.data');
            //导入记录到文件
            $path = write_file('product', 'update_pro_status', $data);
            $data = $this->getDataRow($data);
            $count = 1;
            $msg = $_POST['status']==1?'开启':'关闭';
            foreach ($data as $row) {
                $row = trim($row);
                if (empty($row))
                    continue;
                ++$total;
                $row = explode(" ", trim($row), 2);
                $sku = $row[0];
                $pro_sku = M('ProductSku')->where(array('sku'=>$sku))->select();
                if($pro_sku) {
                    foreach ($pro_sku as $k=>$v) {
                        $pro_id = $v['id_product'];//产品id
                        $sku_id = $v['id_product_sku'];//sku id
                        D('Product/Product')->where(array('id_product'=>$pro_id))->save(array('status'=>$_POST['status']));
                        D('Product/ProductSku')->where(array('id_product_sku'=>$sku_id))->save(array('status'=>$_POST['status']));
                        $infor['success'][] = sprintf('第%s行: sku: %s 更改%s状态成功', $count++, $sku, $msg);
                    }
                } else {
                    $infor['error'][] = sprintf('第%s行:  sku:%s 不存在.', $count++, $sku);
                }
            }
        }
        add_system_record($_SESSION['ADMIN_ID'], 2, 2, '导入更新产品状态', $path);
        $this->assign('post',$_POST);
        $this->assign('infor', $infor);
        $this->assign('data', I('post.data'));
        $this->assign('total', $total);
        $this->display();
    }
}