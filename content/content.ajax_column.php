<?php

	require_once(TOOLKIT . '/class.administrationpage.php');
	require_once(EXTENSIONS . '/databasemanipulator/lib/class.databasemanipulator.php');

	class ContentExtensionCustom_AdminAjax_Column extends AdministrationPage {
		protected $_driver = null;
		
		public function __construct(&$parent){
			parent::__construct($parent);
			$this->_driver = $this->_Parent->ExtensionManager->create('custom_admin');
		}
		
		public function __viewIndex() {
			
			DatabaseManipulator::associateParent(Administration::instance());
			
			$section = $_REQUEST['section'];
			$fields = explode(',', $_REQUEST['fields']);
			
			$query_fields = array();
			foreach($fields as $f) {
				$field = explode(':', $f);
				$query_fields[] = $field[0];
			}
			
			$conditions = $_REQUEST['conditions'];
			
			$result_type = $_REQUEST['result'];
			
			$entries = array();
			
			foreach($_REQUEST['entry'] as $id => $entry) {
				
				$query = DatabaseManipulator::getEntries(
					$section,
					$query_fields,
					$entry['filter'],
					$conditions
				);
				
				$type = explode(':',$result_type);
				
				switch($type[0]) {
					case 'count':
						$result = count($query);
					break;
					case 'date':
						$format = substr($result_type, strlen($type[0]) + 1, strlen($result_type));
						if($format == '') {
							$result = date('d F Y', $query[$id]['fields']['date']['gmt']);
						} else {
							$result = date($format, $query[$id]['fields']['date']['gmt']);
						}
					break;
					default:
						$result = end($query);
						$handle = explode(':',$fields[0]);
						$variant = ($handle[1]) ? $handle[1] : 'value';
						$result = $result['fields'][$handle[0]][$variant];
					break;
				}
				
				$entries[] = array($id, $result);
			}
			
			echo json_encode($entries);
			exit;
			
		}
	}