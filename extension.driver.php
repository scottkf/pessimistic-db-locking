<?php
	
	class Extension_Custom_Admin extends Extension {
	/*-------------------------------------------------------------------------
		Extension definition
	-------------------------------------------------------------------------*/
		
		public static $params = null;
		
		public function about() {
			return array(
				'name'			=> 'Custom Admin',
				'version'		=> '0.01',
				'release-date'	=> '2009-05-25',
				'author'		=> array(
					'name'			=> 'Nick Dunn',
					'website'		=> 'http://airlock.com',
					'email'			=> 'nick.dunn@airlock.com'
				),
				'description'	=> 'Bastardise the backend.'
			);
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
					'delegate'	=>	'AppendElementBelowView',
					'callback'	=>	'appendElementBelowView'
				)
			);
		}
		
	/*-------------------------------------------------------------------------
		Delegates:
	-------------------------------------------------------------------------*/
		
		public function initaliseAdminPageHead($context) {
			$page = $context['parent']->Page;
			
			$page->addStylesheetToHead(URL . '/extensions/custom_admin/assets/custom.css', 'screen', 1001);
			$page->addScriptToHead(URL . '/extensions/custom_admin/assets/custom.js', 1002);
			
		}
	}
	
?>