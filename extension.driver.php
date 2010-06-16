<?php

	Class extension_builder extends Extension{

		protected static $cookie;

		public function about(){
			return array(
				'name' => 'Builder',
				'version' => '1.0',
				'release-date' => '2010-04',
				'author' => array(
					'name' => 'Marco Sampellegrini',
					'email' => 'm@rcosa.mp'
				)

			);
		}

		public static function filter($what)
		{
			if (is_array($what)) {
				$what = array_filter($what);
				$what = array_map(array('self', 'filter'), $what);
				$what = array_filter($what);
			}

			return $what;
		}

		public static function addToNode($param, XMLElement $node)
		{
			if (empty($param)) return;
			if (!is_array($param)) {
				return $node->setValue(htmlspecialchars(stripslashes($param)));
			}

			$key = key($param);
			$val = current($param);
			unset($param[$key]);

			$new = new XMLElement('key', null, array('handle' => $key));
			$node->appendChild($new);

			self::addToNode($val, $new);
			self::addToNode($param, $node);
		}

		public static function store(array $array)
		{
			$cookie = self::cookie();
			$old = $cookie->get('store');
			if (!is_array($old)) $old = array();

			$array = self::filter(array_replace_recursive($old, $array));
			$cookie->set('store', $array);
		}

		public static function cookie()
		{
			if (!self::$cookie)
				self::$cookie = new Cookie('store', TWO_WEEKS, __SYM_COOKIE_PATH__);

			return self::$cookie;
		}

		public static function installInfo($str)
		{
			$temp = explode('|', $str);
			$info = new StdClass();
			$info->args = array();

			$type = strtolower($temp[0]);
			if ($type == 'release') {

				require_once(TOOLKIT . '/class.gateway.php');
				$url = $temp[1];

				$ch = new Gateway();
				$ch->init();
				$ch->setopt('URL', $url);
				$result = $ch->exec();

				$doc = new DOMDocument();
				$doc->loadXML($result);

				$xpath = new DOMXPath($doc);
				$xpath->registerNamespace('xhtml', 'http://www.w3.org/1999/xhtml');
				$query = "//xhtml:a[@class = 'action download']";
				$nodes = $xpath->query($query);
				$count = $nodes->length;

				if ($count !== 1) {
					throw new Exception(
						'An error occurred while determining Symphony
						 download url ('. $url. ')'
					);
				}

				$link = $nodes->item(0);
				$temp[1] = $link->getAttributeNode('href')->value; // hackish -.-
				$type = 'website';
				$info->args['release'] = true;
			}

			if ($type == 'website') {
				$url  = $temp[1];
				$file = CACHE. '/'. md5($url). '.zip';

				$info->args['url']  = $url;
				$info->args['file'] = $file;
				$info->action = 'downloadAndExtract';

				return $info;
			}

			$info->user = $temp[1];
			$info->repo = $temp[2];
			$url = sprintf(
				'git://github.com/%s/%s.git', $info->user, $info->repo
			);

			$info->args['url'] = $url;
			$info->args['ref'] = $temp[3];
			$info->action = 'gitClone';

			return $info;
		}

		public static function utilityInfo($name, $id)
		{
			$file = Lang::createHandle($name);
			$url  = sprintf(
				'http://symphony-cms.com/download/xslt-utilities/source-code/%s/',
				$id
			);
			$cached = CACHE. '/'. md5($file). '.xsl';

			return array(
				'url'  => $url,
				'file' => $cached,
				'dest' => $file. '.xsl'
			);
		}

		public function getSubscribedDelegates(){
			return array(
				array(
					'page' => '/frontend/',
					'delegate' => 'FrontendParamsResolve',
					'callback' => 'addDirName'
				)
			);
		}

		public function addDirName($array){
			$array['params']['dirname'] = DOCROOT;
		}
	}

	if (!function_exists('array_replace_recursive'))
		require_once 'lib/function.array_replace_recursive.php';
