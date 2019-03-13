<?php
class LocalCache
{
    static $shmkey;

    /**
     * 设置shmop的key, 避免缓存集中到一个内存块上
     * @param int $key: System V IPC key, 可以使用ftok 生成
     */
	public static function setShmopKey($key)
    {
        self::$shmkey = $key; 
    }

	public static function set($key, $value, $ttl = 300)
	{
        if(function_exists("shmop_open")) {
            $shmop = new Shmop(self::$shmkey ? self::$shmkey : ftok(__FILE__, "t"));
            return $shmop->set($key, $value, $ttl);
        } else {
            return FileCache::set($key, $value, $ttl);
        }
 	}
    
    public static function get($key)
	{
        if(function_exists("shmop_open")) {
            $shmop = new Shmop(self::$shmkey ? self::$shmkey : ftok(__FILE__, "t"));
            return $shmop->get($key);
        } else {
            return FileCache::get($key);
        }
 	}
    
    public static function delete($key)
	{
        if(function_exists("shmop_open")) {
            $shmop = new Shmop(self::$shmkey ? self::$shmkey : ftok(__FILE__, "t"));
            return $shmop->delete($key);
        } else {
            return FileCache::delete($key);
        }
 	}
}
	
