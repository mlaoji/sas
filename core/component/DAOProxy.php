<?php
class DAOProxy 
{
	protected $table;
	protected $primary;
	protected $defaultFields; //默认字段
	protected $fields; //setFields方法指定的字段,只能通过getFields使用一次
	protected $countField;
	protected $index;
	protected $autoOrder = true;
	protected $forceMaster = false;//强制使用主库读，只能通过useMaster 使用一次

	protected $dbWriter;
	protected $dbReader;

    public function __construct($dao_name, $shard_value = null){/*{{{*/
        $dbs_config = Config::get("DB_CONF");
        $tables_config = Config::get("TABLE_CONF");

        if(!isset($tables_config[$dao_name])) {
			throw new SasException("dao ".$dao_name." is undefined");
        }

        $table_config = $tables_config[$dao_name];
        $host_id = $this->getHostId($table_config, $shard_value);
        $db_config = $dbs_config[$host_id];
        $this->dbWriter =  SasDB::getInstance($db_config["master"]);
        if(true === $db_config["master"]["debug"]) {
            $this->dbWriter->setDebug(true);
        }
        
        if(true === $db_config["master"]["log"]) {
            $this->dbWriter->setLog(true);
        }

        if(empty($db_config["slaves"])) {
            $this->dbReader = $this->dbWriter;
        } else {
            $slave_key  = array_rand($db_config["slaves"]);
            $this->dbReader =  SasDB::getInstance($db_config["slaves"][$slave_key]);
            
            if(true === $db_config["slaves"][$slave_key]["debug"]) {
                $this->dbReader->setDebug(true);
            }
            
            if(true === $db_config["slaves"][$slave_key]["log"]) {
                $this->dbReader->setLog(true);
            }
        }

        $table_name = isset($table_config["table"]) ? $table_config["table"] : $dao_name;
        $primary = isset($table_config["key"]) ? $table_config["key"] : ($table_name . "id");
        $fields = isset($table_config["fields"]) ? $table_config["fields"] : "*";

        if(isset($table_config["table_shard"]) && !empty($table_config["table_shard"])) {
            $table_name .= "_" . $this->getNumericHash($shard_value, $table_config["table_shard"]); 
        }

        $this->setTable($table_name);
        $this->setPrimary($primary);
        $this->setDefaultFields($fields);
    }/*}}}*/

    public static function getInstance() {/*{{{*/
		if((double)PHP_VERSION < 5.3) {
			throw new SasException("method [daoProxy::getInstance] is not supported by php:". PHP_VERSION);
        }

        return new static();
    }/*}}}*/

    private function getHostId($table_config, $shard_value) {/*{{{*/
        $host_id = 0;
        
        if(isset($table_config["host_id"])) {
            $host_id = $table_config["host_id"];
        }

        if(isset($table_config["db_shard"]) && !empty($table_config["db_shard"])) {
            foreach($table_config["db_shard"] as $k => $v) {
                if($shard_value <= $v) {
                    $host_id = $v;
                }
            }
        }

        return $host_id;
    }/*}}}*/

    public function getNumericHash($number, $base) {/*{{{*/
		return abs($number % $base);
	}/*}}}*/

	public function getStringHash($string, $base) {/*{{{*/
		$unsign = sprintf("%u", crc32($string));
        if ($unsign > 2147483647)
        {
			$unsign -= 4294967296;
		}
		return abs($unsign % $base);
	}/*}}}*/

    public function setTable($table) {/*{{{*/
        $this->table = $table;
        return $this;
    }/*}}}*/

    public function getTable() {/*{{{*/
        return $this->table;
    }/*}}}*/
  
	public function setAutoOrder($autoOrder = true) {/*{{{*/
		$this->autoOrder = $autoOrder;
        return $this;
	}/*}}}*/

	public function setPrimary($primary) {/*{{{*/
		$this->primary = $primary;
        return $this;
	}/*}}}*/
	
	public function getPrimary() {/*{{{*/
		return $this->primary;
	}/*}}}*/
  
	public function setCountField($field) {/*{{{*/
		$this->countField = $field;
        return $this;
	}/*}}}*/

	public function getCountField() {/*{{{*/
        $field = 1;
        if($this->countField) {
            $field = $this->countField;
            $this->countField= null;
        }

        return $field;
	}/*}}}*/

	public function setDefaultFields($fields) {/*{{{*/
		$this->defaultFields = $fields;
        return $this;
	}/*}}}*/

	public function setFields($fields) {/*{{{*/
		$this->fields = $fields;
        return $this;
	}/*}}}*/

	public function useIndex($index) {/*{{{*/
		$this->index= $index;
        return $this;
	}/*}}}*/
	
	public function useMaster($force_master = true) {/*{{{*/
		$this->forceMaster = $force_master;
        return $this;
	}/*}}}*/

	public function getFields() {/*{{{*/
        if($this->fields) {
            $fields = $this->fields;
            $this->fields = null;
        } else {
            $fields = $this->defaultFields;
        }

        return $fields;
	}/*}}}*/
  
	public function getDbReader() {/*{{{*/
        if($this->forceMaster) {
            $this->forceMaster = false;

            return $this->dbWriter;
        }

		return $this->dbWriter->isTrans() ? $this->dbWriter : $this->dbReader;
	}/*}}}*/

    public function setDebug($debug = true) {/*{{{*/
        $this->dbWriter->setDebug($debug);
        $this->dbReader->setDebug($debug);
        return $this;
    }/*}}}*/

    public function addRecord($info) {/*{{{*/
    	return $this->dbWriter->insert($this->getTable(), $info);
    }/*}}}*/
    
    public function resetRecord($info) {/*{{{*/
    	return $this->dbWriter->replace($this->getTable(), $info);
    }/*}}}*/

    public function setRecord($info, $relateid) {/*{{{*/
    	return $this->dbWriter->update($this->getTable(), $info, "{$this->getPrimary()}=?", $relateid);
    }/*}}}*/
 
    public function setRecordBy($info, $where, $params = array()) {/*{{{*/
    	return $this->dbWriter->update($this->getTable(), $info, $where, $params);
    }/*}}}*/
   
    public function getRecord($relateid) {/*{{{*/
    	$sql = "select {$this->getFields()} from {$this->getTable()} where {$this->getPrimary()}=?";
    	return $this->getDbReader()->getRow($sql, $relateid);
    }/*}}}*/

    public function getRecordBy($where, $params = array()) {/*{{{*/
    	$sql = "select {$this->getFields()} from {$this->getTable()} where $where";
    	return $this->getDbReader()->getRow($sql, $params);
    }/*}}}*/
    
    public function delRecord($relateid) {/*{{{*/
    	$sql = "delete from {$this->getTable()} where {$this->getPrimary()}=? limit 1";
    	return $this->dbWriter->execute($sql, $relateid);
    }/*}}}*/
   
    public function delRecordBy($where, $params = array()) {/*{{{*/
    	$sql = "delete from {$this->getTable()} where $where";
    	return $this->dbWriter->execute($sql, $params);
    }/*}}}*/
   
    public function getValue($field, $where, $params = array()) {/*{{{*/
        $sql = "select {$field} from {$this->getTable()} where {$where}";
        return $this->getDbReader()->getOne($sql, $params);
	}/*}}}*/

    public function exists($relateid) {/*{{{*/
        $sql = "select count(0) from {$this->getTable()} where {$this->getPrimary()}=?";
        return (int)$this->getDbReader()->getOne($sql, $relateid) > 0;
	}/*}}}*/
    
    public function existsBy($where, $params = array()) {/*{{{*/
        $sql = "select count(0) from {$this->getTable()} where $where";
        return (int)$this->getDbReader()->getOne($sql, $params) > 0;
	}/*}}}*/
  
    public function getCount($where = "", $params = array()) {/*{{{*/
        $sql = "select count(". $this->getCountField() .") from {$this->getTable()}";
        $sql.= $this->index ? " force key(".$this->index.") " : "";
    	$sql.= $where != "" ? " where " . $where : "";
        return (int)$this->getDbReader()->getOne($sql, $params, false);
	}/*}}}*/

    public function getAllRecords($start = 0, $num = 0, $order = "") {/*{{{*/
    	return $this->getRecords("", $start, $num, null, $order);
    }/*}}}*/
    
    public function getRecords($where = "", $start = 0, $num = 0, $params = array(), $order = "") {/*{{{*/
    	$sql = "select {$this->getFields()} from {$this->getTable()}";
        $sql.= $this->index ? " force key(".$this->index.") " : "";
    	$sql.= $where != "" ? " where " . $where : "";
        
        if($order) {
            $sql.= " order by " . $order;
        } elseif($this->autoOrder) {
            $sql.= " order by " . $this->getPrimary() . " desc";
        }

    	$sql.= $num > 0 ? " limit $start, $num" : "";
    	
    	return $this->getDbReader()->getAll($sql, $params, false);
    }/*}}}*/
 
    public function getList($where = "", $start = 0, $num = 0, $params = array(), $order = "") {/*{{{*/
        $sql = "select SQL_CALC_FOUND_ROWS {$this->getFields()} from {$this->getTable()}";
        $sql.= $this->index ? " force key(".$this->index.") " : "";
    	$sql.= $where != "" ? " where " . $where : "";

        if($order) {
            $sql.= " order by " . $order;
        } elseif($this->autoOrder) {
            $sql.= " order by " . $this->getPrimary() . " desc";
        }

    	$sql.= $num > 0 ? " limit $start, $num" : "";
    	
        $reader = $this->getDbReader();
    	$list = $reader->getAll($sql, $params, false);
    	$total = $reader->getOne("select FOUND_ROWS() as total");
    	
    	return array($total, $list);
    }/*}}}*/

    public function __call($name, $arguments) {/*{{{*/
        $db = (stripos($name, "get") === 0) ? $this->getDbReader(): $this->dbWriter;
        return call_user_func_array(array($db, $name), $arguments);
    }/*}}}*/
}
