<?php

	require_once(TOOLKIT . '/class.entrymanager.php');
	require_once(TOOLKIT . '/class.eventmanager.php');
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
				'version'		=> '0.5',
				'release-date'	=> '2009-06-14',
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
					`time_updated` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
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
	        'page' => '/blueprints/events/new/',
	        'delegate' => 'AppendEventFilterDocumentation',
	        'callback' => 'appendEventFilterDocumentation'
	      ),
	      array(
	        'page' => '/blueprints/events/edit/',
	        'delegate' => 'AppendEventFilterDocumentation',
	        'callback' => 'appendEventFilterDocumentation'
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
	      'page'    => '/frontend/',
	      'delegate'  => 'FrontendProcessEvents',
	      'callback'  => 'preInjectXML'
	    ),
			array(
	      'page'    => '/frontend/',
	      'delegate'  => 'FrontendEventPostProcess',
	      'callback'  => 'injectXML'
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
							// just incase they tried (accidentally or maliciously) to POST without owning the lock
							$_REQUEST = array(); 
							$_POST = array();
							$page->addScriptToHead(URL . '/extensions/pessimistic_db_locking/assets/disable-form.js', '1005');
							$this->locked = array(true, $lock[0], $lock[1], $lock[2]);
						} else {
							// add js to renew lock
							$page->addElementToHead($this->addJStoRenewLock($entry_id, $author_id), '1005');
							$this->renewTheLock($entry_id, $author_id);
						}
					}
				}
			}
		}			
		
		public function appendPageAlert(&$context) {
			$page = $context['parent']->Page;
			// if the entry is locked, 
			// 	let the user know (so long as it's not the same user and the lock isn't expired)
			if ($this->locked[0] == true) {
		    $authorManager = new AuthorManager($this->_Parent);
		    $authors = $authorManager->fetchByID($this->locked[1]);
				$time_left = (strtotime($this->locked[3]) + $this->expire_lock) - time();
				Administration::instance()->Page->pageAlert(__($authors->getFullName().' is already editing this entry! Please try again in <strong>'.$time_left.'</strong> seconds.'), Alert::ERROR);
			}
		}
		

		// array of section, entry, fields
		public function entryPostSave($context) {

			$entry_id = $context['entry']->get('id');
			$this->removeTheLockByEntry($entry_id);
		}

		public function preInjectXML($context) {
			$EventManager = new EventManager($this->_Parent);
			// we have to load the events twice to look for a lock-entry event attached
			if(strlen(trim($context['events'])) > 0) {
				$events = preg_split('/,\s*/i', $context['events'], -1, PREG_SPLIT_NO_EMPTY);
				$events = array_map('trim', $events);
			
				if(!is_array($events) || empty($events)) return;
		
				foreach($events as $handle){
					$event = $EventManager->create($handle);
				 	if ($event->eParamFILTERS && in_array("lock-entry", $event->eParamFILTERS)) {
						$this->locked = true;
						return;
					}
				}
			}
		}
		
		public function injectXML($context) {
			// do this only if the lock-entry event is attached
			if ($this->locked == true) {
				$lock_event = new XMLElement('lock-entry');
		
				$fields = array(
					'renew' => new XMLElement('renew_every', $this->renew_lock),
					'expires' => new XMLElement('expires_at', $this->expire_lock),
					'expires-lifetime' => new XMLElement('expires_lifetime', $this->max_lock_hold)
				);
				foreach($fields as $f) $lock_event->appendChild($f);	
				$context['xml']->appendChild($lock_event);
			}			
		}

		public function appendEventFilter(&$context) {
      $context['options'][] = array(
         'lock-entry', @in_array('lock-entry', $context['selected']),
         General::sanitize("Lock Entry")
       );			
		}
		
		public function appendEventFilterDocumentation($context) {
			if(!in_array('lock-entry', $context['selected'])) return;
			$context['documentation'][] = new XMLElement('h3', 'Database Entry Leasing');

			$context['documentation'][] = new XMLElement('p', '<b>This event will only work on events being edited. So events with the following:</b>');
			$code = '<input name="id" type="hidden" value="23" />';

			$context['documentation'][] = contentBlueprintsEvents::processDocumentationCode($code);

			$context['documentation'][] = new XMLElement('p', 'To handle leasing, javascript MUST be used. An example of how to deal with leases follows:');

			$code = <<<CODE

<script type="text/javascript" src="{$root}/extensions/pessimistic_db_locking/assets/locking.js"></script>
<script type="text/javascript">
	<xsl:comment>
		jQuery(document).ready(function() {
			
			/* init takes 3 parameters
					the path to your symphony site (http://example.com)
					the id of the entry being edited
					the id of the author */
			Locking.init('<xsl:value-of select="$root"/>', '<xsl:value-of select="'1130'"/>','<xsl:value-of select="events/login-info/@id"/>' );

			Locking.setupLock(respond);
			function respond(response) {
				switch(response) {

				case '"expired-lifetime"':
					/* offer the user an option to renew the lease incase they're not idle
					 		it can be renewed by doing the following:
							Locking.forceRenewCallback(function response(data) {})
					  	where data is one of the following cases in this example */
					break

				case '"expired"':
					/* this will occur after the "expired-lifetime" case, when no lock exists, 
							so chances are they're still idle */
					alert('no lock exists for this entry');
					break

				case '"true"':
					/* renewLockCallback takes 2 parameters
								how often to renew the lease
								the callback to be used (in this example, it is 'respond') */
					Locking.renewLockCallback('<xsl:value-of select="events/lock-entry/renew_every"/>', respond);
					// we're just renewing here
					break

				default:
					// if someone else owns the lease, do something. in this example, I disable all the form controls
					Locking.disableForm();
					alert('currently owned by ' + response);
				} 
			}
		});
	</xsl:comment>
</script>
CODE;

			$context['documentation'][] = contentBlueprintsEvents::processDocumentationCode($code);

			$context['documentation'][] = new XMLElement('p', 'If a user tries to save an entry with this event attached, one of the following might result:');
			$code = '<filter name="lock-entry" status="passed" />
<filter name="lock-entry" status="failed">This lease is currently owned by "some authors name".</filter>';

			$context['documentation'][] = contentBlueprintsEvents::processDocumentationCode($code);

		}


		// array of fields, events, messages ($type, passed/failed, $message)
		public function eventPreSave($context) {
			$event = $context['event'];
		 	if (in_array("lock-entry", $event->eParamFILTERS)) {
				// see if we're editing anything
				if (!isset($_POST['id'])) {
					//change $context['message']
					return;
				} else {
					$entry_id = $_POST['id'];
				}

				// if there's no user logged in, user_id still has to be set to something
				$author_id = ($context['parent']->isLoggedIn() ? $context['parent']->Author->get('id') : 1);
				if (($lock = $this->lockExists($entry_id)) <= 0) {
					// if a lock doesn't exist or is expired, we can just give them one (ie ignore it)
					$context['messages'] = array(array('lock-entry', 'passed', ''));
				} else {
					// the lock exists, see if it's owned by the user
					if ($lock[0] != $author_id) {
				    $authorManager = new AuthorManager($this->_Parent);
				    $authors = $authorManager->fetchByID($this->locked[1]);
						$context['messages'] = array(array('lock-entry', 'failed', 'This lease is currently owned by '.$authors->getFullName().'.'));
					}
				}
			}
		}



		// array of section, entry, fields
		public function eventPostSave($context) {
			$event = $context['event'];
		 	if (in_array("lock-entry", $event->eParamFILTERS)) {
				if (!isset($_POST['id'])) {
					; //change $context['message']
					return;
				} else {
					$entry_id = $_POST['id'];
				}

				// remove a lock
				$this->removeTheLockByEntry($entry_id);
				;
      }
		}


		/*-------------------------------------------------------------------------
			Class functions:
		-------------------------------------------------------------------------*/
		
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

		/* this see's if the lock exists AND see's if it's expired 
			 		if it exists, returns an array of
			 		the user_id who owns it
					the time it was opened
					the time it was last updated
																					
		*/
		public function lockExists($entry_id) {
			if (!$entry_id) return -2;
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
		
		/* this just sees if the lock exists */
		protected function fetchTheLock($entry_id, $user_id) {
			if (!$entry_id) return -2;
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
			if (!$entry_id) return -2;
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
					Locking.init();
					Locking.renewLock('".$entry_id."', '".$author_id."', '".$this->renew_lock."');
				});								
			");
			return $script;
		}

	}
	
	
	
?>