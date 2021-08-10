<?php
class LocalCache
{
	private static function getShmop($key) {/*{{{*/
        static $_shmop;
        if(!isset($_shmop[$key])) {
            $shmop_key_file = FileCache::getFile($key);
            if(!is_file($shmop_key_file)) {
                Files::write($shmop_key_file);
            }

            $_shmop[$key] = new Shmop($shmop_key_file);
        }

        return $_shmop[$key];
    }/*}}}*/

	public static function set($key, $value, $ttl = 300) {/*{{{*/
        if(function_exists("shmop_open")) {
            return self::getShmop($key)->set($key, $value, $ttl);
        } else {
            return FileCache::set($key, $value, $ttl);
        }
 	}/*}}}*/
    
    public static function get($key) {/*{{{*/
        if(function_exists("shmop_open")) {
            return self::getShmop($key)->get($key);
        } else {
            return FileCache::get($key);
        }
 	}/*}}}*/
    
    public static function delete($key) {/*{{{*/
        if(function_exists("shmop_open")) {
            return self::getShmop($key)->delete($key);
        } else {
            return FileCache::delete($key);
        }
 	}/*}}}*/

    //以下功能比较鸡肋，可忽略
    //通过发送redis 订阅消息，更新远程服务器的本地缓存
    public static function flushCache($key) {/*{{{*/
        $redis = self::getPubClient();

        $shmop_key_file = FileCache::getFile($key);
        $redis->publish('localcache-' . $shmop_key_file, $key);
    }/*}}}*/

    //监控redis 订阅频道，更新缓存
    //在cli脚本中调用
    public static function runBroadcast($keys) {/*{{{*/
        set_time_limit(0);
        if(!is_array($keys)) {
            $keys = array($keys);
        }

        $sub_keys = array();
        foreach($keys as $key) {
            $shmop_key_file = FileCache::getFile($key);
            $sub_keys[] = 'localcache-' . $shmop_key_file;
        }

        while(true) {
            $redis = self::getSubClient();
            $redis->subscribe($sub_keys, array("LocalCache", "subCallback"));

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

    private static function _getRedis($pconnect = false) { /*{{{*/
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

