<?php
/*
 * 利用shmop 将内容缓存到共享内存中
 */
class Shmop 
{
    protected $shmkey;
    protected $perms;
    protected $shmid;
     
    public function __construct($shmop_key_file = __FILE__, $perms = 0644)
    {
        //设置shmop的key, 避免缓存集中到一个内存块上
        $this->shmkey = ftok($shmop_key_file, 't');
        $this->perms  = $perms;
    }

    public function open($size = 0) 
    {/*{{{*/
        if(0 == $size) {//只读
            $this->shmid = $this->shmid ? $this->shmid : @shmop_open($this->shmkey, 'a', 0, 0);
            return (bool)$this->shmid;
        }

        //释放内存，重新申请, 每次按内容大小申请
        if($this->shmid) {
            if(shmop_size($this->shmid) != $size) {
                if(!@shmop_delete($this->shmid)) {
                    return false;
                }
            } 

            shmop_close($this->shmid);
        }

        $this->shmid = @shmop_open($this->shmkey, 'c', $this->perms, $size);

        return (bool)$this->shmid;
    }/*}}}*/
 
    /**
     * set
     * @param String $key
     * @param Mixed $val
     * @param Int $ttl 缓存秒数/或过期时间戳
     * @return Bool 
     */
    public function set($key, $val, $ttl = 0)
    {/*{{{*/
        $lock = TMP_DIR . "/shmop_" . $this->shmkey. ".lock";
        $fp = fopen($lock, "w");
        if(flock($fp, LOCK_EX | LOCK_NB)) {//只有抢到锁才更新
            $data = $this->getAll();
            $expire = $ttl > 0 ? ($ttl > 315360000 ? $ttl: (time() + $ttl)) : 0;
            $data[$key] = array("expire" => $expire, "val" => $val);
            foreach($data as $k => $v) {
                if(isset($v["expire"]) && $v["expire"] > 0 && $v["expire"] <= time()) {
                    unset($data[$k]);
                }
            }

            //json_encode 对二进制不友好，所以使用serialize
            $content = serialize($data);

            if($this->open(strlen($content))) {
                shmop_write($this->shmid, $content, 0);
            }
        
            flock($fp, LOCK_UN);

            if("DEV" == MODE) {//解决开发环境 cli 和 nginx 使用不同用户时的权限问题
                @chmod($lock, 0777);
            }
        }
        fclose($fp);
    }/*}}}*/
    
    /**
     * get all 读出内存中全部的数据
     * @return Array 
     */
    public function getAll()
    {
        return $this->open() ? unserialize(trim(shmop_read($this->shmid, 0, 0))) : array();
    }

    /**
     * delete 
     * @param String $key
     */
    public function delete($key)
    {/*{{{*/
        $lock = TMP_DIR . "/shmop_" . $this->shmkey. ".lock";
        $fp = fopen($lock, "w");
        if(flock($fp, LOCK_EX)) {
            $data = $this->getAll();
            
            if(isset($data[$key])) {
                unset($data[$key]);
            }

            foreach($data as $k => $v) {
                if(isset($v["expire"]) && $v["expire"] > 0 && $v["expire"] <= time()) {
                    unset($data[$k]);
                }
            }

            if(empty($data)) {
                shmop_delete($this->shmid);
            } else {
                $content = serialize($data);

                if($this->open(strlen($content))) {
                    shmop_write($this->shmid, $content, 0);
                }
            }
        
            flock($fp, LOCK_UN);
        }
        fclose($fp);
    }/*}}}*/

    /**
     * @param String $key
     * @return String 
     */
    public function get($key)
    {
        $data  = $this->getAll();
        return isset($data[$key]) && isset($data[$key]["val"]) && (!$data[$key]["expire"] || $data[$key]["expire"] > time()) ? $data[$key]["val"] : "";
    }

    public function flush()
    {/*{{{*/
        if($this->open()) {
            shmop_delete($this->shmid);
        }
    }/*}}}*/

    public function __destruct()
    {
        $this->shmid && shmop_close($this->shmid);
    }
}
