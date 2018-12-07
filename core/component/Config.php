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

    public static function flushCache() {/*删除缓存{{{*/
        if(defined("SAS_VARCACHE")) {
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
            sas_varcache_unset($path); 
        }
    }/*}}}*/

    private static function _getFileVars($file_path) {/*{{{*/
        if(defined("SAS_VARCACHE")) {   
            $s =sas_varcache_get($file_path); 
            if(!$s) {
                unset($s);
                include($file_path); 
                $s =  get_defined_vars();
                sas_varcache_set($file_path, $s);
            }
            return $s; 
        } else {
            include($file_path); 
            return get_defined_vars(); 
        }   
    }/*}}}*/
}
