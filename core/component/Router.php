<?php
class Router {
	public static function parse($params, $path = "") {/*{{{*/
        $path = $path ? $path : $_SERVER["REQUEST_URI"];

        if("" != $path  && "/" != $path) {
            list($path,) = explode("?", $path, 2);
            $path_info = explode("/", trim($path, "/"));

            if(count($path_info) > 0 && ENTRY_FILE != end($path_info)) {
                $controller = array_shift($path_info);
                $action = array_shift($path_info);
                if(!$action) {
                    $action = DEFAULT_ACTION;
                }

                $path_params = array();
                while($path_info) {
                    $k = array_shift($path_info);
                    $v = array_shift($path_info);

                    $path_params[$k] = $v; 
                }

                return array("controller" => $controller, "action" => $action, "path_params" => $path_params);
            }
        }

        if(isset($params[METHOD_ACCESSOR])) {
            $methods = explode(METHOD_SEPARATOR, $params[METHOD_ACCESSOR]);
            $counts = count($methods);
            switch($counts) {
            case 0:
                $controller = DEFAULT_CONTROLLER;
                $action = DEFAULT_ACTION;
                break;
            case 1:
                $controller = $methods[0];
                $action = DEFAULT_ACTION;
                break;
            case 2:
                $controller = $methods[0];
                $action = $methods[1];
                break;
            default:
                $controller = $methods[0];
                $action = $methods[1];
            }
        } else {
            $controller = ("" != $params[CONTROLLER_ACCESSOR]) ? $params[CONTROLLER_ACCESSOR] : DEFAULT_CONTROLLER;
            $action = ("" != $params[ACTION_ACCESSOR]) ? $params[ACTION_ACCESSOR] : DEFAULT_ACTION;
        }

        return array("controller" => $controller, "action" => $action);
	}/*}}}*/
}
