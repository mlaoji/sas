<?php
/**
 * 默认常量配置, 可在项目中重新定义
 *
 */
!defined("USE_GZIP") && define("USE_GZIP", true);

!defined("ENTRY_FILE") && define("ENTRY_FILE", "index.php");
!defined("MODULE_ACCESSOR") && define("MODULE_ACCESSOR", "g");
!defined("CONTROLLER_ACCESSOR") && define("CONTROLLER_ACCESSOR", "c");
!defined("ACTION_ACCESSOR") && define("ACTION_ACCESSOR", "a");
!defined("METHOD_ACCESSOR") && define("METHOD_ACCESSOR", "m");
!defined("METHOD_SEPARATOR") && define("METHOD_SEPARATOR", ".");
!defined("SAS_VARCACHE_TTL") && define("SAS_VARCACHE_TTL", 300);

!defined("DEFAULT_ACTION") && define("DEFAULT_ACTION", "index");
!defined("DEFAULT_CONTROLLER") && define("DEFAULT_CONTROLLER", "index");
!defined("DEFAULT_MODULE") && define("DEFAULT_MODULE", "");

!defined("SYS_TMP_DIR") && define("SYS_TMP_DIR", '/tmp');//系统缓存目录
!defined("TMP_DIR") && define("TMP_DIR", APPLICATION_DIR . '/tmp');
!defined("WEB_DIR") && define("WEB_DIR", '');

!defined("AUTOLOAD_PATH") && define("AUTOLOAD_PATH", "");//项目中自动加载的目录，多个用英文逗号分隔, 默认加载 src/component, src/models 

