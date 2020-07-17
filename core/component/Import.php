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
        if(!is_dir(TMP_DIR)) {
            mkdir(TMP_DIR, 0777, true);
        }

        $fp = @fopen(TMP_DIR . '/lock','w+');
        if($fp) {
            //没有抢到锁不堵塞
            if(flock($fp, LOCK_EX | LOCK_NB)) {
                self::_genCache($_inc);

                fwrite($fp, "ok");
                flock($fp, LOCK_UN);
            } else {
                self::_genCache($_inc, false);
            }
            fclose($fp);
        } else {
            self::_genCache($_inc, false);
        }
    }/*}}}*/

    public static function _genCache($_inc, $getLock = true) {/*{{{*/
        $shmop_key_file = self::getShmopKeyFile();
        if($shmop_key_file) {
            define("SHMOP_KEY_FILE", $shmop_key_file);
        }

        //删除load文件缓存
        Config::flushCache();

        //get class path
        $_autoload_inc = self::getAutoLoadContent($getLock);

        if(!$getLock) {
            $filename = tempnam(TMP_DIR, 'auto_load');
        } else {
            $filename = TMP_DIR . '/auto_load.php';
        }

        Files::write($filename, '<?php '. $_autoload_inc);
        include($filename);

        if($getLock) {
            Files::write(TMP_DIR.'/sas.php', '<?php define("IMPORT_CACHED", ' . $_SERVER['REQUEST_TIME'] . ');' . $_inc . $_autoload_inc);
        } else {
            unlink($filename);
        }
    }/*}}}*/

    private static function getAutoLoadContent($getLock = true) {/*{{{*/
        //get class path
        self::getClassPath(SAS_DIR.'/component');
        //self::getClassPath(APPLICATION_DIR.'/src/controllers');
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
        self::getDAOClassPath($getLock);

        $_inc = 'define("AUTOLOAD_CACHED", ' . $_SERVER['REQUEST_TIME'] . ');';

        list($modules, $controllers, $controller_classes) = self::getRegControllers();
        
        if($modules) {
            $_inc .= 'define("ROUTER_HAS_MODULE", true);';
            $_inc .= ' function getRegModules(){';
            $_inc .= ' return '.var_export($modules, true).';';
            $_inc .= '}';
        }

        $_inc .= ' function getRegControllers(){';
        $_inc .= ' return '.var_export($controllers, true).';';
        $_inc .= '}';

        $_inc .= ' function getClassPath($k){';
        $_inc .= ' $a = '.var_export(self::$classpaths, true).';';
        $_inc .= ' $c = '.var_export($controller_classes, true).';';

        $_inc .= 'if(isset($a[$k])){';
        $_inc .= ' return $a[$k];';
        $_inc .= '}elseif(defined("MODULE") && "" != MODULE && isset($c[MODULE][$k])) {';
        $_inc .= ' return $c[MODULE][$k];';
        $_inc .= '}elseif(isset($c[$k])) {';
        $_inc .= ' return $c[$k];';
        $_inc .= '}else{';
        $_inc .= '$funcs = spl_autoload_functions();';
        $_inc .= 'if(count($funcs) > 1) { return;} else {die("Class $k is not found!");}}}';
        

        if(defined("SHMOP_KEY_FILE")) {
            $_inc .= 'define("SHMOP_KEY_FILE", "' . SHMOP_KEY_FILE . '");';
        }

        return $_inc;
    }/*}}}*/

    private static function getDAOClassPath($getLock = true) {/*{{{检测是否已定义，若未定义，则自动生成并保存在TMP下*/
        $tables = Config::get('TABLE_CONF');

        foreach((array)$tables as $k=>$v) {
            $k_lower = strtolower($k);
            $k_no_underline = str_replace('_', '', $k_lower);
            if(!isset(self::$classpaths['dao'.$k_no_underline])) {
                //gen dao file
                self::$classpaths['dao'.$k_no_underline] = self::genDao($k_lower, $getLock);
            }
        }
    }/*}}}*/

    private static function genDao($k, $getLock = true) {/*{{{*/
        $k_parts = explode("_", $k);
        if(count($k_parts) > 1) {
            foreach($k_parts as $v) {
                $class .= ucfirst($v);
            }
        } else {
            $class = ucfirst($k);
        }

        $dao_script = str_replace(array("__DAO__", "__TABLE__"), array($class, $k), self::$dao_tpl);
        $path = TMP_DIR."/dao/DAO".$class.".php";
        if($getLock) {
            Files::write($path, $dao_script);
        } else {//没有拿到锁，生成缓存文件, 直接include, 程序逻辑中用到DAO时不再走autoload逻辑
            $tmpname = tempnam(TMP_DIR, 'dao');
            file_put_contents($tmpname, $dao_script);
            include($tmpname);
            unlink($tmpname);
        }

        return $path;
    }/*}}}*/

    private static function getRegControllers() {/*{{{*/
        if(!is_dir(APPLICATION_DIR.'/src/controllers')) {
            return array();
        }

        return self::_getRegControllers(APPLICATION_DIR.'/src/controllers');
    }/*}}}*/

    //支持分组 module->controller
    private static function _getRegControllers($path) {/*{{{*/
        $modules = array();
        $controllers = array();
        $controller_classes = array();

        $files = scandir($path);
        foreach ($files as $f) {
            if(($f == '.git') || ($f == '.svn') || ($f == '.') || ($f == '..')) continue;

            if(is_dir($path . '/' . $f)) {
                if(isset($controllers[$f])) {
                    die("Repeatedly controller_name $f in file $path");
                }
                $modules[$f] = $f;
                list(, $controllers[$f], $controller_classes[$f]) = self::_getRegControllers($path .'/'.$f); 
            } elseif(substr($f, -14) == 'Controller.php') {
                $controller_name = substr($f, 0,-14); 
                $controllers[$controller_name] = $controller_name;

                $classes = self::_getClassPath($path . '/' . $f);
                foreach($classes as $clsname) {
                    if(isset($controller_classes[$clsname])) {
                        die("Repeatedly class $clsname in file $path/$f");
                    }

                    $controller_classes[$clsname] = $path . '/' . $f;
                }
            }
        }

        return array($modules, $controllers, $controller_classes);
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

    private static function getShmopKeyFile() {/*{{{*/
        if(defined("LOCALCACHE_NAMESPACE") && LOCALCACHE_NAMESPACE != "" && is_dir(SYS_TMP_DIR)) {
            $shmop_key_file = SYS_TMP_DIR . "/shmop-key-" . LOCALCACHE_NAMESPACE;
            touch($shmop_key_file);
            if(is_file($shmop_key_file)) {
                return $shmop_key_file;
            }
        }

        return null;
    }/*}}}*/
}

