<?php
class Container 
{
	public static function find($cls) {/*{{{*/
        static $objs;

        if (!isset($objs[$cls])) {
            $objs[$cls] = new $cls();
        }

        return $objs[$cls];
	} /*}}}*/
}
