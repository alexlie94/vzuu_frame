<?php defined('BASEPATH') or exit('No direct script access allowed');

abstract class MY_Owner extends MY_Template
{

	protected $_session_email;
	protected $_session_id;
	protected $_menu;
	protected $_menu_active;
	protected $_controller_except = '';
	protected $_function_except   = [];

	public function __construct()
	{
		$this->_session_email = $this->session->userdata('email_admin');
		$this->_session_id    = $this->session->userdata('session_id_admin');
		$this->load->model('users/Users_model', 'users_model');

		$this->_check_session();
		$this->_set_menu();
		$this->_check_access_page();
		$this->_logout_url = base_url() . "logout";
		parent::__construct();
		$this->_get_menu();
		$this->load->model($this->router->class . '_model', strtolower($this->router->class) . '_model');
		$this->template->set('fullname',  json_encode($this->users_model->getProfil()));
	}

	private function _check_session()
	{
		$email      = $this->_session_email;
		$session_id = $this->_session_id;
		try {
			if ($email == null || $session_id == null) {
				throw new Exception("Session not found", 1);
			}

			$this->load->model('app/App_model', 'app_model');

			$check = $this->app_model->_check_data_user($email, $session_id);

			if (!$check) {
				throw new Exception("This Account has been suspended", 1);
			}
		} catch (Exception $e) {

			if ($this->input->is_ajax_request()) {
				header("HTTP/1.1 401 Unauthorized");
				exit;
			}
			redirect(base_url(), 'location');
		}
	}

	private function _set_menu()
	{
		$this->load->model('app/App_model', 'app_model');
		$menu        = $this->app_model->get_menu($this->_session_email, $this->_session_id);
		$this->_menu = $menu;
	}

	protected function _check_access_page()
	{
		$class  = $this->router->class;
		$method = $this->router->method;
		$menu   = $this->_menu;

		$key = array_search($class, array_column(json_decode(json_encode($menu), true), 'controller'));
		try {

			if ($key === false && $this->_controller_except == '') {
				throw new Exception;
			}

			switch ($method) {
				case 'index':
					if ($key !== false) {
						$view    = 1;
						$view_db = $menu[$key]->view;
						if ($view != $view_db) {
							throw new Exception;
						}
					}
					break;
				case 'insert':
					if ($key !== false) {
						$insert    = 1;
						$insert_db = $menu[$key]->insert;
						if ($insert != $insert_db) {
							throw new Exception;
						}
					}
					break;

				case 'update':
					if ($key !== false) {
						$update    = 1;
						$update_db = $menu[$key]->update;
						if ($update != $update_db) {
							throw new Exception;
						}
					}
					break;
				case 'delete':
					if ($key !== false) {
						$delete    = 1;
						$delete_db = $menu[$key]->delete;
						if ($delete != $delete_db) {
							throw new Exception;
						}
					}
					break;
				case 'import':
					if ($key !== false) {
						$import    = 1;
						$import_db = $menu[$key]->import;
						if ($import != $import_db) {
							throw new Exception;
						}
					}
					break;
				case 'export':
					if ($key !== false) {
						$export    = 1;
						$export_db = $menu[$key]->import;
						if ($export != $export_db) {
							throw new Exception;
						}
					}
					break;
				default:
					if ($this->_controller_except == '') {
						if (count($this->_function_except) == 0) {
							throw new Exception;
						}

						$search_except_method = array_search($method, $this->_function_except);
						if ($search_except_method === false) {
							throw new Exception;
						}
					}
					break;
			}

			$this->_menu_active = isset($menu[$key]) ? $menu[$key] : false;
		} catch (Exception $e) {
			if ($this->input->is_ajax_request()) {
				header("HTTP/1.0 404 Not Found");
				exit;
			}
			pageError();
		}
	}

	private function _get_menu()
	{
		$menuItems = $this->_menu;
		$group_menu = [];

		foreach ($menuItems as $val) {
			if ($val->view == 1) {
				$menu = array(
					'menu_id'    => $val->menu_id,
					'controller' => $val->controller,
					'menu_name'  => $val->menu_name,
					'kategori' 	 => $val->kategori,
					'icon'       => $val->icon,
					'active'     => $active_menu ?? '',
					'child'      => []
				);

				if ($val->parent == 0) {
					$group_menu[$val->menu_id] = $menu;
				} else {
					$parentId = $val->parent;
					if (!isset($group_menu[$parentId])) {
						$parent = $this->db->get_where('master_menus', ['id' => $parentId])->row_array();
						$group_menu[$parentId] = array(
							'menu_id'    => $parent['id'],
							'controller' => $parent['controller'],
							'menu_name'  => $parent['menu_name'],
							'icon'       => $parent['icon'],
							'active'     => $active_menu ?? ''
						);
					}
					$group_menu[$parentId]['child'][$val->menu_id] = $menu;
				}
			}
		}

		$this->setMenu($group_menu);
		$this->setJsType(JS_OWNER);
	}

	protected function function_access($function = null)
	{
		try {
			if ($function === null) {
				throw new Exception;
			}

			if ($this->_menu_active !== false && isset($this->_menu_active->{$function})) {
				if ((int)$this->_menu_active->{$function} === 0) {
					throw new Exception;
				}
			}
			return true;
		} catch (Exception $e) {
			pageError();
		}
	}

	protected function setButtonHeader()
	{
		$custom = [];
		if (is_array($this->_custom_button_header) && count($this->_custom_button_header) > 0) {
			$custom = $this->_custom_button_header;
		}
		$this->buttonHeader($this->_menu_active, $custom);
	}

	protected function setTable($array = [], $buttonHeader = false)
	{
		if (!$this->checkThereIsButtonDatatables()) {
			array_pop($array);
		}

		$this->setTemplateTable($array);
		if ($buttonHeader) {
			$this->setButtonHeader();
		} else {
			$this->buttonHeader();
		}
	}

	protected function setButtonOnTable()
	{
		return $this->buttonOnTable($this->_menu_active);
	}

	protected function checkThereIsButtonDatatables()
	{
		$update = $this->_menu_active->update;
		$delete = $this->_menu_active->delete;
		$import = $this->_menu_active->import;
		$export = $this->_menu_active->export;

		if ($update == 0 && $delete == 0 && $import == 0 && $export == 0) {
			return false;
		}
		return true;
	}

	public function paging()
	{
		$this->function_access('view');
		isAjaxRequestWithPost();
		echo json_encode(array('paging' => generatePaging()));
		exit();
	}
}
