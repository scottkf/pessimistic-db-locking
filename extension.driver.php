<?php

	require_once(TOOLKIT . '/class.entrymanager.php');
	require_once(TOOLKIT . '/class.authormanager.php');

	class Extension_Pessimistic_DB_Locking extends Extension {
	/*-------------------------------------------------------------------------
		Extension definition
	-------------------------------------------------------------------------*/
		
		public static $params = null;
		// in seconds, how often to renew the lock  (via an AJAX request)
		protected $renew_lock = 30;
		// in seconds, how long the lock remains if it's not renewed
		protected $expire_lock = 40;
		// in seconds, how long a person can hold a lease (if they hold it for this long, it's automatically dropped, 
		//		so as to prevent people from being idle on a page)
		protected $max_lock_hold = 7200;
		protected $locked = false;
		
		public function about() {
			return array(
				'name'			=> 'Pessimistic Database Locking',
				'version'		=> '0.01',
				'release-date'	=> '2009-06-10',
				'author'		=> array(
					'name'			=> 'Scott Tesoriere',
					'website'		=> 'http://tesoriere.com',
					'email'			=> 'scott@tesoriere.com'
				),
				'description'	=> 'Adds simple row-level locking to Symphony.'
			);
		}
		

   public function install() {
      $this->_Parent->Database->query("
        CREATE TABLE IF NOT EXISTS `tbl_db_locking` (
          `id` int(11) unsigned NOT NULL auto_increment,
					`entry_id` int(11) unsigned NOT NULL,
					`user_id` int(11) unsigned NOT NULL,
					`time_updated` timestamp NOT NULL,
					`time_opened` datetime NOT NULL,
          PRIMARY KEY (`id`),
					KEY `entry_id` (`entry_id`),
					KEY `user_id` (`user_id`)
        )
      ");
		}

    public function uninstall() {
      $this->_Parent->Database->query("DROP TABLE `tbl_db_locking`");
    }
		
		public function getSubscribedDelegates() {
			return array(
				array(
					'page'		=> '/backend/',
					'delegate'	=> 'InitaliseAdminPageHead',
					'callback'	=> 'initaliseAdminPageHead'
				),
				array(
					'page'	=>'/frontend/', 
					'delegate'	=> 'FrontendOutputPreGenerate',
					'callback'	=> 'initialiseFrontendPageHead'
				),
				array(
					'page'		=> '/backend/',
					'delegate'	=>	'AppendPageAlert',
					'callback'	=>	'appendPageAlert'
				),
				array(
          'page'    => '/blueprints/events/edit/',
          'delegate'  => 'AppendEventFilter',
          'callback'  => 'appendEventFilter'
        ),        
        array(
          'page'    => '/blueprints/events/new/',
          'delegate'  => 'AppendEventFilter',
          'callback'  => 'appendEventFilter'
        ),
				array(
          'page'    => '/frontend/',
          'delegate'  => 'EventPreSaveFilter',
          'callback'  => 'eventPreSave'
        ),
			array(
        'page'    => '/frontend/',
        'delegate'  => 'EventPostSaveFilter',
        'callback'  => 'eventPostSave'
      ),
				array(
					'page'		=> '/publish/edit/',
					'delegate'	=> 'EntryPreEdit',
					'callback'	=> 'entryPreSave'
				),
				array(
					'page'		=> '/publish/edit/',
					'delegate'	=> 'EntryPostEdit',
					'callback'	=> 'entryPostSave'
				)
			);
		}
		
	/*-------------------------------------------------------------------------
		Delegates:
	-------------------------------------------------------------------------*/
		
		public function initaliseAdminPageHead($context) {
			$page = $context['parent']->Page;
			
			// only include the element if you're on the symphony /publish/*/edit page
      if ($page instanceof contentPublish and $page->_context['page'] == 'edit') {      
				$page->addStylesheetToHead(URL . '/extensions/pessimistic_db_locking/assets/locking.css', 'screen', 1003);
				$page->addScriptToHead(URL . '/extensions/pessimistic_db_locking/assets/locking.js', 1004);

				if (1) {
					$author_id = $this->_Parent->Author->get('id');
					$entry_id = $page->_context['entry_id'];
					// if the lock doesn't exist (or is expired)
					if (($lock = $this->lockExists($entry_id)) <= 0) {
						// add js to renew lock
						$page->addElementToHead($this->addJStoRenewLock($entry_id, $author_id), '1005');
						$this->renewTheLock($entry_id, $author_id);					
					} else {
						// if the author isn't the same one, let the user know
						if ($lock[0] != $author_id) {
							// set a page alert and fire the js
							$page->addScriptToHead(URL . '/extensions/pessimistic_db_locking/assets/disable-form.js', '1005');
							$this->locked = array(true, $lock[0], $lock[1]);
						} else {
							// add js to renew lock
							$page->addElementToHead($this->addJStoRenewLock($entry_id, $author_id), '1005');
							$this->renewTheLock($entry_id, $author_id);
						}
					}
				}
			}
		}			

		// array of page, xml, xsl
		public function initaliseFrontendPageHead($context) {
			$page = $context['parent']->Page;
			$event = $context['event'];
			
			// only include the js if there's an event named 'lock-entry' on the page
			//   (ignore if the section is allowed to be locked because it would be overwritten here)
			// if there's an existing lock that isn't expired (and the user isn't the lock user)
			//  let the user know who it's owned by, set inputs readonly (via js), and to try again later ;'(
			// else
			//	renew/setup a lock
			$page->addScriptToHead(URL . '/extensions/pessimistic_db_locking/assets/locking.js', 1004);
			
		}
		
		public function appendPageAlert(&$context) {
			$page = $context['parent']->Page;
			// if the entry is locked, 
			// 	let the user know (so long as it's not the same user and the lock isn't expired)
			if ($this->locked[0] == true) {
		    $authorManager = new AuthorManager($this->_Parent);
		    $authors = $authorManager->fetchByID($this->locked[1]);
				$time_left = (strtotime($this->locked[2]) + $this->expire_lock) - time();
				Administration::instance()->Page->pageAlert(__($authors->getFullName().' is already editing this entry! Please try again in <strong>'.$time_left.'</strong> seconds.'), Alert::ERROR);
			}
		}

		public function appendEventFilter(&$context) {
      $context['options'][] = array(
         'lock-entry', @in_array($id, $context['selected']),
         General::sanitize("Lock Entry")
       );			
		}

		// array of section, entry, fields
		public function entryPreSave($context) {
			$page = $context['parent']->Page;
			$entry_id = $page->_context['entry_id'];
			echo $entry_id." id\n";
			// check if a lock exists AND it's owned by the same user AND it's not expired
			//	say ok and lock sym_entries, it will be implicitly unlocked when we disconnect if something fails 
			// if not
			//  set $context[entry to null]
		}

		// array of section, entry, fields
		public function entryPostSave($context) {
			// unlock sym_entries
			// get the entry id, user id, try to free the lock (it might already be free from the javascript)
		}

		// array of section, entry, fields
		public function eventPreSave($context) {
			$event = $context['event'];
		 	if (in_array("lock-entry", $event->eParamFILTERS)) {
				// check if a lock exists AND it's owned by the same user AND it's not expired
				//	say ok and lock sym_entries, it will be implicitly unlocked when we disconnect if something fails
				// if not
				//  say no lock saving for you
			}
		}
		// array of section, entry, fields
		public function eventPostSave($context) {
			$event = $context['event'];
		 	if (in_array("lock-entry", $event->eParamFILTERS)) {
				// unlock sym_entries
				// get the entry id, user id, try to free the lock (it might already be free from the javascript)
				;
      }
		}


		/*-------------------------------------------------------------------------
			Class functions:
		-------------------------------------------------------------------------*/
		
		public function freeTheLock($entry_id, $user_id = 1) {
			if (($lock = $this->fetchTheLock($entry_id, $user_id)) !== FALSE) {
				$this->removeTheLock($lock['id']);
			}
		}
		
		// this set's up/renew's the lock
		public function renewTheLock($entry_id, $user_id = 1) {
			// remove the old one
			if (($lock = $this->fetchTheLock($entry_id, $user_id)) !== FALSE) {
				$this->updateTheLock($lock['entry_id'], $lock['user_id'], $lock['id']);
			}
			else {// insert a new one
				$this->addTheLock($entry_id, $user_id);
			}
		}

		public function lockExists($entry_id) {
			$this->lockTables('READ');
			$lock = $this->_Parent->Database->fetch("
				SELECT `user_id`, `time_opened`, `id` , `time_updated`
				FROM `tbl_db_locking`  
				WHERE `entry_id` = '".$entry_id."'
				LIMIT 1				
			");
			$this->unlockTables();
			if (count($lock[0]) <= 0) return 0;
			// the lock expired OR the lock has reached it's maximum shelf life
			if (time() >= ($this->expire_lock + strtotime($lock[0]['time_updated']))) {
				// remove the old lock
				$this->removeTheLock($lock[0]['id']);
				return 0;
			} else if (time() >= ($this->max_lock_hold + strtotime($lock[0]['time_opened']))) {
					$this->removeTheLock($lock[0]['id']);
					return -1;
			}
			return array($lock[0]['user_id'], $lock[0]['time_opened'], $lock[0]['time_updated']);
			// fetch the lock
			// check if it exists AND it's owned by the same user AND it's not expired
		}
		
		public function removeTheLockByEntry($entry_id) {
			$this->lockTables('WRITE');
			$this->_Parent->Database->query("
				DELETE QUICK FROM
				`tbl_db_locking`
				WHERE 
				`entry_id` = {$entry_id}
			");			
			$this->unlockTables();			
		}
		

		protected function fetchTheLock($entry_id, $user_id) {
			$this->lockTables('READ');
			$lock = $this->_Parent->Database->fetch("
				SELECT `entry_id`, `user_id`, `time_opened`, `id`, `time_updated`
				FROM `tbl_db_locking`  
				WHERE `entry_id` = '".$entry_id."' AND `user_id` = '".$user_id."'
				LIMIT 1"
			);
			$this->unlockTables();
			if (count($lock) <= 0) $lock[0] = FALSE;
			return $lock[0];
		}

		protected function removeTheLock($id) {
			if ($id == '') return;
			$this->lockTables('WRITE');
			$this->_Parent->Database->query("
				DELETE QUICK FROM
				`tbl_db_locking`
				WHERE 
				`id` = {$id}
			");			
			$this->unlockTables();
		}


		protected function updateTheLock($entry_id, $user_id, $id) {
			$this->lockTables('WRITE');
			$this->_Parent->Database->query("
				UPDATE
				`tbl_db_locking`
				SET
				`entry_id` = {$entry_id},
				`user_id` = {$user_id},
				`time_updated` = now()
				WHERE
				`id` = {$id}
			");
			$this->unlockTables();
		}

		protected function addTheLock($entry_id, $user_id) {
			$this->lockTables('WRITE');
			$this->_Parent->Database->query("
				INSERT INTO
				`tbl_db_locking`
				SET
				`entry_id` = {$entry_id},
				`user_id` = {$user_id},
				`time_opened` = now()
			");
			$this->unlockTables();
		}


		protected function lockTables($method) {
			$this->_Parent->Database->query("
				LOCK TABLES `tbl_db_locking` {$method}
			");
		}
		
		protected function unlockTables() {
			$this->_Parent->Database->query("
				UNLOCK TABLES
			");
		}
		
		protected function addJStoRenewLock($entry_id, $author_id) {
			$script = new XMLElement('script');
			$script->setSelfClosingTag(false);
			$script->setAttributeArray(array('type' => 'text/javascript'));
			$script->setValue("
				jQuery(document).ready(function() {
					Locking.renewLock('".$entry_id."', '".$author_id."', '".$this->renew_lock."');
				});								
			");
			return $script;
		}

	}
	
	
	
?>