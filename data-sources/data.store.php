<?php

	require_once(TOOLKIT . '/class.datasource.php');

	Class datasourcestore extends Datasource{

		public $dsParamROOTELEMENT = 'store';
		public $_dependencies = array();

		public function about(){
			return array(
					 'name' => 'Store',
					 'author' => array(
							'name' => 'Marco Sampellegrini',
							'email' => 'm@rcosa.mp'),
					 'version' => '1.0',
					 'release-date' => '2010-04'
			);
		}

		public function allowEditorToParse(){
			return false;
		}

		public function grab(){
			$result = new XMLElement($this->dsParamROOTELEMENT);
			$builder = Frontend::instance()->ExtensionManager->create('builder');
			$store = $builder->cookie()->get('store');

			$builder->addToNode($store, $result);
			return $result;
		}
	}

