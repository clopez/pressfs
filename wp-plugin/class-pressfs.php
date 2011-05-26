<?php
class PressFS {
	private $data;
	private $user;

	public function __construct() { }

	public function auth_user() {
		if (
			empty( $_SERVER['PHP_AUTH_USER'] )
			|| empty( $_SERVER['PHP_AUTH_PW'] )
		) {
			$this->send_error( 'Authentication required' );
		}

		$user = wp_authenticate(
			$_SERVER['PHP_AUTH_USER'],
			$_SERVER['PHP_AUTH_PW']
		);

		if ( is_wp_error( $user ) ) {
			header( 'WWW-Authenticate: Basic realm="PressFS"' );
			header( 'HTTP/1.0 401 Unauthorized' );
			$this->send_error( 'Authentication failure' );
		}

		if ( !in_array( 'administrator', $user->roles ) ) {
			header( 'WWW-Authenticate: Basic realm="PressFS"' );
			header( 'HTTP/1.0 401 Unauthorized' );
			$this->send_error( 'User must have the administrator role.' );
		}

		wp_set_current_user( $this->ID );
		return $user;
	}

	public function call_get_user_list() {
		$users = (array) get_users();

		foreach ( $users as $u ) {
			$this->data['users'][$u->user_login] = array(
				'id'			=> $u->ID,
				'login'			=> $u->user_login,
				'nice_name'		=> $u->user_nicename,
				'email'			=> $u->user_email,
				'url'			=> $u->user_url,
				'registered'	=> $u->user_registered,
				'display_name'	=> $u->display_name
			);
		}
	}

	public function init() {
		if ( 
			!empty( $_GET['pressfs'] )
			&& $_GET['pressfs'] == 1
			&& !empty( $_GET['call'] )
		) {
			$this->data = array(
				'_is_error'		=> FALSE,
				'_error_msg'	=> ''
			);
			$this->user = FALSE;

			add_action( 'init', array( &$this, 'parse_request' ) );
		}
	}

	public function parse_request() {
		header( 'Content-Type: application/json' );
		$this->user = $this->auth_user();

		$method = "call_{$_GET['call']}";
		if ( !method_exists( $this, $method ) ) {
			$this->send_error( 'Error in call request' );
		}

		$this->$method();
		echo json_encode( $this->data );
		exit;
	}

	public function send_error( $msg ) {
		$this->data['_is_error'] = TRUE;
		$this->data['_error_msg'] = $msg;

		# once we've reached an error condition
		# it is time to bail
		echo json_encode( $this->data );
		exit;
	}
} # class PressFS
