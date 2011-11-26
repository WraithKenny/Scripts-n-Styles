<?php
/**
 * Scripts n Styles Admin Class
 * 
 * Allows WordPress admin users the ability to add custom CSS
 * and JavaScript directly to individual Post, Pages or custom
 * post types.
 */
 
require_once( 'class.SnS_Admin_Meta_Box.php' );
require_once( 'class.SnS_Settings_Page.php' );
require_once( 'class.SnS_Usage_Page.php' );
require_once( 'class.SnS_AJAX.php' );

class SnS_Admin
{
    /**#@+
     * Constants
     */
	const MENU_SLUG = 'sns';
	const VERSION = '3.beta.3.2';
    /**#@-*/
	
    /**
	 * Initializing method.
     * @static
     */
	static function init() {
		add_action( 'admin_menu', array( 'SnS_Admin_Meta_Box', 'init' ) );
		
		add_action( 'admin_menu', array( 'SnS_Settings_Page', 'init' ) );
		add_action( 'admin_menu', array( 'SnS_Usage_Page', 'init' ) );
		
		add_action( 'admin_init', array( 'SnS_AJAX', 'init' ) );
		
		$plugin_file = plugin_basename( Scripts_n_Styles::$file ); 
		add_filter( "plugin_action_links_$plugin_file", array( __CLASS__, 'plugin_action_links') );
		
		register_activation_hook( Scripts_n_Styles::$file, array( __CLASS__, 'upgrade' ) );
	}
	
    /**
	 * Settings Page help
     */
	function help() {
		global $wp_version; // Back Compat for now
		if ( version_compare( $wp_version, '3.2.1', '>') ) {
			get_current_screen()->add_help_tab( array(
				'title' => __('Overview'),
				'id' => 'options-help',
				'content' => 
					'<p>' . __( '<p>In default (non MultiSite) WordPress installs, both <em>Administrators</em> and 
					<em>Editors</em> can access <em>Scripts-n-Styles</em> on individual edit screens. 
					Only <em>Administrators</em> can access this Options Page. In MultiSite WordPress installs, only 
					<em>"Super Admin"</em> users can access either
					<em>Scripts-n-Styles</em> on individual edit screens or this Options Page. If other plugins change 
					capabilities (specifically "unfiltered_html"), 
					other users can be granted access.</p>', 'scripts-n-styles' ) . '</p>'
				)
			);
		
			get_current_screen()->set_help_sidebar(
				'<p><strong>' . __( 'For more information:', 'twentyeleven' ) . '</strong></p>' .
				'<p>' . __( '<a href="http://wordpress.org/extend/plugins/scripts-n-styles/faq/" target="_blank">Frequently Asked Questions</a>', 'scripts-n-styles' ) . '</p>' .
				'<p>' . __( '<a href="https://github.com/unFocus/Scripts-n-Styles" target="_blank">Source on github</a>', 'scripts-n-styles' ) . '</p>' .
				'<p>' . __( '<a href="http://wordpress.org/tags/scripts-n-styles" target="_blank">Support Forums</a>', 'scripts-n-styles' ) . '</p>'
			);
		}
	}
	
    /**
	 * Utility Method: Sets defaults if not previously set. Sets stored 'version' to VERSION.
     */
	static function upgrade() {
		$options = get_option( 'SnS_options' );
		if ( ! $options ) $options = array();
		$options[ 'version' ] = self::VERSION;
		update_option( 'SnS_options', $options );

		/*
		 * upgrade proceedure
		 */
		$posts = get_posts(
			array(
				'numberposts' => -1,
				'post_type' => 'any',
				'post_status' => 'any',
				'meta_query' => array(
					'relation' => 'OR',
					array( 'key' => '_SnS_scripts' ),
					array( 'key' => '_SnS_styles' ),
					array( 'key' => 'uFp_scripts' ),
					array( 'key' => 'uFp_styles' )
				)
			)
		);
		
		foreach( $posts as $post) {
			$styles = get_post_meta( $post->ID, '_SnS_styles', true );
			if ( empty( $styles ) )
				$styles = get_post_meta( $post->ID, 'uFp_styles', true );
			
			$scripts = get_post_meta( $post->ID, '_SnS_scripts', true );
			if ( empty( $scripts ) )
				$scripts = get_post_meta( $post->ID, 'uFp_scripts', true );
			
			$SnS = array();
			if ( ! empty( $styles ) ) 
				$SnS[ 'styles' ] = $styles;
			
			if ( ! empty( $scripts ) ) 
				$SnS[ 'scripts' ] = $scripts;
			
			if ( ! empty( $SnS ) ) 
				update_post_meta( $post->ID, '_SnS', $SnS );
			
			delete_post_meta( $post->ID, 'uFp_styles' );
			delete_post_meta( $post->ID, 'uFp_scripts' );
			delete_post_meta( $post->ID, '_SnS_styles' );
			delete_post_meta( $post->ID, '_SnS_scripts' );
		}

	}
	
    /**
	 * Utility Method: Compares VERSION to stored 'version' value.
     */
	static function upgrade_check() {
		$options = get_option( 'SnS_options' );
		if ( ! isset( $options[ 'version' ] ) || version_compare( self::VERSION, $options[ 'version' ], '>' ) )
			self::upgrade();
	}
	
    /**
	 * Adds link to the Settings Page in the WordPress "Plugin Action Links" array.
	 * @param array $actions
	 * @return array
     */
	static function plugin_action_links( $actions ) {
		$actions[ 'settings' ] = '<a href="' . menu_page_url( self::MENU_SLUG, false ) . '"/>Settings</a>';
		return $actions;
	}
	
}

?>