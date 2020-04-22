<?php
 class Files 
 {
     /**
      * ignore_block: Windows 不支持
      */
     public static function write($file, $contents = '', $ignore_block = false)
     {/*{{{*/
         self::mkdirs(dirname($file));

         if('' == $contents) {
             return touch($file);
         }

         $fp = fopen($file, "a");
         if(flock($fp, $ignore_block ? (LOCK_EX | LOCK_NB) : LOCK_EX)) {//LOCK_NB 只有抢到锁才更新, 主要用于写缓存文件, Windows 平台不支持
             $tmp_file = TMP_DIR .'/'. md5($file);
             //先写临时文件,然后rename
             if (file_put_contents($tmp_file, $contents)) {
                 // Win32 有可能无法rename成功，先unlink 
                 if(DIRECTORY_SEPARATOR==='\\' && is_file($file)) {
                     unlink($file);
                 }

                 rename($tmp_file, $file);
                 flock($fp, LOCK_UN);

                 @unlink($tmp_file);
             } else {//写临时文件失败
                 ftruncate($fp, 0);
                 fwrite($fp, $contents); 
                 fflush($fp);
                 flock($fp, LOCK_UN);
             }

             if("DEV" == MODE) {//解决开发环境 cli 和 nginx 使用不同用户时的权限问题
                 @chmod($file, 0777);
             }
         }

         fclose($fp);
     } /*}}}*/

     public static function mkdirs($dir, $mode=0777)
     {/*{{{*/
         if(!is_dir($dir)) {
             mkdir($dir, $mode, true);
         }

         @chmod($dir, $mode);
     }/*}}}*/

}
