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

    //通过发送redis 订阅消息，更新远程服务器的本地缓存
    public static function flushCache($key) {/*{{{*/
        $redis = self::getPubClient();
        $redis->publish('localcache-'.SHMOP_KEY_FILE, $key);
    }/*}}}*/

    //监控redis 订阅频道，更新缓存
    //在cli脚本中调用
    public static function runBroadcast() {/*{{{*/
        set_time_limit(0);
        while(true) {
            $redis = self::getSubClient();
            $redis->subscribe(array('localcache-'.SHMOP_KEY_FILE), array("LocalCache", "subCallback"));

            sleep(3);
        }
	}/*}}}*/

    //收到订阅消息回调处理
    public static function subCallback($instance, $channel, $message) {/*{{{*/
        self::delete($message);
    }/*}}}*/

    public static function getPubClient() {/*{{{*/
        return self::_getRedis();
	}/*}}}*/

    public static function getSubClient() {/*{{{*/
        return self::_getRedis(true);
	}/*}}}*/

    private function _getRedis($pconnect = false) { /*{{{*/
		static $redis;
        try{
            if(isset($redis) && $redis instanceof Redis && $redis->ping() == "+PONG") {
                return $redis;
            }
		} catch(RedisException $e) {}

        try {
            $config = Config::get("REDIS_CONF");

            $redis  = new Redis();
            if($pconnect) {
                $redis->pconnect($config["host"], $config["port"], 0);
            } else {
                $redis->connect($config["host"], $config["port"], 3);
            }
            $redis->auth($config["password"]);
        } catch(RedisException $e) {
            throw new Exception($e->getMessage(), $e->getCode());
        }

        return $redis;
    }/*}}}*/

}

