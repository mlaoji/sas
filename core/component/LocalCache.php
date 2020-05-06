<?php
!defined("SHMOP_KEY_FILE") && define("SHMOP_KEY_FILE", __FILE__);

class LocalCache
{
	private static function getShmop() {/*{{{*/
        static $_shmop;
        if(!$_shmop) {
            $_shmop = new Shmop(SHMOP_KEY_FILE);
        }

        return $_shmop;
    }/*}}}*/

	public static function set($key, $value, $ttl = 300) {/*{{{*/
        if(function_exists("shmop_open")) {
            return self::getShmop()->set($key, $value, $ttl);
        } else {
            return FileCache::set($key, $value, $ttl);
        }
 	}/*}}}*/
    
    public static function get($key) {/*{{{*/
        if(function_exists("shmop_open")) {
            return self::getShmop()->get($key);
        } else {
            return FileCache::get($key);
        }
 	}/*}}}*/
    
    public static function delete($key) {/*{{{*/
        if(function_exists("shmop_open")) {
            return self::getShmop()->delete($key);
        } else {
            return FileCache::delete($key);
        }
 	}/*}}}*/

    public static function getAll() {/*{{{*/
        if(function_exists("shmop_open")) {
            return self::getShmop()->getAll();
        } else {
            return FileCache::getAll();
        }
 	}/*}}}*/
}

