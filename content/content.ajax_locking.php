<?php

	require_once(TOOLKIT . '/class.administrationpage.php');
	require_once(TOOLKIT . '/class.entrymanager.php');
	require_once(TOOLKIT . '/class.authormanager.php');
	
	class ContentExtensionPessimistic_DB_LockingAjax_Locking extends AdministrationPage {
		protected $_driver = null;
		
		public function __construct(&$parent){
			parent::__construct($parent);
			$this->_driver = $this->_Parent->ExtensionManager->create('pessimistic_db_locking');
		}
		
		public function __viewIndex() {
			
			
			$entry_id = $_REQUEST['entry_id'];
			$author_id = $_REQUEST['author_id'];
			if (!$entry_id || !$author_id) {
				echo json_encode('expired');
				exit;
			}
			$setup = $_REQUEST['setup'];
			
			$force = $_REQUEST['force'];
			if ($force == 'true') {
				$this->_driver->removeTheLockByEntry($entry_id);
				$this->_driver->renewTheLock($entry_id, $author_id);
				echo json_encode('true');
				exit;
			}
			
			$lock = $this->_driver->lockExists($entry_id);
			if ($author_id != $lock[0] && $lock[0] > 0) {

		    $authorManager = new AuthorManager($this->_Parent);
				$author = $authorManager->fetchByID($lock[0]);
				echo json_encode($author->getFullName());
			}
			else if ($lock == -1) {
				echo json_encode('expired-lifetime');
			}
			// doesn't exist
			else if ($lock == 0 && $setup == true) {
				$this->_driver->renewTheLock($entry_id, $author_id);
				echo json_encode('true');
			}
			else if ($lock == 0) {
				echo json_encode('expired');
			}
			else {
				$this->_driver->renewTheLock($entry_id, $author_id);
				echo json_encode('true');
			}
			
			exit;	

		}
	}