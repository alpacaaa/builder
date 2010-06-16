<?php

	require_once(TOOLKIT . '/class.event.php');

	Class eventsave_queue extends Event{

		const ROOTELEMENT = 'save-queue';

		public $eParamFILTERS = array(

		);

		public static function about(){
			return array(
					 'name' => 'Save Queue',
					 'author' => array(
							'name' => 'Marco Sampellegrini',
							'email' => 'm@rcosa.mp'),
					 'version' => '1.0',
					 'release-date' => '2010-05-01T10:25:46+00:00',
					 'trigger-condition' => 'action[save-queue]');
		}

		public static function allowEditorToParse(){
			return false;
		}

		public static function documentation(){
			return '';
		}

		public function load(){
			if(isset($_POST['action']['save-queue'])) return $this->__trigger();
		}

		protected function __trigger(){
			// TODO: save queue!
			require_once EXTENSIONS. '/builder/lib/class.builderjob.php';
			require_once EXTENSIONS. '/builder/lib/Queue.php';

			$root = $_POST['root']; //Lang::createHandle($_POST['root']);
			if (!$root) self::missing('Install Folder');
			mkdir($root);

			$file = 'queue-'. md5(time(). $root);
			$queue = new Queue(array(
				'type' => 'SerialisedQueueStorage',
				'file' => TMP. '/'. $file
			));


			/* Symphony */
			$sym = $_POST['symphony-version'];
			if (!$sym) self::missing('Symphony Version');

			$info = extension_builder::installInfo($sym);
			$info->args['dest'] = $root;
			//echo 'Symphony Info: '; print_r($info);

			$job = new BuilderJob();
			$job->setAction($info->action);
			$job->setArgs($info->args);
			$queue->add($job, QueueItem::PRIORITY_HIGH);


			/* Extensions */
			$extensions = $_POST['Extension'] ?
				$_POST['Extension'] : array();

			foreach ($extensions as $id => $source) {
				$info = extension_builder::installInfo($source);
				$info->args['root'] = $root;
				$info->args['dest'] = 'extensions/'. $info->repo;
				//echo 'Ext '. $e. ': '; print_r($info);

				$job = new BuilderJob();
				$job->setAction($info->action);
				$job->setArgs($info->args);
				$queue->add($job);
			}


			/* Utilities */
			$utilities = $_POST['Utility'] ?
				$_POST['Utility'] : array();

			$dir = $root. '/workspace/utilities';

			foreach ($utilities as $id => $name) {
				$args = extension_builder::utilityInfo($name, $id);
				$args['dir'] = $dir;

				$job = new BuilderJob();
				$job->setAction('downloadUtility');
				$job->setArgs($args);
				$queue->add($job, QueueItem::PRIORITY_LOW);
			}

			redirect('?process-queue&action=exec&queue='. $file);
		}

		protected static function missing($what)
		{
			throw new Exception("Missing param '$what'");
		}

	}

