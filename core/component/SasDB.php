<?php
class SasDB 
{
	private static $_container      = array();
	private static $_default_config = array(
        "host"=>"127.0.0.1",
        "port"=>"3306",
        "user"=>"root",
        "pass"=>"",
        "charset"=>"utf8mb4",
        "dbname"=>"test",
        "persistent"=>true,
        "unix_socket"=>"",
        "options"=>array()
    );

	public static function getInstance($config = array()) {/*{{{*/
		$key = md5(serialize($config));

		if(!isset(self::$_container[$key]) || !(self::$_container[$key] instanceof SasDBPDO)) {
			$final_config = self::_getFinalConfig($config);
			self::$_container[$key] = new SasDBPDO($final_config);
		}

		return self::$_container[$key];
	}/*}}}*/
    
    private static function _getFinalConfig($config = array()) {/*{{{*/
        $final_config = array();
        foreach(self::$_default_config as $index=>$value) {
            $final_config[$index] = isset($config[$index]) && ('' !== $config[$index]) ? $config[$index] : self::$_default_config[$index];
        }

        return $final_config;
    }/*}}}*/

    public static function destroyInstance($config = array()) {/*{{{*/
         $key = md5(serialize($config)); 
         if(isset(self::$_container[$key]) && (self::$_container[$key] instanceof SasDBPDO))
         {
               self::$_container[$key]->close();
               unset(self::$_container[$key]);
         }
         return true;
    }/*}}}*/
}

class SasDBPDO
{
	private $_config         = array();
	private $_conn           = null;
	private $_fetch_type     = PDO::FETCH_ASSOC;
	private $_debug          = false;
	private $_log            = false;
	private $_optimize       = false;
	private $_transaction    = false;
	private $_auto_reconnect = true;  //是否需要开启自动重连
    private $_attributes     = array( //建立连接时setAttribute
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    );

	public function __construct($config) {/*{{{*/
		$this->_config = $config;
	}/*}}}*/

	private function _connect() {/*{{{*/
		if($this->_conn == null) {
			if($this->_config["unix_socket"]) {
				$dsn = "mysql:dbname={$this->_config["dbname"]};unix_socket={$this->_config["unix_socket"]}";
			} else {
				$dsn = "mysql:dbname={$this->_config["dbname"]};host={$this->_config["host"]};port={$this->_config["port"]}";
			}

			$user = $this->_config["user"];
			$pass = $this->_config["pass"];
            $options    = (array)$this->_config['options'] + array(PDO::ATTR_PERSISTENT=>$this->_config['persistent']);

			try {
				$this->_conn = new PDO($dsn, $user, $pass, $options);
			} catch (PDOException $e) {
				throw new SasDBException($e->getMessage(), (int)$e->getCode());
			}

            foreach($this->_attributes as $attr => $val) {
                $this->_conn->setAttribute($attr, $val);
            }

			$this->execute("SET NAMES '{$this->_config["charset"]}'");
			$this->execute("SET character_set_client=binary");
		}
	}/*}}}*/

	private function _exec($sql, $params) {/*{{{*/
		$this->_connect();

		if($this->_debug) {
			print $this->getBindedSql($sql, $params)."\n";
		}

		$stmt = new SasDBStatment($this->_conn->prepare($sql));
		if(is_array($params)) {
			if(!empty($params)) {
				$i = 0;
				foreach($params as $value) {
					$stmt->bind(++$i, $value);
				}
			}
		} else {
			$stmt->bind(1, $params);
		}
		$execute_return = $stmt->execute();

		if($this->_optimize && preg_match("/^select/i", $sql)) {
			$fetch_mode = $this->_fetch_type;
			$this->setFetchMode(PDO::FETCH_ASSOC);

			$sql = $this->getBindedSql($sql, $params);
			$draw = SasDBExplainResult::draw($this->getAll("explain ".$sql));
            if($this->_debug) {
                print $draw;
            }

            Logger::other("sql_optimize", $sql . "\n" . $draw);

			$this->setFetchMode($fetch_mode);
		}

		return array("stmt"=>$stmt, "execute_return"=>$execute_return);
	}/*}}}*/

	private function _process($sql, $params) {/*{{{*/
		//关闭直接事务语句
		if(in_array(preg_replace("/\s{2,}/", " ", strtolower($sql)), array("begin", "commit", "rollback", "start transaction", "set autocommit=0", "set autocommit=1"))) {
			throw new SasDBException("为避免操作异常，请使用包装后的事务处理接口[startTrans, commit, rollback]");
		}

        try {
            //屏蔽错误，捕获异常处理
            $arr_exec_result = @$this->_exec($sql, $params);
        } catch (PDOException $e) {
            if($this->_auto_reconnect && in_array($e->errorInfo[1], array(2013, 2006)) && !$this->_transaction) {
                try {
                    $this->close();
                    $arr_exec_result = $this->_exec($sql, $params);
                } catch (PDOException $e) {
                    throw new SasDBException($e->getMessage(), (int)$e->getCode());
                }
            } else {
                throw new SasDBException($e->getMessage(), (int)$e->getCode());
            }
        }

		return $arr_exec_result;
	}/*}}}*/

	private function _checkSafe($sql, $is_open_safe = true) {/*{{{*/
		if(!$is_open_safe) {
			return true;
		}

		$string  = strtolower($sql);
		$operate = strtolower(substr($sql, 0, 6));
		$is_safe = true;
		switch ($operate) {
			case "select":
				if(strpos($string, "where") && !preg_match("/\(.*\)/", $string) && !strpos($string, "?")) {
					$is_safe = false;
				}
				break;
			case "insert":
			case "update":
			case "delete":
				if(!strpos($string, "?")) {
					$is_safe = false;
				}
				break;
		}

		if(!$is_safe) {
			throw new SasDBException("SQL语句:[$sql],存在SQL注入漏洞隐患，请改用bind方式处理或关闭sql执行safe模式.");
		}

		return $is_safe;
	}/*}}}*/

	public function getInsertId() {/*{{{*/
		return $this->_conn->lastInsertId();
	}/*}}}*/

	public function execute($sql, $params = array(), $is_open_safe = true) {/*{{{*/
		if($this->_log) {
            Logger::other("sql", $sql, array("params" => implode(",", (array)$params)));
		}

		$this->_checkSafe($sql, $is_open_safe);

		$arr_process_result = $this->_process($sql, $params);
       
		if($arr_process_result["execute_return"]) {
			$operate = strtolower(substr($sql, 0, 6));
			switch ($operate) {
				case "replac":
				case "insert":
					$arr_process_result["execute_return"] = $this->getInsertId();
					break;
				case "update":
				case "delete":
					$arr_process_result["execute_return"] = $arr_process_result["stmt"]->getEffectedRows();
					break;
				default:
					break;
			}
		}
 
		return $arr_process_result["execute_return"];
	}/*}}}*/

	public function query($sql, $params = array(), $is_open_safe = true) {/*{{{*/
        if($this->_log) {
            Logger::other("sql", $sql, array("params" => implode(",", (array)$params)));
        }

		$this->_checkSafe($sql, $is_open_safe);
		$result = $this->_process($sql, $params);
		return $result["stmt"];
	}/*}}}*/

	public function getOne($sql, $params = array(), $safe = true) {/*{{{*/
		$stmt   = $this->query($sql, $params, $safe);
		$record = $stmt->fetch($this->_fetch_type);
		return is_array($record) && !empty($record) ? array_shift($record) : null;
	}/*}}}*/

	public function getRow($sql, $params = array(), $safe = true) {/*{{{*/
		$stmt   = $this->query($sql, $params, $safe);
		$record = $stmt->fetch($this->_fetch_type);
		return is_array($record) && !empty($record) ? $record : array();
	}/*}}}*/

	public function getAll($sql, $params = array(), $safe = true) {/*{{{*/
		$stmt = $this->query($sql, $params, $safe);
		$data = array();
		while ($record = $stmt->fetch($this->_fetch_type)) {
			$data[] = $record;
		}
		return $data;
	}/*}}}*/

    //return stmt
	public function getAllByCursor($sql, $params = array(), $safe = true) {/*{{{*/
        //关闭缓存设置,否则会一次性取出
        $this->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);

		$stmt  = $this->query($sql, $params, $safe);

        //使用后再次打开缓存设置
        $this->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);

        return $stmt;
	}/*}}}*/

    //调用getAllByCursor返回的stmt
    //foreach ($this->getCursor($stmt) as $row) {
    //  var_dump($row);
    //}
	public function getCursor($stmt) {/*{{{*/
        while ($record = $stmt->fetch($this->_fetch_type)) {
            yield $record;
        }
	}/*}}}*/

	private function _operate($table, $record, $operate, $condition = "", $params = array()) {/*{{{*/
		if(in_array($operate, array("insert", "replace", "update"))) {
			$fields = is_array($record) ? array_keys($record)   : array();
			$values = is_array($record) ? array_values($record) : array();

			if(empty($fields)) {
				throw new SasDBException("\$record 操作数据必须使用关联数组形式");
			}
            
            $sql_record_part = "";
            foreach($fields as $k => $field) {
                if($k > 0) {
                    $sql_record_part .= ",";
                }

                if($val = $this->getFuncParam($values[$k])) {
                    $sql_record_part .= "`$field`=$val";
                    unset($values[$k]);
                } else {
                    $sql_record_part .= "`$field`=?";
                }
            }
		}

		switch ($operate) {
			case "insert":
			case "replace":
				$sql = "$operate into $table set " . $sql_record_part;
				return $this->execute($sql, $values);
				break;
			case "update":
				$sql = "update $table set " . $sql_record_part;
				
				if($condition) {
					$sql .= " where ".$condition;
				}
				is_array($params) ? $values = array_merge($values, $params) : $values[] = $params;
				return $this->execute($sql, $values);
				break;
			case "delete":
				$sql = "delete from $table where $condition";
				return $this->execute($sql, $params);
				break;
		}
		return true;
	}/*}}}*/

    public function getFuncParam($param) {/*{{{*/
        if(substr($param, 0, 5) == "#:F:#") {
            return substr($param, 5); 
        }

        return "";
    }/*}}}*/
    
    //拼装参数时，作为可执行字符，而不是字符串值
    public function funcParam($param) {/*{{{*/
        if("" != $param) {
            return "#:F:#" . $param; 
        }

        return "";
    }/*}}}*/


	public function insert($table, $record) {/*{{{*/
		return $this->_operate($table, $record, "insert");
	}/*}}}*/

	public function replace($table, $record) {/*{{{*/
		return $this->_operate($table, $record, "replace");
	}/*}}}*/

	public function update($table, $record, $condition, $params) {/*{{{*/
        try {
            return $this->_operate($table, $record, "update", $condition, $params);
        } catch (SasDBException $e) {
            throw new SasDBException($e->getMessage());
        }
	}/*}}}*/

	public function delete($table, $condition, $params) {/*{{{*/
		return $this->_operate($table, null, "delete", $condition, $params);
	}/*}}}*/

	public function setWaitTimeOut($seconds) {/*{{{*/
		$this->execute("set wait_timeout=$seconds");
	}/*}}}*/

	public function setAutoReconnect($flag) {/*{{{*/
		$this->_auto_reconnect = $flag;
	}/*}}}*/

	public function setDebug($flag = false) {/*{{{*/
		$this->_debug = $flag;
	}/*}}}*/

	public function setOptimize($flag = false) {/*{{{*/
		$this->_optimize = $flag;
	}/*}}}*/

	public function setFetchMode($fetch_type = PDO:: FETCH_ASSOC) {/*{{{*/
		$this->_fetch_type = $fetch_type;
	}/*}}}*/

	public function setAttribute($attr, $val) {/*{{{*/
		$this->_attributes[$attr] = $val;
        if($this->_conn != null) {
            $this->_conn->setAttribute($attr, $val);
        }
	}/*}}}*/

	public function setLog($flag = false) {/*{{{*/
		$this->_log = $flag;
	}/*}}}*/

	public function startTrans() {/*{{{*/
		if($this->_transaction) {
			throw new SasDBException("之前开启的事务尚未结束，事务处理不能嵌套操作!");
		}

        $this->_connect();

		try {
			@$this->_conn->beginTransaction();
		} catch (PDOException $e) {
            if($this->_auto_reconnect && in_array($e->errorInfo[1], array(2013, 2006))) {
                try {
                    $this->close();
                    $this->_connect();
                    $this->_conn->beginTransaction();
                } catch (PDOException $e) {
                    throw new SasDBException($e->getMessage(), (int)$e->getCode());
                }
            } else {
                throw new SasDBException($e->getMessage(), (int)$e->getCode());
            }
		}

		$this->_transaction = true;
	}/*}}}*/

	public function commit() {/*{{{*/
		if(!$this->_transaction) {
			throw new SasDBException("之前开启的事务已经被提交或没有开启，请仔细查看事务处理过程中的操作语句!");
		}

		$this->_transaction = false;

		try {
			$this->_conn->commit();
		} catch (PDOException $e) {
            throw new SasDBException($e->getMessage(), (int)$e->getCode());
		}
	}/*}}}*/

	public function rollback() {/*{{{*/
		if(!$this->_transaction) {
			throw new SasDBException("之前开启的事务已经被提交或没有开启，请仔细查看事务处理过程中的操作语句!");
		}

		$this->_transaction = false;

		try {
			$this->_conn->rollback();
		} catch (PDOException $e) {
            throw new SasDBException($e->getMessage(), (int)$e->getCode());
		}
	}/*}}}*/

	public function isTrans() {/*{{{是否在事务中*/
		return $this->_transaction;
	}/*}}}*/

	public function close() {/*{{{*/
		$this->_conn = null;
	}/*}}}*/

	public function getBindedSql($sql, $params = array()) {/*{{{*/
		if(!preg_match("/\?/", $sql)) {
			return $sql;
		}
		
		/* 先找出非正常的变量区域并用"#"代替 */
		preg_match_all('/(?<!\\\\)\'.*(?<!\\\\)\'/U', $sql, $arr_match_list);
		$arr_exists_list = $arr_match_list[0];
		foreach($arr_match_list[0] as $value) {
			$sql = str_replace($value, "#", $sql);
		}
		
		if(!is_array($params)) {
			$params = array($params);
		}
		
		/* 根据#或?分解语句,将内容填充到对应位置上 */
		preg_match_all("/[#\?]/", $sql, $arr_match_list);
		$arr_split_list = preg_split("/[#\?]/", $sql);

		$sql = "";
		foreach($arr_match_list[0] as $key=>$flag) {
			$sql .= $arr_split_list[$key].($flag == "#" ? array_shift($arr_exists_list) : $this->quote(array_shift($params)));
		}
        $sql .= end($arr_split_list);

		return $sql;
	}/*}}}*/

	public function quote($string) {/*{{{*/
		return $this->_conn->quote($string);
	}/*}}}*/
}

class SasDBStatment
{
	private $_stmt;

	public function __construct($stmt) {/*{{{*/
		$this->_stmt = $stmt;
	}/*}}}*/

	public function fetch($mode = PDO::FETCH_ASSOC) {/*{{{*/
		return $this->_stmt->fetch($mode);
	}/*}}}*/

	public function execute() {/*{{{*/
		return $this->_stmt->execute();
	}/*}}}*/

	public function bind($parameter, $value) {/*{{{*/
		return $this->_stmt->bindValue($parameter, $value);
	}/*}}}*/

	public function getEffectedRows() {/*{{{*/
		return $this->_stmt->rowCount();
	}/*}}}*/
}

class SasDBExplainResult
{
	private static $result;

	public static function draw($result) {/*{{{*/
		self::$result = $result;

		//if(PHP_SAPI != 'cli') {
		//	return self::drawHTML();
		//} else {
			return self::drawConsole();
		//}
	}/*}}}*/

	public static function drawHTML() {/*{{{*/
		$res  = "<pre>\n";
		$res .= self::drawConsole();
		$res .= "</pre>\n";

        return $res;
	}/*}}}*/

	public static function drawConsole() {/*{{{*/
		$arr_max_length = array();
		foreach(array_keys(self::$result[0]) as $value) {
			$arr_max_length[] = strlen($value);
		}

		foreach (self::$result as $record) {
			$i = 0;
			foreach ($record as $value) {
				$arr_max_length[$i] = (isset($arr_max_length[$i]) ? max($arr_max_length[$i], strlen($value)) : strlen($value)) + 2;
				$i++;
			}
		}

		//draw title
		$res  = self::drawLine($arr_max_length);
		$res .= self::drawData(array_keys(self::$result[0]), $arr_max_length);
		//draw data
		foreach(self::$result as $record) {
			$res .= self::drawLine($arr_max_length);
			$res .= self::drawData(array_values($record), $arr_max_length);
		}

		$res .= self::drawLine($arr_max_length);

        return $res;
	}/*}}}*/

	public static function drawLine($arr_length_list) {/*{{{*/
		$res = "+";
		foreach($arr_length_list as $length) {
			$res .= str_repeat("-", $length)."+";
		}
		$res .= "\n";

        return $res;
	}/*}}}*/

	public static function drawData($arr_record_list, $arr_length_list) {/*{{{*/
		$res = "|";
		$left = 0;
		foreach ($arr_record_list as $i=>$value) {
			$space  = floor(($arr_length_list[$i] - strlen($value)) / 2);
			$left  += $space;
			$right  = $arr_length_list[$i] - $space;
			$format = '%'.$space.'s%-'.$right.'s|';

			$res .= sprintf($format, "", $value);
			$left  -= $space;
			$left  += $arr_length_list[$i];
		}
		$res .= "\n";

        return $res;
	}/*}}}*/
}

class SasDBException extends SasException
{
	public function __construct($message, $code=0) {/*{{{*/
		$message = "Mysql Error [$code]:$message";
		parent::__construct($message, $code);
	}/*}}}*/
}
