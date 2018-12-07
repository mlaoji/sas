<?php
 class Files 
 {
 	public static function write($file, $contents='', $mode='w') {/*{{{*/
		if ($contents=='') {
			Files::mkdirs(dirname($file));
			return touch($file);
		} elseif($mode=='w') {
			$tmpFile = DIR_FS_TMP .'/'. uniqid('');
			if (!(file_put_contents($tmpFile, $contents))) {
				throw new SasFileException("can't open temp file: $tmpFile");
			}
			// Win32 can't rename over top another file
			//if(strtoupper(substr(PHP_OS, 0, 3)) == 'WIN' && is_file($file))
			if(DIRECTORY_SEPARATOR==='\\' && is_file($file)) {
				unlink($file);
			} else {
				Files::mkdirs(dirname($file));
			}

			rename($tmpFile, $file);
			@unlink($tmpFile);
			chmod($file, 0755);
		} else {
			Files::mkdirs(dirname($file));
			$handle = fopen($file, $mode);
    		fwrite($handle, $contents);
    		fclose($handle);
		}
        
        return true;
 	} /*}}}*/

	public static function touch($file, $mtime = null) {/*{{{*/
		Files::mkdirs(dirname($file));
		return $mtime?touch($file,$mtime):touch($file);
 	} /*}}}*/
    
    public static function mkdirs($dir,$mode=0777) {/*{{{*/
		if(!is_dir($dir)) mkdir($dir, $mode, true);
		@chmod($dir,$mode);
	}/*}}}*/

}
