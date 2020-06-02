<?php
class Application 
{
	private $action; 
	private $controller; 
	private $module; //对controller分组，仅支持一级
	
	private $controllerClass;  
	private $controllerFileName;
	
	private $moduleAccessor;    
	private $controllerAccessor;    
	private $actionAccessor;    

    //module、controller、action 用一个参数表示，用字符隔开
	private $methodAccessor;
	         
	private static $instance; 

	private function __construct() {/*{{{*/
		$this->moduleAccessor     = MODULE_ACCESSOR;
		$this->controllerAccessor = CONTROLLER_ACCESSOR;
		$this->actionAccessor     = ACTION_ACCESSOR;
		$this->methodAccessor     = METHOD_ACCESSOR;
		$this->methodSeparator    = METHOD_SEPARATOR;
	}/*}}}*/

	/**
	 * The singleton method
	 * @return object Application
	 */
	public static function singleton() {/*{{{*/
		if (!isset(self::$instance)) {
            $c = __CLASS__;
			self::$instance = new $c;
        }

        return self::$instance;
	}/*}}}*/

	/**
	 * autoload loader
	 */
	public static function autoload($class) {/*{{{*/
        if(defined('AUTOLOAD_CACHED')) {
            $class = strtolower($class);
            $path = getClassPath($class);
            if(!empty($path)) {
                return include($path);
            }
        } elseif(is_file(SAS_DIR."/component/".$class.".php")) {
            return include(SAS_DIR."/component/".$class.".php"); 
        } else {
            throw new SasException("$class is not autoloaded");
        }
    }/*}}}*/

	private function parseMethod() {/*{{{*/
        $methods = Router::parse($_REQUEST);

        $this->module     = $methods["module"]; 
        $this->controller = $methods["controller"]; 
        $this->action     = $methods["action"]; 

        if($methods["path_params"]) {
            $_GET     = $_GET     + $methods["path_params"];
            $_REQUEST = $_REQUEST + $methods["path_params"];
        }

        define ('MODULE',$this->module);
        define ('CONTROLLER',$this->controller);
		define ('ACTION',$this->action);
	}/*}}}*/

	private function parseController() {/*{{{*/
        $controller_name = ucfirst($this->controller);
		$this->controllerFileName = APPLICATION_DIR  . "/src/controllers/" . ($this->module ? $this->module . "/" : "") . $controller_name . "Controller.php";

        if(defined('AUTOLOAD_CACHED')) {
            $reg_controllers = getRegControllers();
            if($this->module) {
                if(!isset($reg_controllers[$this->module][$controller_name])) {
                    throw new SasFileException("Sorry, can't found controller file: " . $this->controllerFileName . " !");
                }
            } elseif(!isset($reg_controllers[$controller_name])) {
                throw new SasFileException("Sorry, can't found controller file: " . $this->controllerFileName . " !");
            }
        } else {
            if(!is_file($this->controllerFileName)) {
                throw new SasFileException("Sorry, can't found controller file: " . $this->controllerFileName . " !");
            } 
        }

		include($this->controllerFileName);
		$this->controllerClass = $this->controller . "Controller";
	}/*}}}*/

    /**
     * strip slashes and whitespace
     * @param array $array
     * @return array
     */
    private function stripVars(&$array, $mqg=0) {/*{{{*/
    	foreach ($array as $key => $value) {
    		!is_array($value) ? ($array[$key] = ($mqg ? stripcslashes(trim($value)) : trim($value))): ($this->stripVars($array[$key], $mqg)) ;
    	} 
    }/*}}}*/

	/**
     * Parse request vars
     */
    private function parseRequest() {/*{{{*/
		ini_set('magic_quotes_runtime',0);
        $mqg = function_exists("get_magic_quotes_gpc") && get_magic_quotes_gpc(); 

        $_GET     && $this->stripVars($_GET, $mqg);
        $_REQUEST && $this->stripVars($_REQUEST, $mqg);

        if($_POST) {
            $this->stripVars($_POST, $mqg);
        } elseif($_SERVER["REQUEST_METHOD"] == "POST" && false !== stripos($_SERVER['CONTENT_TYPE'], "application/json")) {//解析json格式的post数据
            if($post_data_str = file_get_contents('php://input')) {
                if($post_data = json_decode($post_data_str, true)) {
                    $_POST = $post_data;
                    $this->stripVars($_POST, $mqg);
                    $_REQUEST = $_REQUEST + $_POST;
                }
            }
        }
    }/*}}}*/
	
	/**
	 * Init Application
	 */
	private function init() {/*{{{*/
		$this->parseMethod();
		$this->parseRequest();
        $this->parseController();
	}/*}}}*/
	
	/**
	 * Run Application
	 */
	public function run() {/*{{{*/
        if (USE_GZIP && extension_loaded('zlib') && (strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip')!== FALSE)) {			
			if(ini_set('zlib.output_compression', 1)!==false) {
				ini_set('zlib.output_compression_level', '9');
				ini_set('zlib.output_handler', '');
				ob_start();
			} else {
				ob_start('ob_gzhandler');
			}
		} else {
			ob_start();
		}

        //init Application
		$this->init();

        try{
            //run controller
            $controller = new $this->controllerClass();
            $action_name = $this->action . "Action"; 
            if(!method_exists($controller, $action_name)) {
                throw new SasException("method $action_name is not exists!");
            }

            $controller->$action_name();
        } catch(SasRunException $e) {
            if(method_exists($this->controllerClass, "renderError")) {
                call_user_func(array($this->controllerClass, "renderError"), $e);
            } else {
                throw $e;
            }
        }
	}/*}}}*/
	
	/**
	 * Prevent users to clone the instance
	 */ 
    public function __clone() {
        trigger_error('Clone is not allowed.', E_USER_ERROR);
    }
}

spl_autoload_register(array('Application','autoload'));
