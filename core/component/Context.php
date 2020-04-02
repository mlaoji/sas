<?php
class Context
{
	private static $_container;
	
	public static function set($key, $value) {
		self::$_container[$key] = $value;
		return true;	
	}
	
	public static function get($key)
	{
		return self::$_container[$key];
	}
}
