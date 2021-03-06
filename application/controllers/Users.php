<?php
class Users extends CI_Controller {
	public function __construct()
	{
		parent::__construct();
		$this->load->model('users_model');
		$this->load->library('parsedown');
		$this->load->library('parsedownExtra');

		$this->load->library('form_validation');

		$this->load->helper('form');
	}

	public function index($page = 1)
	{
		// fetch users array according to page number, if given any
		$data['users'] = $this->users_model->get_users(FALSE, FALSE, $page);
		$data = $this->security->xss_clean($data);

		$data['title'] = 'Users';

		$this->load->view('templates/header', $data);
		$this->load->view('templates/sidebar', $data);
		$this->load->view('templates/main', $data);
		$this->load->view('users/index', $data);
		$this->load->view('templates/footer', $data);
	}

	public function view($nick = NULL, $page = 1)
	{
		// user row array
		$data['users_item'] = $this->users_model->get_users($nick);
		$data = $this->security->xss_clean($data);

		if (empty($data['users_item'])) {
			show_404();
		}

		$Parsedown = new ParsedownExtra();

		$data['title'] = $data['users_item']['usr_nick'];
		$data['prf_bio'] = $Parsedown->text($data['users_item']['prf_bio']);

		$data['posts'] = $this->users_model->get_userPosts($nick, $page);
		foreach ($data['posts'] as &$posts_item) {
			$posts_item['post_text'] = $Parsedown->text($posts_item['post_text']);
		}

		// pagination configuration
		$config = array();
		$config['base_url'] = base_url('profile/' . $nick);
		$config['total_rows'] = $this->db->count_all_results();
		$config['uri_segment'] = 3;
		$this->pagination->initialize($config);
		$data['pagination'] = $this->pagination->create_links();

		$this->load->view('templates/header', $data);
		$this->load->view('templates/sidebar', $data);
		$this->load->view('templates/main', $data);
		$this->load->view('users/view', $data);
		$this->load->view('templates/footer', $data);
	}

	public function create()
	{
		// already logged in?
		if ($this->session->usr_id) {
			redirect(base_url());
		}

		$data['title'] = 'Register';

		$this->form_validation->set_rules('username', 'Username', 'required|alpha_dash|is_unique[usr.usr_nick]');
		$this->form_validation->set_rules('password', 'Password', 'required');
		$this->form_validation->set_rules('passwordcheck', 'Password (check)', 'required|matches[password]');
		$this->form_validation->set_rules('mail', 'Mail', 'required|valid_email|is_unique[prf.prf_mail]');

		$this->load->view('templates/header', $data);
		$this->load->view('templates/sidebar', $data);
		$this->load->view('templates/main', $data);

		if ($this->form_validation->run() === FALSE) {
			$this->load->view('users/create');
		} else {
			$this->users_model->set_user();
			$this->users_model->set_profile();
			$this->load->view('users/login');
		}

		$this->load->view('templates/footer', $data);
	}

	public function delete($id)
	{
		// permissions test
		if ($this->session->prf_act != "A") {
			redirect(base_url('users'));
		}

		$this->users_model->delete_user($id);
		redirect(base_url('users'));
	}

	public function login()
	{
		// already logged in?
		if ($this->session->usr_id) {
			redirect(base_url());
		}

		$data['title'] = 'Login';

		$nick = $this->input->post('username');
		$pass = hash('sha256', $this->config->item('global_salt') . $this->input->post('password'));

		$this->form_validation->set_rules('username', 'Username', 'required');
		$this->form_validation->set_rules('password', 'Password', 'required');

		$this->load->view('templates/header', $data);
		$this->load->view('templates/sidebar', $data);
		$this->load->view('templates/main', $data);

		if ($this->form_validation->run() === FALSE) {
			$this->load->view('users/login');
		} else {
			$data = $this->users_model->check_user($nick, $pass);
			$data = $this->security->xss_clean($data);

			if ($data) {
				$this->session->set_userdata('usr_id', $data['usr_id']);
				$this->session->set_userdata('usr_nick', $data['usr_nick']);
				$this->session->set_userdata('prf_act', $data['prf_act']);
				redirect(base_url());
			} else {
				$data = Array(
						'valid_user' => 'Wrong username and/or password.'
					);
				$this->load->view('users/login', $data);
			}
		}

		$this->load->view('templates/footer', $data);
	}

	public function logout()
	{
		$this->session->sess_destroy();

		redirect(base_url());
	}
}

