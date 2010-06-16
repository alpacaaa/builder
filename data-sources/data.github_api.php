<?php

	require_once(TOOLKIT . '/class.datasource.php');

	Class datasourcegithub_api extends Datasource{

		public $dsParamROOTELEMENT = 'github-api';
		public $dsParamURL = '?user={$user:$url-user}&repo={$repo:$url-repo}';
		public $dsParamXPATH = '/';
		public $dsParamCACHE = '9999';
		public $dsParamTIMEOUT = '6';
		public function __construct(&$parent, $env=NULL, $process_params=true){
			parent::__construct($parent, $env, $process_params);
			$this->_dependencies = array();
			$this->dsParamURL = 'http://'. DOMAIN. '/extensions/builder/github.php'. $this->dsParamURL;
		}

		public function about(){
			return array(
					 'name' => 'Github API',
					 'author' => array(
							'name' => 'Marco Sampellegrini',
							'email' => 'm@rcosa.mp'),
					 'version' => '1.0',
					 'release-date' => '2010-04-18T16:22:19+00:00');
		}

		public function getSource(){
			return 'dynamic_xml';
		}

		public function allowEditorToParse(){
			return false;
		}

		public function grab(&$param_pool=NULL){
			$result = new XMLElement($this->dsParamROOTELEMENT);

			try{
				include(TOOLKIT . '/data-sources/datasource.dynamic_xml.php');
			}
			catch(FrontendPageNotFoundException $e){
				// Work around. This ensures the 404 page is displayed and
				// is not picked up by the default catch() statement below
				FrontendPageNotFoundExceptionHandler::render($e);
			}
			catch(Exception $e){
				$result->appendChild(new XMLElement('error', $e->getMessage()));
				return $result;
			}

			if($this->_force_empty_result) $result = $this->emptyXMLSet();
			return $result;
		}
	}

