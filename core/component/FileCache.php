<?php
!defined("LEVELS_DIR_CACHE") && define("LEVELS_DIR_CACHE", 1);
class FileCache 
{
	public static function set($key, $value, $ttl = 300)
	{/*{{{*/
        $expire = $ttl > 0 ? ($ttl > 315360000 ? $ttl: (time() + $ttl)) : 0;
        //json_encode 对二进制不友好，所以使用serialize
        return Files::write(self::getFile($key), serialize(array("key" => $key, "expire" => $expire, "val" => $value)), true);
 	}/*}}}*/

	public static function get($key)
	{/*{{{*/
        $file = self::getFile($key);

		if(is_file($file)) {
            $data = file_get_contents($file);
            $contents = unserialize($data, true);

            return (isset($contents["val"]) && (!$contents["expire"] || $contents["expire"] > time()))  ? $contents["val"] : "";
        }

        return "";
 	}/*}}}*/

    public static function delete($key)
	{/*{{{*/
        try{
            return @unlink(self::getFile($key));
        } catch(Exception $e) {
            return false;
        }
 	}/*}}}*/
    
    public static function getFile($key)
	{/*{{{*/
		$name = md5($key);
        return self::getdir($name) . '/' . $name;
 	}/*}}}*/

	public static function flush()
	{
		return Files::rmdirs(TMP_DIR.'/vars');
	}
    
    public static function getAll()
	{/*{{{*/
        return self::_getAll(TMP_DIR.'/vars');
 	}/*}}}*/

	private static function _getAll($dir)
	{/*{{{*/
        $cache = [];
        while($f = scandir($dir)) {
            if(is_file($f)) {
                $data = file_get_contents($f);
                $contents = unserialize($data, true);

                if(isset($contents["key"])) {
                    $cache[$contents["key"]] = array("expire" => $contents["expire"], "val" => $contents["val"]);
                }
            } else {
                $cache = array_merge($cache, self::getAll($dir . "/" . $f));
            }
        }

        return $cache;
 	}/*}}}*/

	private static function getdir($md5name, $dir=null)
	{/*{{{*/
		$dir =$dir ? $dir : (TMP_DIR.'/vars');
		if(LEVELS_DIR_CACHE) {
            for($i=0;$i<LEVELS_DIR_CACHE;$i++){
                $dir.='/'.$md5name[$i];
            }
		}

		return $dir;
 	}/*}}}*/
}
