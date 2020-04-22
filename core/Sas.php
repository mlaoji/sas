<?php
error_reporting(E_ALL & ~E_NOTICE);

//模式：DEV: 开发模式 TEST: 测试模式 RELEASE: 线上模式
!defined("MODE") && define("MODE", "RELEASE");
!defined("TMP_DIR") && define("TMP_DIR", APPLICATION_DIR.'/tmp');
!defined("CONFIG_DIR") && define("CONFIG_DIR", APPLICATION_DIR.'/config');
!defined("TIMEZONE") && define("TIMEZONE", "Asia/Shanghai");//默认时区

ini_set("date.timezone", TIMEZONE);

if("DEV" == MODE) {
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
    set_time_limit(0);
    return;
}

$sas = Application::singleton();
try{
    $sas->run();
}catch(Exception $e){
    $error = $e->getMessage();
    if("DEV" == MODE || "TEST" == MODE) {
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
    if("DEV" != MODE && !defined("NO_CACHED") && is_file(TMP_DIR . '/lock') && is_file(TMP_DIR.'/sas.php')) {
        return include(TMP_DIR.'/sas.php');
    } else {
        //避免在多次调用import中途, 由于其他脚本优先生成sas.php, 造成"重复定义" 的错误
        !defined("NO_CACHED") && define("NO_CACHED", true);

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
