<?php
class RedisProxy
{
	private $_config = null;
	private $_redis = null;

	public function __construct($config = null) {/*{{{*/
        $this->_redis = $this->_getRedis($config);
	}/*}}}*/

    public static function getInstance($config = null) {/*{{{*/
		static $redis;

        $key = $config ? ($config["host"].":". $config["port"]) : "def";

        try{
            if(isset($redis[$key]) && $redis[$key]->_redis instanceof Redis && $redis[$key]->_redis->ping() == "+PONG") {
                return $redis[$key];
            }
		} catch(RedisException $e) {}

        $redis[$key] = new self($config);

        return $redis[$key];
	}/*}}}*/

    private function _getRedis($config = null) { /*{{{*/
        if(!$config) {
            $config = Config::get("REDIS_CONF");	
        }

        try {
            $redis  = new Redis();

            if(true === $config["pconnect"]) {
                $redis->pconnect($config["host"], $config["port"], $config['timeout'] ? $config['timeout'] : 0);
            } else {
                $redis->connect($config["host"], $config["port"], $config['timeout'] ? $config['timeout'] : 3);
            }

            $redis->auth($config["password"]);
        } catch(RedisException $e) {
            throw new SasRedisException($e->getMessage(), $e->getCode());
        }

        return $redis;
    }/*}}}*/
  
    public function __call($name, $arguments) {/*{{{*/
		try {
			return call_user_func_array(array($this->_redis, $name), $arguments);
		} catch(RedisException $e) {
            throw new SasRedisException($e->getMessage(), $e->getCode());
		}
	}/*}}}*/

	public function __destruct()
	{/*{{{*/
        $this->_redis = null;
	}/*}}}*/
}

class SasRedisException extends SasException
{
	public function __construct($message, $code=0) {/*{{{*/
		$message = "Redis Error[$code]:$message";
		parent::__construct($message, $code);
	}/*}}}*/
}
