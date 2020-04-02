<?php
/**
 * 拦截器
 */
class Interceptor
{
    public static function ensure($bool, $errno, $args = array(), $data = array()) {/*{{{*/
        if(!$bool) {
            throw new SasRunException($errno, $args, $data);
        }
    }/*}}}*/
}
