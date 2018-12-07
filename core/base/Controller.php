<?php
abstract class Controller implements IController{
    
    private static $startTime = 0;

    public function __construct(){}
    
    public function getControllerName() {/*{{{*/
        return CONTROLLER;
    }/*}}}*/

    public function getActionName() {/*{{{*/
        return ACTION;
    }/*}}}*/

    public function getParam($key = "", $default_value = "") {/*{{{*/
        return isset($_REQUEST[$key]) ? $_REQUEST[$key] : $default_value;
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
