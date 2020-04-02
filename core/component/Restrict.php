<?php
/**
 * 频度控制器
 */
class Restrict 
{
    /**
     * @param string $_rule 规则名
     */
    public $_rule;

    /**
     * @param int $_freq 频度
     */
    public $_freq;
    
    /**
     * @param int $_interval 周期, 单位秒
     */
    public $_interval;

    /**
     * @param array $_whitelist  白名单
     */
    public $_whitelist = array();

    /**
     * @param array $_blacklist 白名单
     */
    public $_blacklist = array();
    
    /**
     * @param array $_redisconf redis 配置
     */
    public $_redisconf;
    
    public function __construct($rule, $freq, $interval, $config = null) {/*{{{*/
        $this->_rule = $rule;
        $this->_freq = $freq;
        $this->_interval = $interval;
        $this->_redisconf = $config;
    }/*}}}*/

    public function AddWhiteList($uniqid = array()) {/*{{{*/
        $this->_whitelist = array_merge($this->_whitelist, $uniqid);
    }/*}}}*/

    public function AddBlackList($uniqid = array()) {/*{{{*/
        $this->_blacklist = array_merge($this->_blacklist, $uniqid);
    }/*}}}*/

    public function Add($uniqid, $num = 1) {/*{{{*/
        return $this->Check($uniqid, true, $num);
    }/*}}}*/

    /**
     * 频度检查
     * @param $uniqid 唯一ID
     * @param bool $update 是否检查同时更新次数值，默认只检查是否触犯封禁次数
     * @return bool true 达到封禁次数 false 未达到封禁次数
     */
    public function Check($uniqid, $update = false, $num = 1) { //{{{
        foreach($this->_whitelist as $v) {
            if($uniqid == $v) {
                return false;
            }
        }
        
        foreach($this->_blacklist as $v) {
            if($uniqid == $v) {
                return true; 
            }
        }

        $times = $this->getRecord($uniqid);

        if($times >= $this->_freq) {
            return true;
        }

        if($update) {
            $times = $this->incrRecord($uniqid, $num);

            if($times > $this->_freq) {
                return true;
            }
        }

        return false;
    } // }}}

    private function getKey($uniqid) { //{{{
        return "rest:".$this->_rule . $uniqid . (ceil(time()/$this->_interval));
    } //}}}

    public function GetRecord($uniqid) { //{{{
        $key = $this->getKey($uniqid);

        $redis_client = RedisProxy::getInstance($this->_redisconf);
        $cache = $redis_client->Get($key);

        return (int)$cache;
    } // }}}

    private function incrRecord($uniqid, $num = 1) { //{{{
        $key = $this->getKey($uniqid);

        $redis_client = RedisProxy::getInstance($this->_redisconf);
        $times = $redis_client->IncrBy($key, $num);

        $redis_client->Expire($key, $this->_interval);

        return $times;
    } // }}}

    private function Surplus($uniqid) { //{{{
        $key = $this->getKey($uniqid);

        $times = $this->getRecord($key);

        $surplus = $this->_freq - $times;
        if($surplus > 0) {
            return $surplus;
        }

        return 0;
    } // }}}

    public function Delete($uniqid) { //{{{
        $key = $this->getKey($uniqid);

        $redis_client = RedisProxy::getInstance($this->_redisconf);
        $redis_client->Del($key);
    } // }}}
}
