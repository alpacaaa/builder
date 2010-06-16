<?php

	require_once(TOOLKIT . '/class.gateway.php');
	
	class BuilderJob {
	
		protected $action;
		protected $args = array();
		
		public function __construct(){ }
		
		public function execute()
		{
			$action = $this->action;
			$args = $this->args;
			
			if (!method_exists($this, $action)) {
				self::throwEx('Invalid action: '. $action);
			}
			
			call_user_func(array('self', $action), $args);
		}
		
		public static function downloadAndExtract($args)
		{
			$url = $args['url'];
			$file = $args['file'];
			$root = $args['root'];
			$dest = $root ? $root. '/'. $args['dest'] : $args['dest'];
			$timeout = $args['timeout'] ? $args['timeout'] : 10;
			$isSymphony = $args['release'] ? $args['release'] : false;
			
			if (!Gateway::isCurlAvailable()) {
				// downloads are broken without curl. don't know why...
				self::throwEx('Curl not available. Necessary to download files.');
			}
			
			if (!class_exists('ZipArchive')) {
				self::throwEx('Class ZipArchive not available. Cannot extract.');
			}
			
			if (!file_exists($file)) {
				$result = self::urlGet($url, $timeout);
				file_put_contents($file, $result);
			}
			
			$zip = new ZipArchive();
			if (!$zip->open($file)) {
				self::throwEx('Invalid archive: '. $file);
			}
			
			$files   = array();
			$length  = $zip->numFiles;
			$pattern = $isSymphony ? 'symphony' : '';
			
			for($i = 0; $i < $length; $i++) {
				$entry = $zip->getNameIndex($i);
				if (!$pattern || strpos($entry, $pattern) === 0)
					$files[] = $entry;
			}
			
			/* 
			 * Some extension are enclosed in their specific folders
			 * However other are not... we should check if
			 * extension.driver.php exists to empirically determine this..
			 */
			
			if (!$isSymphony && $zip->getFromName('extension.driver.php')) {
				$temp = explode('/', $url);
				$dir  = str_replace('.zip', '', array_pop($temp));
				$dest .= '/'. $dir;
				
				mkdir($dest);
			}
			
			if ($isSymphony) {
				$destBak = $dest;
				$path = TMP. '/'. md5($dest);
				$dest = $path;
				
				@mkdir($path);
			}
			
			$zip->extractTo($dest, $files);
			$zip->close();
			
			if ($isSymphony) {
				$src  = array_pop(glob($path. '/symphony*'));
				$dest = $destBak;
				
				self::recurse_copy($src, $dest);
			}
		}
		
		public static function gitClone($args)
		{
			$url = $args['url'];
			$ref = $args['ref'];
			$dest = $args['dest'];
			$root = $args['root'];
			$submodule = $args['submodule']; /* wtf? */
			
			if (!function_exists('shell_exec')) {
				self::throwEx('Unable to execute git.');
			}
			
			$version = shell_exec('git --version');
			if (substr($version, 0, 5) !== 'git v') {
				self::throwEx('Git is not available on your system.');
			}
			
			if ($root) $dest = $root. '/'. $dest;
			if (!is_dir($dest)) mkdir($dest, 0777, $recursive = true);
			
			$cmd = $submodule ? 'submodule add' : 'clone';
			$cd  = sprintf('cd %s && ', $dest);
			$dir = '.';
			
			$stack = shell_exec(
				sprintf('%sgit %s %s %s', $cd, $cmd, $url, $dir)
			);
			
			$cmd = 'submodule update --init';
			shell_exec(sprintf('%sgit %s', $cd, $cmd));
			
			if (!is_dir($dest. '/.git')) {
				self::throwEx('Something went wrong while cloning repository: '. $dest);
			}
			
			if ($ref) {
				shell_exec(
					sprintf('cd %s && git checkout %s', $dest, $ref)
				);
			}
		}
		
		public static function downloadUtility($args)
		{
			$url = $args['url'];
			$dir = $args['dir'];
			$file = $args['file'];
			$dest = $args['dest'];
			
			if (!file_exists($file)) {
				$result = self::urlGet($url);
				file_put_contents($file, $result);
			}
			
			if (!is_dir($dir)) {
				mkdir($dir, 0777, $recursive = true);
			}
			
			$result = $result ?
				$result :
				file_get_contents($file);
			
			file_put_contents($dir. '/'. $dest, $result);
		}
		
		public static function urlGet($url, $timeout = 7)
		{
			$ch = new Gateway();
			$ch->init();
			$ch->setopt('URL', $url);
			$ch->setopt('TIMEOUT', $timeout);
			$ch->setopt('USERAGENT', 'Symphony Builder');
		
			return $ch->exec();
		}
		
		public function setAction($action)
		{
			$this->action = $action;
		}
		
		public function setArgs(array $args)
		{
			$this->args = $args;
		}
		
		// Stolen from:
		// http://www.php.net/manual/en/function.copy.php#91010
		public static function recurse_copy($src,$dst) {
			$dir = opendir($src);
			@mkdir($dst);
			while(false !== ( $file = readdir($dir)) ) {
				if (( $file != '.' ) && ( $file != '..' )) {
					if ( is_dir($src . '/' . $file) ) {
						self::recurse_copy($src . '/' . $file,$dst . '/' . $file);
					}
					else {
						copy($src . '/' . $file,$dst . '/' . $file);
					}
				}
			}
			closedir($dir);
		} 
		
		protected static function throwEx($msg)
		{
			throw new Exception($msg);
		}
	}
