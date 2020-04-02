<?php
abstract class Controller implements IController{
    
    private static $startTime = 0;
    private static $strip_tags= false;//是否对参数进行strip_tags过滤

    public function __construct(){}
    
    public function getControllerName() {/*{{{*/
        return CONTROLLER;
    }/*}}}*/

    public function getActionName() {/*{{{*/
        return ACTION;
    }/*}}}*/

    public function setStripTags($flag = true) {/*{{{*/
        return $this->strip_tags = $flag;
    }/*}}}*/

    //strip_tags 参数会忽略 setStripTags 的全局设置
    public function getParam($key = "", $default_value = "", $strip_tags = null/*bool*/) {/*{{{*/
        $val = isset($_REQUEST[$key]) ? $_REQUEST[$key] : $default_value;
        return $val && (is_bool($strip_tags) ? $strip_tags : $this->strip_tags) ? trim(strip_tags($val)) : $val;
    }/*}}}*/

    public function getInt($key = "", $default_value = "") {/*{{{*/
        return (int)$this->getParam($key, $default_value, false);
    }/*}}}*/

    public function getPostParam($key = "", $default_value = "") {/*{{{*/
        return isset($_POST[$key]) ? $_POST[$key] : $default_value;
    }/*}}}*/

    public function getSliceParam($key = "", $default_value = array(), $seprator = ",") {/*{{{*/
        $slice = array();
        if(isset($_REQUEST[$key])) {
            if($_REQUEST[$key]) {
                $slice = explode($seprator, $_REQUEST[$key]);
            }
        } else {
            $slice = $default_value;
        }

        return $slice;
    }/*}}}*/

    public function getParams() {/*{{{*/
        return $_REQUEST;
    }/*}}}*/

    public function getPostParams() {/*{{{*/
        return $_POST;
    }/*}}}*/

    public function getPostData() {/*{{{*/
        return file_get_contents('php://input');
    }/*}}}*/

    public function assign($key, $val = null, $html_encode = true) {/*{{{*/
        Container::find("Template")->assign($key, $val, $html_encode);
    }/*}}}*/
 
    public function render($tplname = "") {/*{{{*/
        Container::find("Template")->render($tplname);
    }/*}}}*/

    public static function renderError($e) {}

	public static function consumeStart() {/*{{{*/
		self::$startTime = microtime(true);	
	}/*}}}*/
	
	public static function getConsumeTime() {/*{{{*/
		return round((microtime(true) - self::$startTime) * 1000);
	}/*}}}*/

    public function __call($name, $parameter) {/*{{{*/
		throw new SasClassException("Sorry, can't found your request method: $name");
	}/*}}}*/
}
