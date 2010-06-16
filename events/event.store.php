<?php

	require_once(TOOLKIT . '/class.event.php');

	Class eventstore extends Event{

		const ROOTELEMENT = 'store';

		public $eParamFILTERS = array(

		);

		public static function about(){
			return array(
					 'name' => 'Store',
					 'author' => array(
							'name' => 'Marco Sampellegrini',
							'email' => 'm@rcosa.mp'),
					 'version' => '1.0',
					 'release-date' => '2010-04'
			);
		}

		public static function allowEditorToParse(){
			return false;
		}

		public static function documentation(){
			return '';
		}

		public function load(){
			if(isset($_REQUEST['store'])) return $this->__trigger();
		}

		protected function __trigger(){
			$builder = Frontend::instance()->ExtensionManager->create('builder');
			$array = $_REQUEST['store'];
			if (!is_array($array)) $array = array();

			$builder->store($array);
		}

	}

