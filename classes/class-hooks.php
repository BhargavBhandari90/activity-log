<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class HT_Hooks {

	protected function _add_log_attachment( $action, $attachment_id ) {
		$post = get_post( $attachment_id );

		$history = new HT_Model();

		$history->action         = $action;
		$history->object_type    = 'Attachment';
		$history->object_subtype = $post->post_type;
		$history->object_id      = $attachment_id;
		$history->object_name    = get_the_title( $post->ID );

		$history->insert();
	}
	
	protected function _add_log_plugin( $action, $plugin_name ) {
		ht_insert_log( array(
			'action'      => $action,
			'object_type' => 'Plugin',
			'object_id'   => 0,
			'object_name' => $plugin_name,
		) );
	}
	
	public function init() {
		add_filter( 'wp_login_failed', array( &$this, 'hooks_wrong_password' ) );
		add_action( 'wp_login', array( &$this, 'hooks_wp_login' ) );
		add_action( 'wp_logout', array( &$this, 'hooks_wp_logout' ) );
		add_action( 'delete_user', array( &$this, 'hooks_delete_user' ) );
		add_action( 'user_register', array( &$this, 'hooks_user_register' ) );
		add_action( 'profile_update', array( &$this, 'hooks_profile_update' ) );

		add_action( 'activated_plugin', array( &$this, 'hooks_activated_plugin' ) );
		add_action( 'deactivated_plugin', array( &$this, 'hooks_deactivated_plugin' ) );
	}

	public function admin_init() {
		add_action( 'transition_post_status', array( &$this, 'hooks_transition_post_status' ), 10, 3 );
		add_action( 'delete_post', array( &$this, 'hooks_delete_post' ) );

		add_action( 'add_attachment', array( &$this, 'hooks_add_attachment' ) );
		add_action( 'edit_attachment', array( &$this, 'hooks_edit_attachment' ) );
		add_action( 'delete_attachment', array( &$this, 'hooks_delete_attachment' ) );
	}

	public function hooks_delete_attachment( $attachment_id ) {
		$this->_add_log_attachment( 'deleted', $attachment_id );
	}

	public function hooks_edit_attachment( $attachment_id ) {
		$this->_add_log_attachment( 'updated', $attachment_id );
	}

	public function hooks_add_attachment( $attachment_id ) {
		$this->_add_log_attachment( 'added', $attachment_id );
	}

	public function hooks_deactivated_plugin( $plugin_name ) {
		$this->_add_log_plugin( 'deactivated', $plugin_name );
	}

	public function hooks_activated_plugin( $plugin_name ) {
		$this->_add_log_plugin( 'activated', $plugin_name );
	}

	public function hooks_profile_update( $user_id ) {
		$user = get_user_by( 'id', $user_id );
		
		ht_insert_log( array(
			'action'      => 'updated',
			'object_type' => 'User',
			'object_id'   => $user->ID,
			'object_name' => $user->user_nicename,
		) );
	}

	public function hooks_user_register( $user_id ) {
		$user = get_user_by( 'id', $user_id );

		ht_insert_log( array(
			'action'      => 'created',
			'object_type' => 'User',
			'object_id'   => $user->ID,
			'object_name' => $user->user_nicename,
		) );
	}

	public function hooks_delete_user( $user_id ) {
		$user = get_user_by( 'id', $user_id );
		
		ht_insert_log( array(
			'action'      => 'deleted',
			'object_type' => 'User',
			'object_id'   => $user->ID,
			'object_name' => $user->user_nicename,
		) );
	}

	public function hooks_wrong_password() {
		ht_insert_log( array(
			'action'      => 'wrong_password',
			'object_type' => 'User',
			'user_id'     => 0,
			'object_id'   => 0,
			'object_name' => $_REQUEST['log'],
		) );
	}

	public function hooks_wp_login( $user ) {
		$user = get_user_by( 'login', $user );

		$history = new HT_Model();

		$history->action      = 'logged_in';
		$history->user_id     = $user->ID;
		$history->object_type = 'User';
		$history->object_id   = $user->ID;
		$history->object_name = $user->user_nicename;

		$history->insert();
	}

	public function hooks_wp_logout() {
		$user = wp_get_current_user();

		$history = new HT_Model();

		$history->action      = 'logged_out';
		$history->user_id     = $user->ID;
		$history->object_type = 'User';
		$history->object_id   = $user->ID;
		$history->object_name = $user->user_nicename;

		$history->insert();
	}

	public function hooks_transition_post_status( $new_status, $old_status, $post ) {
		$action = '';

		if ( 'auto-draft' === $old_status && ( 'auto-draft' !== $new_status && 'inherit' !== $new_status ) ) {
			// page created
			$action = 'created';
		}
		elseif ( 'auto-draft' === $new_status || ( 'new' === $old_status && 'inherit' === $new_status ) ) {
			// nvm.. ignore it.
			return;
		}
		elseif ( "trash" === $new_status ) {
			// page was deleted.
			$action = 'deleted';
		}
		else {
			// page updated. i guess.
			$action = 'updated';
		}

		if ( wp_is_post_revision( $post->ID ) )
			return;

		$history = new HT_Model();

		$history->action         = $action;
		$history->user_id        = get_current_user_id();
		$history->object_type    = 'Post';
		$history->object_subtype = $post->post_type;
		$history->object_id      = $post->ID;
		$history->object_name    = get_the_title( $post->ID );

		$history->insert();
	}

	public function hooks_delete_post( $post_id ) {
		if ( wp_is_post_revision( $post_id ) )
			return;

		$post = get_post( $post_id );

		if ( 'auto-draft' === $post->post_status || 'inherit' === $post->post_status ) {
			return;
		}

		$history = new HT_Model();

		$history->action         = 'deleted';
		$history->user_id        = get_current_user_id();
		$history->object_type    = 'Post';
		$history->object_subtype = $post->post_type;
		$history->object_id      = $post->ID;
		$history->object_name    = get_the_title( $post->ID );

		$history->insert();
	}
	
	public function __construct() {
		add_action( 'init', array( &$this, 'init' ) );
		add_action( 'admin_init', array( &$this, 'admin_init' ) );
	}
}