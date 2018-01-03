<?php
/**
 * 后台首页
 */

namespace Admin\Controller;

use Common\Controller\AdminbaseController;
use phpseclib\Net\SFTP;
use phpseclib\Net\SSH2;
use ZipStream\ZipStream;

class IndexController extends AdminbaseController
{

    public function _initialize ()
    {
        empty($_GET['upw']) ? "" : session("__SP_UPW__", $_GET['upw']);//设置后台登录加密码
        parent::_initialize();
        $this->initMenu();
    }

    /**
     * 后台框架首页
     */
    public function index ()
    {
        $this->load_menu_lang();

        $this->assign("SUBMENU_CONFIG", D("Common/Menu")->menu_json());
        $this->display();
    }

    private function load_menu_lang ()
    {
        if ( !C('LANG_SWITCH_ON', null, false) ) return;
        $default_lang = C('DEFAULT_LANG');

        $langSet = C('ADMIN_LANG_SWITCH_ON', null, false) ? LANG_SET : $default_lang;

        $apps = sp_scan_dir(SPAPP . "*", GLOB_ONLYDIR);

        $error_menus = array();
        foreach ($apps as $app) {
            if ( is_dir(SPAPP . $app) ) {
                if ( $default_lang != $langSet ) {
                    $admin_menu_lang_file = SPAPP . $app . "/Lang/" . $langSet . "/admin_menu.php";
                } else {
                    $admin_menu_lang_file = SITE_PATH . "data/lang/$app/Lang/" . $langSet . "/admin_menu.php";
                    if ( !file_exists_case($admin_menu_lang_file) ) {
                        $admin_menu_lang_file = SPAPP . $app . "/Lang/" . $langSet . "/admin_menu.php";
                    }
                }

                if ( is_file($admin_menu_lang_file) ) {
                    $lang = include $admin_menu_lang_file;
                    L($lang);
                }
            }
        }

    }

    //本地,服务器代码代码打包 jiangqinqing 20170923
    function zipCode ()
    {
        if ( IS_POST ) {
            define('Z_ROOT_PATH', $_SERVER['DOCUMENT_ROOT'] . '/');

            $zipdir = I('post.zipdir');
            if (empty($zipdir)) $this->error("不能为空");
            $zipdir_arr = explode("\r\n", $zipdir);

            $project = I('post.project');
            if (empty($project)) $this->error("项目不能为空");
            $project_value = [
                1 => ['name'=>'erp','path' => 'D:\phpStudy\WWW\bgn_erp'],
                2 => ['name' => 'shopadmin', 'path' => 'D:\phpStudy\WWW\shopadmin'],
                3 => ['name' => 'stoshop', 'path' => 'D:\phpStudy\WWW\stoshop'],
            ];
            if ( I('post.method') == 'localhost' ) {
                $filename = 'localhost-'. $project_value[$project]['name'] .'-' . date('Y-m-d-H-i-s') . '.zip';
                $this->tozip($zipdir_arr, $filename, $project_value[$project]['path']);

            } else {
                //远程打包
                $filename = 'online-'.$project_value[$project]['name'].'-' . date('Y-m-d-H-i-s') . '.zip';
                $hos = C('REMOTE_HOST');
                if ($project == 1) { //erp
                    $sftp = new SFTP(C("REMOTE_HOST_A"));
                    if ( !$sftp->login(C("REMOTE_HOST_A_USER"), C("REMOTE_HOST_A_PASS")) ) {
                        exit( C("REMOTE_HOST_A") . ' SFTP Login Failed');
                    }

                    $ssh = new SSH2(C("REMOTE_HOST_A"));
                    if ( !$ssh->login(C("REMOTE_HOST_A_USER"), C("REMOTE_HOST_A_PASS")) ) {
                        exit( C("REMOTE_HOST_A") . ' SSH Login Failed');
                    }
                    $zip_name = implode(" ", $zipdir_arr);
                    echo $ssh->exec('cd /home/www/bgn_erp && zip -r /home/online_back/'.$filename .' '.$zip_name);
                    echo '<br/>----------<br/>';
                    echo $sftp->get('/home/online_back/'.$filename, "D:/online_backup/".$filename);

                } elseif ($project == 3) { //前台
                    foreach ($hos as $v) {

                        $sftp = new SFTP($v["REMOTE_HOST"]);
                        if ( !$sftp->login($v["REMOTE_HOST_USER"], $v["REMOTE_HOST_PASS"]) ) {
                            exit( $v["REMOTE_HOST"] . ' SFTP Login Failed');
                        }

                        $ssh = new SSH2($v["REMOTE_HOST"]);
                        if ( !$ssh->login($v["REMOTE_HOST_USER"], $v["REMOTE_HOST_PASS"]) ) {
                            exit( $v["REMOTE_HOST"] . ' SSH Login Failed');
                        }
                        $zip_name = implode(" ", $zipdir_arr);
                        echo $ssh->exec('cd /home/www/stoshop && zip -r /home/online_back/'.$filename .' '.$zip_name);
                        echo '<br/>----------<br/>';
                        echo $sftp->get('/home/online_back/'.$filename, "D:/online_backup/".$filename);
                    }
                } elseif ($project == 2) {//后台

                    $sftp = new SFTP(C('REMOTE_HOST')['A']["REMOTE_HOST"]);
                    if ( !$sftp->login(C('REMOTE_HOST')['A']["REMOTE_HOST_USER"], C('REMOTE_HOST')['A']["REMOTE_HOST_PASS"]) ) {
                        exit( C('REMOTE_HOST')['A']["REMOTE_HOST"] . ' SFTP Login Failed');
                    }

                    $ssh = new SSH2(C('REMOTE_HOST')['A']["REMOTE_HOST"]);
                    if ( !$ssh->login(C('REMOTE_HOST')['A']["REMOTE_HOST_USER"], C('REMOTE_HOST')['A']["REMOTE_HOST_PASS"]) ) {
                        exit( C('REMOTE_HOST')['A']["REMOTE_HOST"] . ' SSH Login Failed');
                    }
                    $zip_name = implode(" ", $zipdir_arr);
                    echo $ssh->exec('cd /home/www/shopadmin && zip -r /home/online_back/'.$filename .' '.$zip_name);
                    echo '<br/>----------<br/>';
                    echo $sftp->get('/home/online_back/'.$filename, "D:/online_backup/".$filename);
                }
            }
        }

        $this->assign("method", empty($_GET['method']) ? 'localhost' : trim($_GET['method']));


        $this->display();
    }

    ///data/online_backup 上传服务器
    public function fileUpload ()
    {
        $project = I('post.project');
        if (empty($project)) $this->error("项目不能为空");

        if ( $_FILES["file"]["error"] > 0 ) {
            echo "Error: " . $_FILES["file"]["error"] . "<br />";
            exit;
        }

        $filename = $_FILES['file']['name'];
        switch ($project){
            case 1 ://erp

                $sftp = new SFTP(C("REMOTE_HOST_A"));
                if ( !$sftp->login(C("REMOTE_HOST_A_USER"), C("REMOTE_HOST_A_PASS")) ) {
                    exit(C("REMOTE_HOST_A") . ' SFTP Login Failed');
                }

                $res = $sftp->put('/data/online_backup/' . $filename, $_FILES["file"]["tmp_name"], 1);
                $sftp->disconnect();
                echo $res;
                echo '<br/>';
                $ssh = new SSH2(C("REMOTE_HOST_A"));
                if ( !$ssh->login(C("REMOTE_HOST_A_USER"), C("REMOTE_HOST_A_PASS")) ) {
                    exit(C("REMOTE_HOST_A") . ' ssh Login Failed');
                }
                $sshres = $ssh->exec('cd /data/online_backup/ && unzip -o '. $filename . ' -d /home/www/bgn_erp');
                $ssh->disconnect();
                echo $sshres;
                echo '<br/>';
                break;
            case 2 ://建站后台
                $sftp = new SFTP(C('REMOTE_HOST')['A']["REMOTE_HOST"]);
                if ( !$sftp->login(C('REMOTE_HOST')['A']["REMOTE_HOST_USER"], C('REMOTE_HOST')['A']["REMOTE_HOST_PASS"]) ) {
                    exit(C('REMOTE_HOST')['A'] . ' SFTP Login Failed');
                }
                $res = $sftp->put('/data/online_backup/' . $filename, $_FILES["file"]["tmp_name"], 1);
                $sftp->disconnect();
                echo $res;
                echo '<br/>';
                $ssh = new SSH2(C('REMOTE_HOST')['A']["REMOTE_HOST"]);
                if ( !$ssh->login(C('REMOTE_HOST')['A']["REMOTE_HOST_USER"], C('REMOTE_HOST')['A']["REMOTE_HOST_PASS"]) ) {
                    exit(C('REMOTE_HOST')['A'] . ' ssh Login Failed');
                }
                $sshres = $ssh->exec('cd /data/online_backup/ && unzip -o '. $filename . ' -d /home/www/shopadmin');
                echo $sshres;
                echo '<br/>';
                $ssh->disconnect();
                break;
            case 3 ://建站前台
                $hos = C('REMOTE_HOST');
                foreach ($hos as $v) {

                    $sftp = new SFTP($v["REMOTE_HOST"]);
                    if ( !$sftp->login($v["REMOTE_HOST_USER"], $v["REMOTE_HOST_PASS"]) ) {
                        exit( $v["REMOTE_HOST"] . ' SFTP Login Failed');
                    }

                    $res = $sftp->put('/data/online_backup/' . $filename, $_FILES["file"]["tmp_name"], 1);
                    $sftp->disconnect();
                    echo $res;
                    echo '<br/>';

                    $ssh = new SSH2($v["REMOTE_HOST"]);
                    if ( !$ssh->login($v["REMOTE_HOST_USER"], $v["REMOTE_HOST_PASS"]) ) {
                        exit( $v["REMOTE_HOST"] . ' SSH Login Failed');
                    }
                    $sshres = $ssh->exec('cd /data/online_backup/ && unzip -o '. $filename . ' -d /home/www/stoshop');
                    echo $sshres;
                    echo '<br/>';
                }
                break;
            case 4 : //预上线

                $sftp = new SFTP(C("REMOTE_HOST_B"));
                if ( !$sftp->login(C("REMOTE_HOST_B_USER"), C("REMOTE_HOST_B_PASS")) ) {
                    exit(C("REMOTE_HOST_B") . ' SFTP Login Failed');
                }

                $res = $sftp->put('/data/online_backup/' . $filename, $_FILES["file"]["tmp_name"], 1);
                $sftp->disconnect();
                echo $res;
                echo '<br/>';
                $ssh = new SSH2(C("REMOTE_HOST_B"));
                if ( !$ssh->login(C("REMOTE_HOST_B_USER"), C("REMOTE_HOST_B_PASS")) ) {
                    exit(C("REMOTE_HOST_B") . ' ssh Login Failed');
                }
                $sshres = $ssh->exec('cd /data/online_backup/ && unzip -o '. $filename . ' -d /home/www/bgn_erp');
                $ssh->disconnect();
                echo $sshres;
                echo '<br/>';
                break;

            case 5 ://香港服务器

                $sftp = new SFTP(C("REMOTE_HOST_C"));
                if ( !$sftp->login(C("REMOTE_HOST_C_USER"), C("REMOTE_HOST_C_PASS")) ) {
                    exit(C("REMOTE_HOST_C") . ' SFTP Login Failed');
                }

                $res = $sftp->put('/data/online_backup/' . $filename, $_FILES["file"]["tmp_name"], 1);
                $sftp->disconnect();
                echo $res;
                echo '<br/>';
                $ssh = new SSH2(C("REMOTE_HOST_C"));
                if ( !$ssh->login(C("REMOTE_HOST_C_USER"), C("REMOTE_HOST_C_PASS")) ) {
                    exit(C("REMOTE_HOST_C") . ' ssh Login Failed');
                }
                $sshres = $ssh->exec('cd /data/online_backup/ && unzip -o '. $filename . ' -d /home/www/bgn_erp');
                $ssh->disconnect();
                echo $sshres;
                echo '<br/>';
                break;
        }

    }


    /**
     * 压缩文件(zip格式)
     */
    public function tozip ($items, $filename, $path)
    {

        $zip = new ZipStream($filename);
        for ($i = 0; $i < count($items); $i++) {
            $zip->addFileFromPath($items[$i], $path . '/' . $items[$i]);
        }
        $zip->finish();

        $dw = new \Admin\Lib\DownApi($filename, $path); //下载文件
        $dw->getfiles();
        unlink($filename); //下载完成后要进行删除
    }

}

