<?php
namespace Product\Controller;
use Common\Controller\AdminbaseController;

class ChangeController extends AdminbaseController{
    public function hide_product_sku(){
        $hide_product = array(6,7,8,14,15,18,20,21,22,31,32,35,38,40,41,46,47,48,50,51,52,53,54,57,58,61,62,63,65,66,67,68,69,70,73,75,77,79,80,81,83,84,86,87,89,90,92,93,94,95,97,98,99,102,103,104,106,107,112,113,114,115,116,117,118,122,124,126,127,128,129,130,132,135,136,137,138,140,141,142,146,147,148,150,151,152,153,155,156,157,160,161,162,163,164,165,167,168,169,170,171,174,175,176,177,179,181,183,186,187,189,191,193,197,199,200,202,203,205,206,207,208,209,210,211,212,213,214,215,216,217,221,222,223,225,226,227,230,233,234,236,246,247,248,249,250,251,252,255,259,260,261,262,265,266,268,269,270,273,274,276,277,278,279,281,282,283,284,285,286,295,296,297,298,300,302,305,311,312,313,314,316,322,324,326,329,334,336,337,338,341,342,343,345,347,348,350,352,353,354,355,356,357,358,360,361,362,364,365,366,367,368,371,373,374,375,376,377,379,380,384,386,387,388,389,390,391,392,393,395,396,398,400,401,402,407,409,410,411,415,416,417,419,420,421,422,423,427,428,429,431,432,434,435,436,438,441,445,448,449,453,454,455,457,459,460,465,466,467,468,476,477,481,482,485,490,493,497,500,502,504,506,512,517,518,519,524,525,528,530,531,532,533,536,537,539,540,547,548,549,550,551,552,553,554,555,558,559,560,562,563,564,567,570,571,575,576,577,580,582,584,585,586,587,588,589,590,591,592,593,597,600,601,603,604,605,608,610,611,613,615,620,624,625,626,627,628,629,631,633,634,635,636,639,640,641,644,645,647,649,650,652,654,656,657,659,660,661,662,663,664,665,666,668,669,671,673,674,677,678,679,681,682,683,684,685,686,688,689,690,691,692,696,698,699,700,702,703,706,711,723,728,733,734,736,738,740,747,749,750,751,753,754,755,764,765,766,767,768,769,771,773,775,776,777,778,779,781,782,785,786,789,791,792,793,794,795,798,799,802,808,809,810,812,813,814,816,817,821,827,830,831,832,833,834,843,844,850,851,852,853,855,857,858,861,863,867,868,871,872,873,874,878,879,883,884,889,891,895,896,897,898,899,901,902,903,904,906,907,908,909,911,912,914,916,917,918,919,920,921,922,923,924,925,926,927,928,929,930,931,932,934,935,937,938,939,940,941,942,943,944,945,946,948,949,950,951,952,953,954,955,956,957,958,959,960,962,963,964,966,967,968,969,970,971,972,973,974,977,978,979,980,981,984,985,986,987,988,989,990,991,993,994,995,996,997,1000,1001,1004,1005,1006,1009,1010,1012,1013,1015,1016,1018,1019,1021,1022,1024,1025,1027,1028,1030,1032,1033,1034,1035,1036,1037,1039,1041,1042,1046,1047,1048,1049,1050,1051,1052,1053,1054,1057,1058,1059,1060,1061,1062,1063,1064,1065,1066,1067,1070,1071,1072,1073,1074,1077,1078,1079,1080,1081,1082,1083,1084,1085,1086,1087,1090,1091,1092,1093,1094,1095,1096,1097,1098,1099,1100,1101,1102,1103,1104,1105,1107,1108,1109,1110,1111,1112,1113,1114,1116,1118,1119,1120,1123,1124,1125,1126,1128,1130,1131,1133,1134,1135,1136,1139,1141,1142,1143,1144,1145,1147,1148,1149,1150,1152,1153,1154,1156,1157,1159,1161,1162,1163,1164,1166,1167,1169,1170,1171,1172,1173,1174,1176,1179,1180,1181,1182,1183,1184,1185,1186,1192,1193,1194,1196,1197,1198,1199,1200,1201,1205,1206,1209,1210,1211,1212,1213,1214,1215,1217,1218,1219,1220,1221,1222,1223,1224,1228,1230,1233,1236,1237,1238,1240,1241,1244,1247,1248,1249,1250,1252,1253,1254,1255,1256,1257,1262,1263,1264,1265,1267,1269,1271,1272,1274,1275,1276,1277,1278,1279,1280,1282,1284,1285,1286,1288,1291,1293,1294,1295,1296,1297,1299,1300,1301,1302,1303,1304,1305,1306,1307,1308,1311,1312,1313,1314,1316,1317,1318,1319,1320,1321,1322,1323,1324,1325,1330,1331,1332,1333,1334,1335,1336,1337,1338,1339,1341,1342,1343,1344,1345,1346,1347,1348,1349,1350,1351,1352,1353,1354,1355,1356,1357,1358,1359,1360,1361,1362,1363,1364,1365,1366,1367,1368,1369,1370,1371,1372,1373,1374,1375,1376,1378,1379,1380,1381,1382,1383,1384,1385,1386,1387,1389,1390,1391,1392,1393,1394,1395,1396,1397,1398,1399,1400,1401,1402,1403,1406,1407,1408,1409,1410,1411,1412,1413,1414,1415,1416,1417,1418,1419,1420,1421,1422,1423,1424,1425,1426,1427,1428,1429,1430,1431,1432,1433,1436,1437,1438,1439,1441,1442,1443,1444,1445,1447,1448,1449,1450,1451,1452,1453,1454,1455,1456,1457,1458,1459,1460,1461,1462,1463,1464,1465,1466,1467,1471,1472,1473,1479,1480,1481,1482,1483,1484,1485,1486,1487,1488,1489,1490,1492,1493,1494,1495,1496,1497,1498,1504,1505,1506,1507,1508,1509,1511,1512,1513,1514,1515,1522,1524,1525,1526,1527,1528,1532,1533,1534,1535,1536,1537,1538,1542,1543,1544,1545,1546,1547,1548,1549,1550,1551,1552,1553,1556,1557,1558,1559,1560,1562,1563,1564,1565,1566,1567,1568,1569,1570,1571,1572,1573,1575,1576,1577,1578,1579,1580,1584,1586,1587,1588,1589,1591,1592,1596,1597,1601,1602,1603,1604,1605,1606,1607,1610,1611,1612,1613,1614,1617,1618,1620,1624,1625,1626,1627,1629,1630,1631,1633,1638,1639,1640,1641,1642,1643,1646,1647,1649,1650,1654,1655,1656,1658,1659,1660,1663,1664,1665,1666,1668,1669,1670,1671,1672,1673,1677,1678,1680,1681,1682,1683,1684,1685,1686,1689,1692,1693,1695,1696,1698,1699,1700,1701,1702,1703,1704,1706,1708,1710,1712,1713,1714,1715,1717,1718,1721,1723,1727,1728,1729,1730,1731,1732,1733,1734,1735,1744,1745,1746,1748,1749,1750,1753,1754,1755,1756,1757,1758,1759,1761,1764,1766,1768,1769,1770,1771,1772,1773,1774,1775,1776,1777,1778,1779,1784,1785,1786,1787,1789,1790,1791,1792,1794,1796,1797,1799,1800,1801,1802,1805,1806,1807,1808,1810,1811,1812,1813,1814,1815,1816,1817,1821,1823,1824,1825,1827,1828,1831,1832,1834,1836,1838,1840,1843,1845,1848,1849,1852,1854,1855,1856,1859,1860,1863,1864,1866,1868,1869,1870,1871,1873,1874,1875,1878,1880,1882,1886,1887,1888,1889,1890,1891,1892,1893,1894,1897,1900,1901,1902,1903,1905,1907,1909,1910,1911,1913,1914,1916,1919,1920,1922,1923,1926,1927,1929,1930,1931,1932,1933,1935,1936,1939,1940,1941,1942,1947,1948,1949,1950,1954,1955,1956,1958,1959,1960,1961,1964,1966,1969,1970,1971,1973,1977,1978,1979,1980,1981,1984,1985,1987,1989,1990,1992,1993,1996,1997,2002,2003,2005,2006,2008,2010,2011,2013,2014,2015,2016,2017,2018,2019,2020,2021,2022,2023,2024,2025,2029,2031,2035,2041,2043,2045,2047,2057,2061,2063,2065,2067,2069,2071,2073,2079,2081,2083,2085,2089,2095,2103,2107,2109,2111,2113,2117,2121,2123,2125,2135,2137,2143,2145,2147,2155,2163,2165,2187,2189,2191,2193,2199,2201,2209,2211,2215,2217,2219,2221,2223,2227,2229,2235,2237,2239,2241,2253,2259,2261,2263,2265,2267,2269,2271,2275,2277,2291,2293,2297,2301,2303,2307,2313,2315,2319,2321,2323,2325,2327,2329,2331,2333,2335,2337,2339,2341,2345,2349,2353,2361,2367,2381,2383,2387,2389,2395,2397,2401,2403,2405,2409,2413,2415,2419,2423,2431,2433,2435,2439,2443,2447,2449,2455,2457,2461,2463,2469,2471,2481,2487,2491,2497,2499,2501,2505,2513,2517,2523,2531,2535,2537,2539,2551,2555,2569,2581,2587,2589,2591,2593,2599,2607,2609,2611,2617,2623,2625,2631,2641,2647,2651,2653,2655,2665,2671,2673,2681,2689,2691,2703,2707,2709,2715,2729,2743,2763,2769,2777,3079,3645,3661,3829,3895,3929,3931,4001,4003,4005,4047,4163,4275,4289,4307,4331,4367,4377,4421,4499,4503,4591,4593,4691,4693,4711,4713,4715,4765,4787,4809,4841,4851,4875,4881,4889,4895,4903,4971,5007,5047,5085,5087,5089,5091,5105,5107,5129,5149,5161,5163,5171,5179,5181,5187,5193,5223,5225,5231,5235,5239,5241,5243,5261,5273,5281,5329,5335,5393,5397,5421,5453,5471,5495,5499,5503,5511,5517,5535,5545,5547,5553,5565,5571,5581,5595,5597,5605,5633,5649,5655,5661,5683,5693,5695,5707,5727,5739,5753,5759,5781,5807,5817,5827,5835,5841,5851,5877,5899,5901,5911,5913,5961,5963,5965,5967,5975,6037,6043,6047,6049,6069,6073,6103,6119,6123,6137,6147);
        $waite_product = array(5,12,13,19,23,24,26,30,37,55,56,74,76,78,85,88,96,100,110,111,119,120,125,139,145,154,158,172,173,184,188,194,201,204,220,228,239,245,253,258,299,301,327,333,340,349,363,370,372,378,381,385,397,403,404,424,425,426,430,433,440,442,444,447,450,451,452,456,462,464,471,478,480,483,491,492,494,498,499,503,507,508,510,516,522,523,527,529,535,542,556,561,569,572,574,581,594,595,606,607,609,612,614,616,621,622,630,632,637,638,643,646,670,672,675,687,694,701,710,743,759,760,770,790,797,839,854,859,876,877,880,881,882,885,886,887,890,892,893,910,933,947,975,976,982,983,992,998,1002,1003,1011,1020,1023,1026,1031,1043,1044,1045,1055,1056,1068,1088,1106,1117,1122,1138,1140,1168,1175,1191,1195,1226,1229,1232,1234,1239,1245,1251,1259,1266,1281,1283,1287,1298,1309,1315,1340,1377,1404,1440,1446,1476,1510,1518,1555,1574,1581,1583,1585,1599,1608,1616,1619,1634,1637,1644,1651,1653,1661,1675,1676,1690,1694,1709,1716,1724,1736,1737,1739,1760,1763,1798,1803,1809,1820,1829,1833,1835,1842,1844,1846,1850,1865,1876,1879,1884,1906,1924,1925,1943,1944,1957,1986,1988,1998,2000,2027,2049,2051,2053,2055,2093,2097,2099,2105,2127,2129,2133,2139,2141,2169,2167,2171,2173,2175,2177,2179,2205,2231,2233,2245,2247,2251,2255,2281,2283,2305,2343,2351,2369,2373,2377,2379,2399,2421,2425,2427,2437,2459,2473,2475,2477,2483,2493,2507,2509,2521,2525,2547,2549,2553,2559,2563,2565,2567,2575,2577,2579,2585,2603,2619,2621,2629,2643,2645,2649,2657,2663,2667,2675,2679,2683,2685,2697,2701,2705,2713,2717,2719,2723,2725,2727,2733,2737,2739,2745,2751,2753,2755,2757,2761,2765,2767,2773,2775,2779,2783,2785,2787,2789,2791,2797,2801,2803,2805,2807,2809,2811,2817,2819,2821,2823,2825,2827,2833,2853,2857,2861,2863,2869,2871,2889,2893,2895,2897,2901,2903,2905,2907,2913,2915,2917,2923,2925,2927,2929,2931,2933,2935,2937,2941,2943,2945,2947,2949,2951,2963,2965,2967,2971,2973,2975,2977,2981,3009,3025,3029,3031,3035,3041,3043,3045,3047,3049,3051,3055,3057,3061,3067,3069,3073,3075,3077,3085,3087,3089,3095,3099,3101,3103,3105,3107,3109,3111,3117,3121,3125,3127,3129,3131,3133,3137,3139,3143,3153,3155,3161,3163,3165,3175,3179,3181,3183,3185,3189,3191,3193,3197,3203,3209,3211,3215,3219,3225,3227,3231,3235,3237,3239,3241,3243,3245,3249,3255,3257,3259,3261,3267,3273,3279,3281,3283,3287,3299,3313,3319,3321,3323,3325,3329,3331,3333,3335,3339,3343,3345,3347,3349,3355,3357,3359,3363,3367,3369,3375,3377,3379,3381,3383,3385,3387,3389,3397,3403,3409,3411,3413,3417,3427,3429,3433,3435,3437,3439,3441,3443,3445,3455,3457,3469,3473,3477,3479,3493,3495,3497,3499,3503,3507,3511,3519,3521,3523,3525,3529,3531,3533,3535,3559,3577,3587,3591,3597,3607,3609,3613,3619,3625,3627,3629,3631,3633,3635,3637,3641,3643,3647,3649,3655,3687,3693,3699,3701,3709,3711,3727,3747,3753,3775,3793,3803,3819,3831,3833,3851,3863,3865,3869,3871,3873,3877,3891,3893,3911,3913,3915,3937,3941,3943,3945,3949,3965,3967,3969,3975,3977,4009,4011,4013,4021,4029,4043,4055,4079,4087,4091,4103,4113,4117,4119,4137,4139,4169,4171,4173,4175,4195,4203,4207,4217,4247,4249,4259,4265,4269,4283,4287,4333,4335,4343,4345,4347,4361,4383,4401,4431,4437,4441,4445,4449,4459,4461,4469,4533,4567,4587,4639,4771,4797,4865,4899,5165,5183,5299,5301,5323,5415,5573,5631,5583,5713,5777,5839,5867,5895,5935,5977,5987,6001,6003,6023,6029,6033,6039,6053,6055,6065,6067,6089,6107,6139,6141);
        $products    = D("Product/Product")->where(array('id_product'=>array('IN',$hide_product)))->select();
        foreach($products as $pro){
            D("Product/Product")->where(array('id_product'=>$pro['id_product']))
                ->save(array('status'=>0));
            D("Product/ProductSku")->where(array('id_product'=>$pro['id_product']))->save(array('status'=>0));
        }
        /** @var \Order\Model\OrderModel $order_model */
        $order_model = D("Order/Order");
        $w_product    = D("Product/Product")->where(array('id_product'=>array('IN',$waite_product)))->select();
        foreach($w_product as $w_pro){
            $tow_month     = date('Y-m-d H:i:s',strtotime('-15 day'));
            $order_where   = array('oi.id_product'=>$w_pro['id_product'] , 'o.created_at'=>array('GT',$tow_month));
            $order_product = $order_model->alias('o')->field('oi.id_product')
                ->join('__ORDER_ITEM__ oi ON (o.id_order = oi.id_order)', 'LEFT')
                ->where($order_where)->group("oi.id_product")->find();

            if(!$order_product){
                echo $w_pro['id_product'].'<br />';
                D("Product/Product")->where(array('id_product'=>$w_pro['id_product']))
                    ->save(array('status'=>0));
                D("Product/ProductSku")->where(array('id_product'=>$w_pro['id_product']))->save(array('status'=>0));
            }else{
                echo '存在'.$w_pro['id_product'].'<br />';
            }
        }
    }
    public function select_domian_product(){
        /** @var \Order\Model\OrderModel $order_model */
        $order_model = D("Order/Order");
        $product_ids = array();
        set_time_limit(0);
        ini_set('memory_limit', '-1');

        vendor("PHPExcel.PHPExcel");
        vendor("PHPExcel.PHPExcel.IOFactory");
        vendor("PHPExcel.PHPExcel.Style.NumberFormat");
        $excel = new \PHPExcel();
        $columns = array(
            '产品ID', '内部名', '2个月内是否有单','产品建立时间'
        );
        $j = 65;
        foreach ($columns as $col) {
            $excel->getActiveSheet()->setCellValue(chr($j) . '1', $col);
            ++$j;
        }
        $idx = 2;
        $products    = D("Product/Product")->where(array('id_product'=>array('IN',$product_ids)))->select();
        foreach($products as $pro){
            $tow_month = date('Y-m-d H:i:s',strtotime('-2 month'));
            $order_where = array('oi.id_product'=>$pro['id_product'] , 'o.created_at'=>array('GT',$tow_month));

            $order_product = $order_model->alias('o')->field('oi.id_product')
                ->join('__ORDER_ITEM__ oi ON (o.id_order = oi.id_order)', 'LEFT')
                ->where($order_where)->group("oi.id_product")->find();
            $is_order = $order_product?'Yes':'No';
            $out_data = array(
                $pro['id_product'],$pro['inner_name'],$is_order,date('Y-m-d',strtotime($pro['created_at']))
            );
            $j = 65;
            foreach ($out_data as $col) {
                $excel->getActiveSheet()->setCellValueExplicit(chr($j) . $idx, $col);
                ++$j;
            }
            ++$idx;

        }
        $excel->getActiveSheet()->setTitle(date('Y-m-d') . '产品.xlsx');
        $excel->setActiveSheetIndex(0);
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . date('Y-m-d') . '产品.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        $writer->save('php://output');
        exit();
        //$domains = D("Common/Domain")->where(array('name'=>array('IN',$config)))->select();
        /** @var \Order\Model\OrderModel $order_model */
        /*$order_model = D("Order/Order");
        $temp_product = array();
        $temp_dom_pro = array();
        foreach($domains as $d){
            $order_where = array('id_domain'=>$d['id_domain']);
            $order_product = $order_model->alias('o')->field('oi.id_product')
                ->join('__ORDER_ITEM__ oi ON (o.id_order = oi.id_order)', 'LEFT')
                ->where($order_where)->group("oi.id_product")->select();

            foreach($order_product as $ord_pro){
                $id_product = $ord_pro['id_product'];
                $temp_product[$id_product] = $id_product;
                $temp_dom_pro[$d['name']][$id_product] = $id_product;
            }
            $temp_product = array_merge($temp_product,array_column($order_product,'id_product'));
        }*/

    }

    /**
     * 设置SKU 条形码
     */
    public function add_sku_barcode(){
        set_time_limit(0);
        /** @var  \Product\Model\ProductSkuModel $sku_model */
        $sku_model = D('Product/ProductSku');
        $sku_list  = $sku_model->where('barcode>2061462')->select();
        $pro_model = D("Common/Product");
        foreach($sku_list as $sku){
            //sleep(1);temp_barcode
            $barcode = D("Common/TempBarcode")->data(array('sku_id'=>$sku['id_product_sku']))->add();
            $update_data = array('barcode'=>$barcode);
            $find_barcode = $sku_model->where($update_data)->find();
            if(!$find_barcode){
                $sku_model->where(array('id_product_sku'=>$sku['id_product_sku']))->save($update_data);
            }
        }

        /*$sku_model = D('Product/ProductSku');
        $sku_list  = $sku_model->where('`barcode` is null or `barcode`=""')->select();
        $pro_model = D("Common/Product");
        foreach($sku_list as $sku){
            //sleep(1);
            $id_product  = $sku['id_product'];
            $product     = $pro_model->cache(true,600)->find($id_product);
            $get_sku_num = (int)str_replace('ST','',$product['model']);
            $sku_len     = strlen($get_sku_num);
            $s_len       = 9-$sku_len;
            $time        = str_replace('.','',microtime(true));
            $barcode     = substr($time,-$s_len);
            $update_data = array('barcode'=>$get_sku_num.$barcode);
            $find_barcode = $sku_model->where($update_data)->find();
            if(!$find_barcode){
                $sku_model->where(array('id_product_sku'=>$sku['id_product_sku']))->save($update_data);
            }
        }*/

        echo 'OK';
    }
    public function barcode_repeat(){
        set_time_limit(0);
        /** @var  \Product\Model\ProductSkuModel $sku_model */
        $sku_model = D('Product/ProductSku');
        $sku_list  = $sku_model->field('id_product_sku,barcode,COUNT(*) as get_count')
                //->where('`barcode` is null or `barcode`=""')
                ->group('barcode HAVING get_count > 1')
                ->select();
        $pro_model = D("Common/Product");
        foreach($sku_list as $sku){
            //sleep(1);
            $id_product  = $sku['id_product'];
            $product     = $pro_model->cache(true,600)->find($id_product);
            $get_sku_num = (int)str_replace('ST','',$product['model']);
            $sku_len     = strlen($get_sku_num);
            $s_len       = 9-$sku_len;
            $time        = str_replace('.','',microtime(true));
            $barcode     = substr($time,-$s_len);
            $update_data = array('barcode'=>$get_sku_num.$barcode);
            $find_barcode = $sku_model->where($update_data)->find();
            if(!$find_barcode){
                echo $get_sku_num.$barcode.'<br />';
                $sku_model->where(array('id_product_sku'=>$sku['id_product_sku']))->save($update_data);
            }
        }
        echo 'OK';
    }
    public function  change_product($set_data){
        $old_id_department = $set_data['old_id_department'];//2
        $new_id_department = $set_data['new_id_department'];//9
        $product_ids       = $set_data['product_ids'];//
        $set_start_id      = $set_data['set_start_id'];
//        if(!isset($_GET['action'])){
//            echo $old_id_department.'==='.$new_id_department.'<br />';
//            print_r($product_ids);
//            exit();
//        }
        /** @var \Product\Model\ProductModel $product */
        $product       = D('Product/Product');
        $pro_data      = $product->where(array('id_product'=>array('IN',$product_ids)))->select();
        foreach($pro_data as $item){
            $old_sku        = 'ST'.$set_data['old_set_start_id'];
            $new_sku        = 'ST'.$set_start_id;
            $id_product     = $item['id_product'];
            $model          = $item['model'];
//            $inner_name     = $item['inner_name'];
            $all_sku       = D("Common/ProductSku")->where(array('id_product'=>$id_product))->select();
            foreach($all_sku as $sku_list){
                $sku_update = array(
//                    'sku' => str_replace($old_sku,$new_sku,$sku_list['sku']),
//                    'model' => str_replace($old_sku,$new_sku,$sku_list['model']),
                    'id_department' => $new_id_department,
                );
                D("Common/ProductSku")->where(array('id_product_sku'=>$sku_list['id_product_sku']))->save($sku_update);
            }
            $product_update = array(
//                'model' => str_replace($old_sku,$new_sku,$model),
//                'inner_name' => str_replace('A'.$set_data['old_set_start_id'],'A'.$set_start_id,$inner_name),
                'id_department' => $new_id_department,
            );
            $product->where(array('id_product'=>$id_product))->save($product_update);
        }
        echo $set_data['old_set_start_id'].'==>'.$set_start_id.' ID'.$old_id_department.'=='.$new_id_department.'change product OK<br />';
    }

    /**
     * 旧部门产品和订单迁移
     * 注意需要迁移订单时间
     */
    public function change_order(){
        $chang_array = $this->Three_group();
        foreach($chang_array as $single){
            $old_id_department = $single['old_id_department'];
            $new_id_department = $single['new_id_department'];
            $set_start_id      = $single['set_start_id'];
            //处理产品
            $product_ids =  implode(',',$single['product_ids']);
            $pro_data  = array(
                'old_id_department' => $old_id_department,
                'new_id_department' => $new_id_department,
                'product_ids'       => $product_ids,
                'set_start_id'      => $set_start_id,
                'old_set_start_id'  => $single['old_set_start_id'],
            );
//            $this->change_product($pro_data);  //更改产品 SKU 信息

            if(is_array($single['domain'])){
                foreach($single['domain'] as $do){
                    $domain = D('Domain/Domain')->where(array('name'=>$do))->find();
                    if(!$domain){
                        echo $do.' 没有找到<br/>';
                    }else{
                        echo $do.'<br />';
                        $id_domain  = $domain['id_domain'];
                        D('Domain/Domain')->where(array('id_domain'=>$id_domain))->save(array('id_department'=>$new_id_department));
                        $order_where = array(
                            'id_domain' => $id_domain,
                            'created_at' => array('EGT', date('2017-08-01'))//GT 大于6月1号之前的。
                            //------------------------------------注意是否需要修改订单时间------------------------------------------
                        );
                        $select_order = D("Order/Order")->field('id_order,id_department')->where($order_where)->select();
                        foreach($select_order as $order_item){
                            $id_order = $order_item['id_order'];

                            //更新订单产品SKU
//                            $order_item_data = D("Order/OrderItem")->where(array('id_order'=>$id_order))->select();
//                            foreach($order_item_data as $ord_i){
//                                $ord_item_update = array(
//                                    'sku' => str_replace('ST'.$single['old_set_start_id'],'ST'.$set_start_id,$ord_i['sku'])
//                                );
//                                D("Order/OrderItem")->where(array('id_order_item'=>$ord_i['id_order_item']))->save($ord_item_update);
//                            }
                            //更新订单部门
                            D("Order/Order")->where(array('id_order'=>$id_order))->save(array('id_department'=>$new_id_department));
                        }
                    }

                }
            }
            echo $old_id_department.'===>'.$new_id_department.'完成<br /><br />';


        }
    }
    public function Three_group(){
        $chang_array = array(
            array(
                'set_start_id' => 31,//新的部门 订单和产品开始数字
                'old_set_start_id' => 10,//旧的部门 订单和产品开始数字
                'old_id_department' => 19,//旧部门ID
                'new_id_department' => 76,//新部门ID
                'domain' => array(
                    'www.raihy.com'
                ),
                'product_ids' =>array(
                )
            ),
        );
        return $chang_array;
    }
    public function six_group(){
        $domain = D('Domain/Domain')->where(array('id_department'=>6))->select();
        $domain_name = array_column($domain,'name');
        /** @var \Product\Model\ProductModel $product */
        $product       = D('Product/Product');
        $pro_data      = $product->where(array('id_department'=>6))->select();
        $product_ids   = array_column($pro_data,'id_product');
        if($domain_name){
            $chang_array = array(
                array(
                    'set_start_id' => 11,
                    'old_id_department' => 6,
                    'new_id_department' => 21,//8部
                    'domain'  => $domain_name,
                    'product_ids' =>$product_ids
                )
            );
            return $chang_array;
        }
    }

    public function two_group(){
        $chang_array = array(
            array(
                'new_id_users' => 854,//新广告专员ID
                'id_department' => 62,
                'domain' => array(
                    'www.oamuo.com'
                ),
            ),
        );
        return $chang_array;
    }

    public function chang_array(){
        $chang_array = array(
            array(
               'old_id_department' => 1,
               'new_id_department' => 14,//8部
               'domain'  =>array(
                   'www.bdidly.com',
                    'www.qcwqv.com'),
                'product_ids' => array(
                    263,4615,
                    1862
                )
            ),



            array(
                'old_id_department' => 2,
                'new_id_department' => 17,//9部
                'domain'=>array(
                    'www.abtkb.com',
                    'www.cgabu.com'
                ),
                'product_ids' =>array(
                    1499,1500,
                    4059
                )
            ),

            array(
                'old_id_department' => 3,
                'new_id_department' => 19,
                'domain'=>array(
                    'www.wfpil.com',
                    'www.ptufd.com',
                ),
                'product_ids' =>array(
                    710,
                    714,
                    4793
                )
            )
        );
        return $chang_array;
    }

    /**
     * 更换域名对应的广告专员的id
     */
    public function change_aduser(){
        $chang_array = $this->two_group();
        foreach($chang_array as $domain) {
            $new_id_users = $domain['new_id_users'];
            if(is_array($domain['domain'])) {
                foreach($domain['domain'] as $do){
                    $domain = D('Domain/Domain')->where(array('name'=>$do))->find();
                    if(!$domain){
                        echo $do.' 没有找到<br/>';
                    }else{
                        echo $do.'===>'.$new_id_users.'<br />';
                        $id_domain  = $domain['id_domain'];
                        $order_where = array(
                            'id_domain' => $id_domain,
                            'id_department' => $domain['id_department'],
//                            'created_at' => array('GT','2017-06-01 00:00:00'),//GT 大于5月1号之前的。
                            'created_at'=>array(array('EGT', date('2017-07-07')), array('LT', date('2017-07-10')))
                            //------------------------------------注意是否需要修改订单时间------------------------------------------
                        );
                        $select_order = D("Order/Order")->field('id_order,id_department')->where($order_where)->select();
                        foreach($select_order as $order_item){
                            $id_order = $order_item['id_order'];
                            //更新域名用户
                            D("Order/Order")->where(array('id_order'=>$id_order))->save(array('id_users'=>$new_id_users));
                        }
                    }
                }
            }
        }
    }

    public function product_arr() {
        $chang_array = array(
            'set_start_id' => 38,//新的部门 订单和产品开始数字
            'old_set_start_id' => 2,//旧的部门 订单和产品开始数字
            'product_ids' =>array(
                1037
            )
        );
        return $chang_array;
    }

    //修改产品sku
    public function  change_product_sku(){
        $set_data = $this->product_arr();
        $old_id_department = $set_data['old_set_start_id'];//8
        $new_id_department = $set_data['set_start_id'];//13
        $product_ids       = $set_data['product_ids'];//
        /** @var \Product\Model\ProductModel $product */
        $product       = D('Product/Product');
        $pro_data      = $product->where(array('id_product'=>array('IN',$product_ids)))->select();
        foreach($pro_data as $item){
            $id_product     = $item['id_product'];
            $all_sku       = D("Common/ProductSku")->where(array('id_product'=>$id_product))->select();
            foreach($all_sku as $sku_list){
                $sku_update = array(
                    'id_department'=>$new_id_department
                );
                D("Common/ProductSku")->where(array('id_product_sku'=>$sku_list['id_product_sku']))->save($sku_update);
            }
            $product_update = array(
                'id_department' => $new_id_department,
            );
            $product->where(array('id_product'=>$id_product))->save($product_update);
        }
        echo $old_id_department.'==>'.$new_id_department.'change product OK<br />';
    }

    //临时修改结款数据
    public function change_settle() {
        $arr = $this->tracknum_arr();
        $order = M('OrderShipping')->where(array('track_number'=>array('IN',$arr)))->select();
        foreach($order as $key=>$val) {
            $order_id = $val['id_order'];
            $data = array(
                'amount_settlement'=>0,
                'status'=>0
            );
            $res = D('Order/OrderSettlement')->where(array('id_order'=>$order_id))->save($data);
            if($res) {
                echo $order_id.'success<br/>';
            } else {
                echo $order_id.'fail<br/>';
            }
        }
    }

    //修改产品model
    public function change_product_model() {
        $id_department = 38;
        $id_department_new = 17;
        $id_department_old = 2;
        /** @var \Product\Model\ProductModel $product */
        $product       = D('Product/Product');
        $pro_data      = $product->where(array('id_department'=>$id_department))->select();
        foreach($pro_data as $item){
            $old_sku        = 'ST'.$id_department_old;
            $new_sku        = 'ST'.$id_department_new;
            $id_product     = $item['id_product'];
            $model          = $item['model'];
            $product_update = array(
                'model' => str_replace($old_sku,$new_sku,$model),
            );
            $product->where(array('id_product'=>$id_product))->save($product_update);
        }
        echo $id_department_old.'==>'.$id_department.'change product OK<br />';
    }
}