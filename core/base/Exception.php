<?php
class SasException extends RuntimeException
{/*{{{*/
    public function __construct($msg, $code = "-1") {
        parent::__construct($msg, $code);
    }
}/*}}}*/

class SasClassException extends SasException
{/*{{{*/
    const ERR_CODE = 1;
    public function __construct($msg='') {
        empty($msg) && $msg = 'Class or method not found!';
        parent::__construct($msg, self::ERR_CODE);
    }
}/*}}}*/

class SasFileException extends SasException
{/*{{{*/
    const ERR_CODE = 2;
    public function __construct($msg='') {
        empty($msg) && $msg = 'File not found!';
        parent::__construct($msg, self::ERR_CODE);
    }
}/*}}}*/

class SasRunException extends SasException
{/*{{{*/
    private $args;
    private $data;

    public function __construct($errno, $args = array(), $data = array()) {
        if(!is_numeric($errno)) {
            $error = $errno;
            $errno = "-1";
        } else {
            $error = self::getError($errno, $args);
        }
        parent::__construct($error, $errno);
        $this->setArgs($args);
        $this->setData($data);
    }

    public function getArgs() {/*{{{*/
        return $this->args;
    }/*}}}*/

    public function getData() {/*{{{*/
        return $this->data;
    }/*}}}*/

    public function setArgs($args) {/*{{{*/
        $this->args = $args;
    }/*}}}*/

    public function setData($data) {/*{{{*/
        $this->data = $data;
    }/*}}}*/

    public static function getError($errno, $args = array()) {/*{{{*/
        $errors = Config::get("ERROR_CONF");
        $args  = $args ? (is_array($args) ? $args : array($args)) : array();
        return isset($errors[$errno]) ? (empty($args) ? $errors[$errno] : vsprintf($errors[$errno], $args)) : ("未定义的错误码:" . $errno);
    }/*}}}*/
}/*}}}*/

