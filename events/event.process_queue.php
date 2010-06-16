<?php

	require_once(TOOLKIT . '/class.event.php');

	Class eventprocess_queue extends Event{

		const ROOTELEMENT = 'process-queue';

		public $eParamFILTERS = array(

		);

		public static function about(){
			return array(
					 'name' => 'Process Queue',
					 'author' => array(
							'name' => 'Marco Sampellegrini',
							'email' => 'm@rcosa.mp'),
					 'version' => '1.0',
					 'release-date' => '2010-05-01T10:25:55+00:00',
					 'trigger-condition' => 'action[process-queue]');
		}

		public static function allowEditorToParse(){
			return false;
		}

		public static function documentation(){
			return '';
		}

		public function load(){
			if(isset($_GET['process-queue'])) return $this->__trigger();
		}

		protected function __trigger(){

			require_once EXTENSIONS. '/builder/lib/class.builderjob.php';
			require_once EXTENSIONS. '/builder/lib/Queue.php';

			$action = $_GET['action'];
			if ($action == 'add') $this->_testAdd();
			if ($action == 'install') $this->_testInstall();
			if ($action == 'exec') $this->_testExecute();
			if ($action == 'dbg') $this->_debug();
		}

		public function _testAdd()
		{
			$q = new Queue(array(
				'type' => 'SerialisedQueueStorage',
				'file' => TMP. '/test_queue'
			));

			$job  = new BuilderJob();
			$job->setAction('download');
			$job->setArgs(array(
				'url' => 'http://downloads.symphony-cms.com/extension/44156/collapse_fields.zip',
				'file' => TMP. '/collapse_fields.zip'
			));

			$q->add($job);

			$job  = new BuilderJob();
			$job->setAction('extract');
			$job->setArgs(array(
				'file' => TMP. '/collapse_fields.zip',
				'dest' => TMP. '/www/'
			));

			$q->add($job);
		}

		public function _testInstall()
		{
			$q = new Queue(array(
				'type' => 'SerialisedQueueStorage',
				'file' => TMP. '/test_queue'
			));

			$root = TMP. '/www';

			/* Symphony */
			$job  = new BuilderJob();
			$job->setAction('gitClone');
			$job->setArgs(array(
				'url'  => 'git://github.com/symphony/symphony-2.git',
				'dest' => $root
			));

			$q->add($job);

			require_once EXTENSIONS. '/builder/extension.driver.php';
			$store = extension_builder::cookie()->get('store');
			$extensions = $store['Extension'];

			foreach ($extensions as $e) {
				$info = explode('|', $e['source']);
				$url  = 'git://github.com/'.$info[1].'/'.$info[2].'.git';
				$dir  = $info[2];

				$job  = new BuilderJob();
				$job->setAction('gitClone');
				$job->setArgs(array(
					'url'  => $url,
					'dest' => 'extensions/'. $dir,
					'commit' => $info[3],
					'root' => $root,
					/*'submodule' => true*/
				));

				$q->add($job);
			}
		}

		public function _testExecute()
		{
			$file = $_GET['queue'];
			$q = new Queue(array(
				'type' => 'SerialisedQueueStorage',
				'file' => TMP. '/'. $file
			));

			if (!$q->hasNext()){
				unlink(TMP. '/'. $file);
				return redirect('?build=complete'); //return redirect('?process-queue&action=dbg');
			}

			$item = $q->next();

			$item->activate();
			$item->getObject()->execute();
			$item->setStatus(QueueItem::STATUS_SUCCESS);

			$q->update($item);

			redirect('?process-queue&action=exec&queue='. $file);
		}

		public function _debug()
		{
			$q = new Queue(array(
				'type' => 'SerialisedQueueStorage',
				'file' => TMP. '/'. $GET['file']
			));

			print_r($q); die();
		}

	}

