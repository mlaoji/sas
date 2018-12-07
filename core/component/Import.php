<?php
class Import {

    private static $classpaths = array();
    private static $dao_tpl = '<?php 
class DAO__DAO__ extends DAOProxy{
    private static $instance;
    public function __construct($shard_value = null) {
        parent::__construct("__TABLE__", $shard_value);
    } 

    public static function getInstance($shard_value = null) {
        $shard = $shard_value ? $shard_value : 0;
        if(!isset(self::$instance[$shard])) {
            self::$instance[$shard]=new self($shard_value);
        }
        return self::$instance[$shard];
    }
}';

    public static function genCache($_inc) {/*{{{*/
        $_inc .= self::autoLoad();
        return Files::write(DIR_FS_TMP.'/sas.php', '<?php define("IMPORT_CACHED", ' . $_SERVER['REQUEST_TIME'] . ');' . $_inc);
    }/*}}}*/

    public static function autoLoad() {/*{{{*/
        //get class path
        $_inc = self::compatibleCode();
        $_inc .= self::getAutoLoadContent();
        Files::write(DIR_FS_TMP.'/auto_load.php', '<?php '. $_inc);
        include(DIR_FS_TMP.'/auto_load.php');
        //删除load文件缓存
        Config::flushCache();

        return $_inc;
    }/*}}}*/

    private static function getAutoLoadContent() {/*{{{*/
        //get class path
        self::getClassPath(SAS_DIR.'/component');
        self::getClassPath(APPLICATION_DIR.'/src/controllers');
        self::getClassPath(APPLICATION_DIR.'/src/component');
        self::getClassPath(APPLICATION_DIR.'/src/models');

        if(AUTOLOAD_PATH) {
            $prj_autoload = explode(",", AUTOLOAD_PATH);
            foreach($prj_autoload as $path) {
                $path = trim($path);
                if($path) {
                    self::getClassPath(APPLICATION_DIR.'/'.$path);
                }
            }
        }
        //顺序不能反，getDAOClassPath要在获得self::$classpaths之后
        self::getDAOClassPath();

        $_inc = 'define("AUTOLOAD_CACHED", ' . $_SERVER['REQUEST_TIME'] . ');';
        $_inc .= ' function getClassPath($k){';
        $_inc .= ' $a = '.var_export(self::$classpaths, true).';';
        $_inc .= 'if(isset($a[$k])){';
        $_inc .= ' return $a[$k];';
        $_inc .= '}else{';
        $_inc .= '$funcs = spl_autoload_functions();';
        $_inc .= 'if(count($funcs) > 1) { return;} else {die("Class $k is not found!"); } } }';

        $controller_list = self::getRegControllers();
        $_inc .= ' function getRegControllers(){';
        $_inc .= ' return '.var_export($controller_list, true).';';
        $_inc .= '}';

        return $_inc;
    }/*}}}*/

    private static function getDAOClassPath() {/*{{{检测是否已定义，若未定义，则自动生成并保存在TMP下*/
        $tables = Config::get('TABLE_CONF');

        foreach((array)$tables as $k=>$v) {
            $k_lower = strtolower($k);
            $k_no_underline = str_replace('_', '', $k_lower);
            if(!isset(self::$classpaths['dao'.$k_no_underline])) {
                //gen dao file
                self::$classpaths['dao'.$k_no_underline] = self::genDao($k_lower);
            }
        }
    }/*}}}*/

    private static function genDao($k) {/*{{{*/
        $k_parts = explode("_", $k);
        if(count($k_parts) > 1) {
            foreach($k_parts as $v) {
                $class .= ucfirst($v);
            }
        } else {
            $class = ucfirst($k);
        }

        $dao_script = str_replace(array("__DAO__", "__TABLE__"), array($class, $k), self::$dao_tpl);
        $path = DIR_FS_TMP."/dao/DAO".$class.".php";
        Files::write($path, $dao_script);
        Files::write($path, php_strip_whitespace($path));

        return $path;
    }/*}}}*/

    private static function getRegControllers() {/*{{{*/
        $controller_files = scandir(APPLICATION_DIR.'/src/controllers');

        $controllers = array();
        foreach ($controller_files as $controller) {
            if($controller == '.git'||$controller == '.svn'||$controller == '.cvs'||$controller == '.'||$controller== '..') continue;

            if (substr($controller,-14) == 'Controller.php') {
                $controller_name= substr($controller,0,-14); 
                $controllers[$controller_name] = $controller_name;
            }
        }

        return $controllers;
    }/*}}}*/

    private static function getClassPath($path) {/*{{{*/
        if(is_dir($path)) {
            $files = scandir($path);
            foreach ($files as $f) {
                if(($f == '.git') || ($f == '.svn') || ($f == '.') || ($f == '..')) continue;
                self::getClassPath($path .'/'.$f); 
            }
        } elseif(is_file($path)) {
            $part = explode(".", $path);
            if('php' != end($part)) {
                return;
            }

            $classes = self::_getClassPath($path);
            foreach($classes as $clsname) {
                if(isset(self::$classpaths[$clsname])) {
                    die("Repeatedly class $clsname in file $path");
                }

                self::$classpaths[$clsname] = $path;
            }
        }
    }/*}}}*/

    private static function _getClassPath($file) {/*{{{*/
        $classes = array();
        $lines = file($file);

        foreach ($lines as $line) {
            if (preg_match("/^\s*class\s+(\w+)\s*/", $line, $match)) {
                $classes[] = strtolower($match[1]);
            }

            if (preg_match("/^\s*(abstract|final)\s*class\s+(\S+)\s*/", $line, $match)) {
                $classes[] = strtolower($match[2]);
            }

            if (preg_match("/^\s*interface\s+(\w+)\s*/", $line, $match)) {
                $classes[] = strtolower($match[1]);
            }
        }

        return $classes;
    }/*}}}*/

    private static function compatibleCode() {/*{{{*/
        $_inc = '';

        $sas_varcache = true;
        if(function_exists('apc_fetch')){
            $_inc .='function sas_varcache_get($key){return apc_fetch($key);}';
            $_inc .='function sas_varcache_set($key, $value, $ttl='.(defined('SAS_VARCACHE_TTL') ? SAS_VARCACHE_TTL : 0).'){return apc_store($key, $value, $ttl);}';
            $_inc .='function sas_varcache_unset($key){return apc_delete($key);}';
        }elseif(function_exists('eaccelerator_get')) {
            $_inc .='function sas_varcache_get($key){return eaccelerator_get($key);}';
            $_inc .='function sas_varcache_set($key, $value, $ttl='.(defined('SAS_VARCACHE_TTL') ? SAS_VARCACHE_TTL : 0).'){
                eaccelerator_lock($key);
                $rs = eaccelerator_put($key, $value, $ttl);
                eaccelerator_unlock($key);
                return $rs; }';
            $_inc .='function sas_varcache_unset($key){return eaccelerator_rm($key);}';
        }elseif(function_exists('xcache_get')){
            $_inc .='function sas_varcache_get($key){return xcache_get($key);}';
            $_inc .='function sas_varcache_set($key, $value, $ttl='.(defined('SAS_VARCACHE_TTL') ? SAS_VARCACHE_TTL : 0).'){
                $fp = fopen(DIR_FS_TMP . "/varcahce_" . md5($key). ".lock", "w");
                flock($fp, LOCK_EX);
                if(xcache_isset($key)) {
                    fclose($fp);
                    return xcache_get($key);
                }

                $rs = xcache_set($key, $value, $ttl);
                fclose($fp);
                return $rs; }';
            $_inc .='function sas_varcache_unset($key){return xcache_unset($key);}';
        }else{
            $sas_varcache = false;
        }

        if($sas_varcache) {
            $_inc .= 'define("SAS_VARCACHE", true);';
        }
        return $_inc;
    }/*}}}*/
}

