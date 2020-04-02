<?php
class Template
{
	private $tplDir;
	private $tplName;
	private $tplVars;
	private $tplSurfix;
    
    public function __construct() {
		$this->tplDir = APPLICATION_DIR . "/src/templates";          
        $this->tplSurfix = ".htm";
    }
    
    /**
     * set tpl dir
     * @param $dir
     */
    public function setTplDir($dir) {	
    	$this->tplDir = $dir;
    }

     /**
     * set tpl surfix 
     * @param $surfix
     */
    public function setTplSurfix($surfix) {	
    	$this->tplSurfix= $surfix;
    }

    /**
     * get tpl surfix 
     * @return $surfix
     */
    public function getTplSurfix() {	
    	return $this->tplSurfix;
    }

    /**
     * set tpl name
     * @param string $tplName
     */
    public function setTpl($tplName) { 	
		$this->tplName = $tplName;
    }
   
    /**
     * assign tpl var
     * @param mix $varName
     * @param mix $value
     */
    public function assign($varName, $value=null, $encode = true) {/*{{{*/
        if(is_array($varName)) {
           foreach($varName as $k => $v) {
                if($k != '') {
                    $this->tplVars[$k] = ($v && (is_bool($value) ? $value : $encode)) ? $this->_htmlencode($v) : $v;
                }
            }
        } elseif($varName != '') {
            $this->tplVars[$varName] = $encode ? $this->_htmlencode($value) : $value;
        }
    }/*}}}*/
    
    private function _htmlencode($value) {/*{{{*/
        if(is_array($value)) {
            foreach($value as $k=>$v) {
                $value[$k] = $this->_htmlencode($v); 
            }
        } else {
            $value = htmlspecialchars($value);
        }

        return $value;
    }/*}}}*/

	 /**
     * render tpl page
     */
    public function render($tplname = "") {/*{{{*/
        if($this->tplVars) {
            extract($this->tplVars);
        }

        if($tplname) {
            $this->setTpl($tplname);
        } elseif(!$this->tplName) {
            $this->setTpl(strtolower(CONTROLLER . "_" . ACTION) . $this->tplSurfix);
        }

        if(is_file($this->tplDir.'/'.$this->tplName)) {
            include($this->tplDir.'/'.$this->tplName);
        } else {
            throw new SasFileException("Template file [$this->tplName] not found");
        }
    }/*}}}*/
}
