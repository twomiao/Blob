<?php

// 处理CLI 模式下乱码问题
function cmdInfo($text) {
	$info = strtolower(php_uname('S'));
	if (strstr($info, 'windows')) {
		return die(iconv('UTF-8', 'GBK', $text));
	}
	return $text;
}

function toWinGbk($text) {
	return iconv('UTF-8', 'GBK', $text);
}

 class Blob {
 	private static $startTime   = 0;	        // 运行开始时间
 	private static $fileNameCsv = '';	        // 获取数据csv文件
 	private static $count       = 0;	        // 图片总张数
 	private static $imageDir    = '../images/';	// 图片存储位置

 	// 记录文件错误次数, 默认是0
 	private static $err         = 0;	
 	// 错误文件数统计
 	private static $errFile     = array();
 	// 错误文件数超过，自己设置才记录在日志文件中, 默认值是12 
 	public static $blobErr      = 12;

    function __construct() {
		self::$startTime = self::start_run_time();
 	}

	// 自定义规则命名
	public static function nameToSearch($oldName='', $save_image_to='') { 
		$oldName = trim($oldName);
		if (!$save_image_to) {
			cmdInfo('请设置你的文件名或者图片保存位置!'.PHP_EOL);
		}
		$len = 0;
		if ($oldName && $save_image_to) {
			$save_image_to = toWinGbk($save_image_to);
			$files = glob($save_image_to.'*'.$oldName.'*.{jpg}', GLOB_BRACE);
			$len = count($files);
			if (empty($files)) {
				self::$errFile[] = $oldName;
				if ((self::$err++) > self::$blobErr) {
					$data = implode(self::$errFile, ', ');
					@file_put_contents('Blob.log',$data);
				}
			}

			foreach ($files as $k => $oldname) {
				if ($k == 0) {
					$newname = $save_image_to.$oldName.'.jpg';
				} else {
					$newname = $save_image_to.$oldName.'_'.$k.'.jpg';
				}

				if (file_exists($newname)) {
					$str = toWinGbk('[ 跳过 ] - ').
						   self::getFileName($oldname).
						   		' ====> '.
						   self::getFileName($newname);
					echo $str.PHP_EOL;
					continue;
				} 

				if (!file_exists($newname)) {
					$str = rename($oldname, $newname) ? 
						   toWinGbk('[ 成功 ] - ').
						   self::getFileName($oldname).' ====> '.
						   self::getFileName($newname) : 
						   toWinGbk('[ 失败 ] - ').
						   self::getFileName($oldname).' ====> '.
						   self::getFileName($newname) ;
					echo $str.PHP_EOL;
				}	
			}
		}
		return $len;
	}

	// d:/1.jpg --> 1.jpg
	public static function getFileName($str) {
		if ($str != "") {
			$ext = pathinfo($str)['extension'];
			if ($ext) {
				return pathinfo($str)['basename'];
			}
		}
		return false;
	}

	// 导出CSV文件内容到内存
	public static function openCsv($files=[], $col=-1) {
		if (!is_numeric($col)) {
	    	die(('Error: 暂只支持数字格式.'.PHP_EOL));
	    }
	    $tmp  = []; 
		$cols = [];
		foreach ($files as $file) {
		$file = toWinGbk($file);
	    if (!file_exists($file)) {
	        cmdInfo('Error: 文件不存在[ '.$file.' ]'.PHP_EOL);
	    }

	     $fp = fopen($file,"r");

	     if ($col >= 0) {
	     	while($lists[] = fgetcsv($fp)[$col]) {}
	     }

	    if ($col == -1) {
	    	while($lists[] = fgetcsv($fp)) {}
	    }
		$len = count($lists);
		$tmp = array_merge($tmp, array_splice($lists, 1, $len));
	}
	if ($col != -1) {
		foreach ($tmp as $v) {
			if (preg_match('/[\d]+/i', $v)) {
				array_push($cols, $v);
			}
		}
	}
     return !empty($cols) ? $cols : $tmp;
   }

	# 计算开始运行时间
	private static function start_run_time() {
	    $start_time = explode(' ',microtime());
	    return $start_time;
	}

	# 计算结束时间
	private static function end_run_time($startTime, $len=3) {
		date_default_timezone_set('PRC'); 
	    $end_time = explode(' ',microtime());
	    $nowtime  = ($end_time[0] + $end_time[1]) - ($startTime[0]+$startTime[1]);
	    $nowtime  = round($nowtime, $len);
	    $success   = self::$count - self::$err;
	    $runInfo  = PHP_EOL.date('Y-m-d H:i:s',time()).
		    		   ",总文件数 [ ".self::$count."张 ], 成功文件数 ".$success.
		    		   " ,失败文件数 ".self::$err.", 耗时 ".$nowtime." 秒完成!";

		if (self::$err > 0) {
			echo toWinGbk('失败文件数统计：[ '. implode(self::$errFile, ', '). ' ].'.PHP_EOL);
		}
	  
	    echo toWinGbk($runInfo);
        if (self::$err > self::$blobErr) {
	    	echo PHP_EOL.'-------------------------'.PHP_EOL;
	    	echo toWinGbk('错误文件数过多,已记录在日志文件 [ Blob.log ].'.PHP_EOL);
	    	echo '-------------------------'.PHP_EOL;
	    }
	}

	# 验证CMD传入CSV文件有效性
	public static function checkCsvExt($files = '') {
		$files = explode(',', $files);
		foreach ($files as $file) {
			$ext = pathinfo(strtolower($file));
			if (empty($ext['extension'])) {
				cmdInfo('Error: 请指明你的文件位置!'.PHP_EOL);
			}

			if (!in_array($ext['extension'], ['csv'])) {
			    cmdInfo('Error: 文件不合法，只支持csv文件。非法文件是'.$file.PHP_EOL);
			}
			$fullFile[] = !strstr($file, '..\\') ? $file : '..\\'.$file;
		}
		return $fullFile;
	}

	public function exec($fileName, $pos=3) {
		
		// 验证合法csv文件
		self::$fileNameCsv =  self::checkCsvExt($fileName); 
		// 运行环境监测结束
		$lists = self::openCsv(self::$fileNameCsv, $pos);
		foreach ($lists as $sku) {
			self::$count += self::nameToSearch($sku, self::$imageDir);
		}
		self::end_run_time(self::$startTime, 3);
	}
}

// ==================== CLI 环境检测 ====================

if (php_sapi_name() != 'cli') {
	header('Content-Type:text/html;charset=utf-8;');
	cmdInfo('只支持PHP-CLI模式.'.'<br />');
}

if (empty($argv[1])) {
	cmdInfo('[错误!]: php script [file] [index].');
}

if (empty($argv[2])) {
	cmdInfo('[错误!]:请指明你的查询的索引.');
}

// ==================== CLI 下执行 ====================

$blob = new Blob();
$fileName = $argv[1];
$pos = $argv[2];
$blob->exec($fileName, $pos);

