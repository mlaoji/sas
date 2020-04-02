<?php
/**
 * 默认常量配置, 可在项目中重新定义
 *
 * DIR_FS_* = Filesystem directories (local/physical)
 * DIR_WS_* = Webserver directories (virtual/URL)
 */
!defined("USE_GZIP") && define("USE_GZIP", true);

!defined("ENTRY_FILE") && define("ENTRY_FILE", "index.php");
!defined("CONTROLLER_ACCESSOR") && define("CONTROLLER_ACCESSOR", "c");
!defined("ACTION_ACCESSOR") && define("ACTION_ACCESSOR", "a");
!defined("METHOD_ACCESSOR") && define("METHOD_ACCESSOR", "m");
!defined("METHOD_SEPARATOR") && define("METHOD_SEPARATOR", ".");
!defined("SAS_VARCACHE_TTL") && define("SAS_VARCACHE_TTL", 300);

!defined("DEFAULT_ACTION") && define("DEFAULT_ACTION", "index");
!defined("DEFAULT_CONTROLLER") && define("DEFAULT_CONTROLLER", "index");

!defined("DIR_FS_TMP") && define("DIR_FS_TMP",APPLICATION_DIR . '/tmp');
!defined("WEB_DIR") && define("WEB_DIR", '');

!defined("AUTOLOAD_PATH") && define("AUTOLOAD_PATH", "");//项目中自动加载的目录，多个用英文逗号分隔, 默认加载 src/component, src/models 



