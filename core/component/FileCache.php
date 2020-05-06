<?php
!defined("LEVELS_DIR_CACHE") && define("LEVELS_DIR_CACHE", 1);
class FileCache 
{
	public static function set($key, $value, $ttl = 300)
	{/*{{{*/
		$name = md5($key);
		$dir  = self::getdir($name);
        $expire = $ttl > 0 ? ($ttl > 315360000 ? $ttl: (time() + $ttl)) : 0;

        return Files::write($dir.'/'.$name, json_encode(array("key" => $key, "expire" => $expire, "val" => $value)), true);
 	}/*}}}*/

	public static function get($key)
	{/*{{{*/
        $name=md5($key);
		$dir=self::getdir($name);
		if(is_file($dir.'/'.$name)) {
            $data = file_get_contents($dir.'/'.$name);
            $contents = json_decode($data, true);

            return (isset($contents["val"]) && (!$contents["expire"] || $contents["expire"] > time()))  ? $contents["val"] : "";
        }

        return "";
 	}/*}}}*/

    public static function delete($key)
	{/*{{{*/
		$name = md5($key);
		$dir  = self::getdir($name);
		return @unlink($dir.'/'.$name);
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
                $contents = json_decode($data, true);

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
