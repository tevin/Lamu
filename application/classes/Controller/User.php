<?php defined('SYSPATH') OR die('No direct access allowed.');

class Controller_User extends Controller_Layout {

	public $template = 'user/main';

	protected $_auth;
	protected $_acl;
	protected $_user;

	protected $_redirect_whitelist = array(
		'oauth/authorize',
		'/'
	);

	public function before()
	{
		parent::before();

		$this->acl  = A2::instance();
		$this->auth = $this->acl->auth();
		$this->user = $this->acl->get_user();

		$this->header->set('logged_in', $this->auth->logged_in());
	}

	public function action_index()
	{
		if (! $this->acl->allowed('logout'))
		{
			$this->redirect('user/login' . URL::query());
		}

		$this->template = View::factory('user/main');
	}

	public function action_login()
	{
		if (! $this->acl->allowed('user/login') AND ! $this->acl->allowed('register'))
		{
			if ($from_url = $this->request->query('from_url')
					AND in_array(parse_url($from_url, PHP_URL_PATH), $this->_redirect_whitelist)
				)
			{
				$this->redirect($from_url);
			}
			else
			{
				$this->redirect('user' . URL::query());
			}
		}

		$this->template = View::factory('user/login');
	}

	public function action_register()
	{
		if (! $this->acl->allowed('login') AND ! $this->acl->allowed('register'))
		{
			if ($from_url = $this->request->query('from_url')
					AND in_array(parse_url($from_url, PHP_URL_PATH), $this->_redirect_whitelist)
				)
			{
				$this->redirect($from_url);
			}
			else
			{
				$this->redirect('user' . URL::query());
			}
		}

		$this->template = View::factory('user/register');
	}

	public function action_submit_login()
	{
		if (! $this->acl->allowed('login'))
		{
			$this->redirect('user' . URL::query());
		}

		if ($this->request->method() != 'POST')
		{
			$this->redirect('user' . URL::query());
		}

		$parser  = service('parser.user.login');
		$usecase = service('usecase.user.login');
		$params  = $this->request->post();

		try
		{
			$user = $parser($params);
			$userid = $usecase->interact($user);

			// TODO: move this into the use case, somehow, some way...
			$user = ORM::factory('User', $userid);
			$this->auth->complete_login($user);
		}
		catch (Ushahidi\Exception\Validator $e)
		{
			$error = implode(', ', Arr::flatten($e->getErrors()));
		}
		catch (Ushahidi\Exception\Authenticator $e)
		{
			$error = $e->getMessage();
		}

		if (empty($error)) {
			$to_url = $this->request->query('from_url');
			if (in_array(parse_url($to_url, PHP_URL_PATH), $this->_redirect_whitelist))
			{
				$this->redirect($to_url);
			}
			$this->redirect('user' . URL::query());
		}

		$this->template = View::factory('user/login')
			->set('error', $error)
			->set('form', $params);
	}

	public function action_submit_register()
	{
		if (! $this->acl->allowed('register'))
		{
			$this->redirect('user' . URL::query());
		}

		if ($this->request->method() != 'POST')
		{
			$this->redirect('user/login' . URL::query());
		}

		$parser  = service('parser.user.register');
		$usecase = service('usecase.user.register');
		$params  = $this->request->post();

		try
		{
			$user = $parser($params);
			$userid = $usecase->interact($user);

			return $this->action_submit_login();
		}
		catch (Ushahidi\Exception\Validator $e)
		{
			$this->template = View::factory('user/register')
				->set('error', implode(', ', Arr::flatten($e->getErrors())))
				->set('form', $params);
		}
	}

	public function action_logout()
	{
		$this->auth->logout();
		if ($from_url = $this->request->query('from_url')
				AND in_array(parse_url($from_url, PHP_URL_PATH), $this->_redirect_whitelist)
			)
		{
			$this->redirect($from_url);
		}
		else
		{
			$this->redirect('user/login' . URL::query());
		}
	}

}
