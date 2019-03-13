<?php
error_reporting(E_ALL & ~E_NOTICE);

!defined("DEV_MODE") && define("DEV_MODE", false);//开发模式
!defined("TEST_MODE") && define("TEST_MODE", false);//测试模式
!defined("DIR_FS_TMP") && define("DIR_FS_TMP", APPLICATION_DIR.'/tmp');
!defined("CONFIG_DIR") && define("CONFIG_DIR", APPLICATION_DIR.'/config');
!defined("TIMEZONE") && define("TIMEZONE", "Asia/shanghai");//默认时区

ini_set("date.timezone", TIMEZONE);

if(DEV_MODE) {
    ini_set('display_errors',1);
}

$_sas_inc = "";

//用import 函数导入框架依赖文件
import(CONFIG_DIR . "/App.conf.php");
import(SAS_DIR . "/config/Const.conf.php");
import(SAS_DIR . "/base/Application.php");
import(SAS_DIR . "/base/IController.php");
import(SAS_DIR . "/base/Controller.php");
import(SAS_DIR . "/base/Exception.php");

//将上面import过的文件生成一个缓存文件 
genCache();

if(PHP_SAPI === 'cli') {
    return;
}

$sas = Application::singleton();
try{
    $sas->run();
}catch(Exception $e){
    $error = $e->getMessage();
    if(DEV_MODE || TEST_MODE) {
        echo $error;
    } else {
        echo "Error happened!  Please view the system log!";
        Logger::warn($error);
    }
}

function import($file = null, $depend = true) {/*{{{*/
    if(defined('IMPORT_CACHED')) {
        return false;
    }
	
    //删除 lock 文件会触发更新缓存
    if(!DEV_MODE && is_file(DIR_FS_TMP . '/lock') && is_file(DIR_FS_TMP.'/sas.php')) {
        return include(DIR_FS_TMP.'/sas.php');
    } else {
        global $_sas_inc;

        if($file && $depend) {
            if(is_file($file)) {
                $c = trim(php_strip_whitespace($file));
                if(false !== ($i=stripos($c, '<?php'))) {
                    $c = substr($c, $i+5);
                }

                if('?>' == substr($c, -2)) {
                    $c = substr($c, 0, -2);
                } else {
                    $i=strrpos($c, '}');
                    $j=strrpos($c, ';');
                    if(!$i) $i=-1;
                    if(!$j) $j=-1;
                    $k = max($i,$j);
                    if($k>0) {
                        $c = substr($c, 0, $k+1);
                    }
                }
                $_sas_inc .= $c."\n"; 
                include($file);
            }
        }
    }   
}/*}}}*/

function genCache() {/*{{{*/
    if(defined('IMPORT_CACHED')) {
        return false;
    }

    global $_sas_inc;
    
    if("" != $_sas_inc) {
        if(is_file(APPLICATION_DIR . '/vendor/autoload.php')) {
            $_sas_inc .= "include(APPLICATION_DIR . '/vendor/autoload.php');";
        }

        Import::genCache($_sas_inc);
        unset($_sas_inc);
    }   

    if(is_file(APPLICATION_DIR . '/vendor/autoload.php')) {
        include(APPLICATION_DIR . '/vendor/autoload.php');
    }
}/*}}}*/
