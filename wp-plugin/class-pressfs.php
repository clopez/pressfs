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

	public function call_get_category_list() {
		$cats = (array) get_categories( array(
			'hierarchical'		=> FALSE,
			'hide_empty'		=> FALSE
		) );

		foreach ( $cats as $c ) {
			$parent = '';
			if ( $c->parent != 0 ) {
				$cat_parent = get_category( $c->parent );
				$parent = $cat_parent->name;
			}

			$this->data['categories'][$c->term_id] = array(
				'id'			=> $c->term_id,
				'name'			=> $c->name,
				'slug'			=> $c->slug,
				'description'	=> $c->description,
				'count'			=> $c->count,
				'parent'		=> $parent
			);
		}
	}

	public function call_get_page_list() {
		$pages = (array) get_pages( array (
			'hierarchical'		=> FALSE
		) );

		foreach ( $pages as $p ) {
			$page_url = get_permalink( $p->ID );

			$parent = '';
			if ( $p->post_parent != 0 ) {
				$page_parent = get_page( $p->post_parent );
				$parent = "{$p->ID}-{$page_parent->title}";
			}

			$this->data['pages'][$p->ID] = array(
				'id'			=> $p->ID,
				'date-gmt'		=> $p->post_date_gmt,
				'title'			=> $p->post_title,
				'status'		=> $p->post_status,
				'password'		=> $p->post_password,
				'type'			=> $p->post_type,
				'url'			=> $page_url,
				'name'			=> $p->post_name,
				'parent'		=> $parent
			);
		}
	}

	public function call_get_post( $arg_post_id = FALSE ) {
		$post_id = 0;
		if ( !empty( $_GET['post_id'] ) ) {
			$post_id = (int) $_GET['post_id'];
		}

		if ( $arg_post_id != FALSE ) {
			$post_id = (int) $arg_post_id;
		}

		$p = get_post( $post_id );
		$post_url = get_permalink( $p->ID );

		$post_data = array(
			'id'			=> $p->ID,
			'date-gmt'		=> $p->post_date_gmt,
			'content'		=> $p->post_content,
			'title'			=> $p->post_title,
			'status'		=> $p->post_status,
			'password'		=> $p->post_password,
			'type'			=> $p->post_type,
			'url'			=> $post_url,
			'name'			=> $p->post_name,
		);

		$this->data['post'] = $post_data;
		return $post_data;
	}

	public function call_get_post_list() {
		$post_total = 0;
		foreach ( (array) wp_count_posts() as $type => $count ) {
			$post_total += $count;
		}

		$posts = (array) get_posts( array (
			'numberposts'	=> $post_total,
			'post_status'	=> 'any'
		) );

		foreach ( $posts as $p ) {
			$this->data['posts'][$p->ID] = $this->call_get_post( $p->ID );
		}
	}

	public function call_get_tag_list() {
		foreach ( (array) get_tags() as $t ) {
			$this->data['tags'][$t->term_id] = array(
				'id'			=> $t->term_id,
				'name'			=> $t->name,
				'slug'			=> $t->slug,
				'description'	=> $t->description,
				'count'			=> $t->count
			);
		}
	}

	public function call_get_user_list() {
		$users = (array) get_users();

		foreach ( $users as $u ) {
			$this->data['users'][$u->user_login] = array(
				'id'			=> $u->ID,
				'login'			=> $u->user_login,
				'nice-name'		=> $u->user_nicename,
				'email'			=> $u->user_email,
				'url'			=> $u->user_url,
				'registered'	=> $u->user_registered,
				'display-name'	=> $u->display_name
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
