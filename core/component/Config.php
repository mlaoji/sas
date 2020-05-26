<?php
class Config 
{
    public static function get($conf_key) {/*{{{*/
        static $confs;
        if (!isset($confs)) {
            $file_path = CONFIG_DIR.'/App.conf.php'; 
            $confs = self::_getFileVars($file_path);
        }

        return $confs[$conf_key];
	}/*}}}*/

    /**
     * 清除缓存
     */
    public static function flushCache() {/*{{{*/
        if(function_exists("shmop_open")) {
            //flush config
            self::flushCacheFiles(CONFIG_DIR.'/App.conf.php');
        }
    }/*}}}*/

    private static function flushCacheFiles($path) {/*{{{*/
        if(is_dir($path)) {
            $files = scandir($path);
            foreach ($files as $f) {
                if(($f == '.svn')||($f == '.git')||($f == '.')||($f == '..')) continue;
                self::flushCacheFiles($path .'/'.$f); 
            }
        } else {
            $shmop = new Shmop(defined("SHMOP_KEY_FILE") ? SHMOP_KEY_FILE : __FILE__);
            $shmop->delete($path);
        }
    }/*}}}*/

    private static function _getFileVars($file_path) {/*{{{*/
        if(function_exists("shmop_open")) {
            $shmop = new Shmop(defined("SHMOP_KEY_FILE") ? SHMOP_KEY_FILE : __FILE__);
            $s = $shmop->get($file_path);
            if(!$s) {
                unset($s);
                include($file_path); 
                $s =  get_defined_vars();
                $shmop->set($file_path, $s, defined('SAS_VARCACHE_TTL') ? SAS_VARCACHE_TTL : 0);
            }
            return $s; 
        } else {
            include($file_path); 
            return get_defined_vars(); 
        }   
    }/*}}}*/

}
