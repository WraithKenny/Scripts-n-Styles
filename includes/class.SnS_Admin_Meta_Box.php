<?php
/**
 * SnS_Admin_Meta_Box
 * 
 * Allows WordPress admin users the ability to add custom CSS
 * and JavaScript directly to individual Post, Pages or custom
 * post types.
 */
		
class SnS_Admin_Meta_Box
{
    /*
     * Constants
     */
	const NONCE_NAME = 'scripts_n_styles_noncename';
	
	static $post_types;
	
    /**
	 * Initializing method. 
	 */
	static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'save_post', array( __CLASS__, 'save_post' ) );
		
		add_filter( 'mce_buttons_2', array( __CLASS__, 'mce_buttons_2' ) );
		add_filter( 'tiny_mce_before_init', array( __CLASS__, 'tiny_mce_before_init' ) );
		add_filter( 'mce_css', array( __CLASS__, 'mce_css' ) );
	}
	
	function mce_buttons_2( $buttons ) {
		global $post;
		$SnS = get_post_meta( $post->ID, '_SnS', true );
		$styles = isset( $SnS['styles'] ) ? $SnS[ 'styles' ]: array();
		
		if ( ! empty( $styles[ 'classes_mce' ] ) )
			array_unshift( $buttons, 'styleselect' );
		
		return $buttons;
	}
	function tiny_mce_before_init( $initArray ) {
		global $post;
		$SnS = get_post_meta( $post->ID, '_SnS', true );
		$styles = isset( $SnS['styles'] ) ? $SnS[ 'styles' ]: array();
		
		// Add div as a format option, should probably use a string replace thing here.
		// Better yet, a setting for adding these. Postpone for now.
		//$initArray['theme_advanced_blockformats'] = "p,address,pre,h1,h2,h3,h4,h5,h6,div";
		
		if ( ( ! empty( $styles[ 'classes_body' ] ) || ! empty( $styles[ 'classes_post' ] ) ) && ! isset( $initArray['body_class'] ) )
			$initArray['body_class'] = '';	
		
		// Add body_class (and/or maybe post_class) values... somewhat problematic.
		if ( ! empty( $styles[ 'classes_body' ] ) )
			$initArray['body_class'] .= ' ' . $styles[ 'classes_body' ];
		if ( ! empty( $styles[ 'classes_post' ] ) )
			$initArray['body_class'] .= ' ' . $styles[ 'classes_post' ];
		
		// In case Themes or plugins have added style_formats, not tested.
		if ( isset( $initArray['style_formats'] ) )
			$style_formats = json_decode( $initArray['style_formats'], true );
		else
			$style_formats = array();
		
		if ( ! empty( $styles[ 'classes_mce' ] ) )
			foreach ( $styles[ 'classes_mce' ] as $format )
				$style_formats[] = $format;
		
		if ( ! empty( $style_formats ) )
			$initArray['style_formats'] = json_encode( $style_formats );
		
		return $initArray;
	}
	
    /**
	 * Admin Action: 'mce_css'
	 * Adds a styles sheet to TinyMCE via ajax that contains the current styles data.
     */
	static function mce_css( $mce_css ) {
		global $post;
		$url = admin_url( 'admin-ajax.php' );
		$url = wp_nonce_url( $url, 'sns_tinymce_styles' );
		$url = add_query_arg( 'post_id', $post->ID, $url );
		$url = add_query_arg( 'action', 'sns_tinymce_styles', $url );
		$mce_css .= ',' . $url;
		return $mce_css;
	}

    /**
	 * Admin Action: 'add_meta_boxes'
	 * Main Meta Box function. Checks restriction options and display options, calls add_meta_box() and adds actions for adding admin CSS and JavaScript.
     */
	static function add_meta_boxes() {
		if ( current_user_can( 'unfiltered_html' ) ) {
			self::$post_types = get_post_types( array('show_ui' => true, 'public' => true) ); // updated for http://core.trac.wordpress.org/changeset/18234
			foreach ( self::$post_types as $post_type ) {
				add_meta_box( 'SnS_meta_box', __( 'Scripts n Styles', 'scripts-n-styles' ), array( __CLASS__, 'admin_meta_box' ), $post_type, 'normal', 'high' );
			}
			add_filter( 'default_hidden_meta_boxes', array( __CLASS__,  'default_hidden_meta_boxes' )  );
			add_action( "admin_print_styles", array( __CLASS__, 'meta_box_styles'));
			add_action( "admin_print_scripts", array( __CLASS__, 'meta_box_scripts'));
			add_filter( 'contextual_help', array( 'SnS_Admin', 'help' ) );
		}
	}
	
	static function default_hidden_meta_boxes( $hidden ) {
		$options = get_option( 'SnS_options' );
		if ( ! isset( $options[ 'metabox' ] ) ) 
			$hidden[] = 'SnS_meta_box';
		else if ( 'yes' == $options[ 'metabox' ] )
			$hidden[] = 'SnS_meta_box';
		
    	return $hidden;
	}
	
    /**
	 * Admin Action: 'add_meta_boxes'
	 * Outputs the Meta Box. Only called on callback from add_meta_box() during the add_meta_boxes action.
	 * @param unknown_type WordPress Post object.
     */
	static function admin_meta_box( $post ) {
		$registered_handles = Scripts_n_Styles::get_wp_registered();
		$SnS = get_post_meta( $post->ID, '_SnS', true );
		$styles = isset( $SnS['styles'] ) ? $SnS[ 'styles' ]: array();
		$scripts = isset( $SnS['scripts'] ) ? $SnS[ 'scripts' ]: array();
		
		$screen = get_current_screen();
		$position = get_user_option( "current_sns_tab" );
		if ( ! in_array( $position, array( 's0', 's1', 's2', 's3' ) ) ) $position = 's0';
		wp_nonce_field( Scripts_n_Styles::$file, self::NONCE_NAME );
		?>
			<ul class="wp-tab-bar">
				<li<?php echo ( 's0' == $position ) ? ' class="wp-tab-active"': ''; ?>><a href="#SnS_scripts-tab"><?php _e( 'Scripts', 'scripts-n-styles' ) ?></a></li>
				<li<?php echo ( 's1' == $position ) ? ' class="wp-tab-active"': ''; ?>><a href="#SnS_styles-tab"><?php _e( 'Styles', 'scripts-n-styles' ) ?></a></li>
				<li<?php echo ( 's2' == $position ) ? ' class="wp-tab-active"': ''; ?>><a href="#SnS_classes_body-tab"><?php _e( 'Classes', 'scripts-n-styles' ) ?></a></li>
				<li<?php echo ( 's3' == $position ) ? ' class="wp-tab-active"': ''; ?>><a href="#SnS_enqueue_scripts-tab"><?php _e( 'Include Scripts', 'scripts-n-styles' ) ?></a></li>
			</ul>
			
			<div class="wp-tab-panel" id="SnS_scripts-tab">
				<p><em><?php _e( "This code will be included <strong>verbatim</strong> in <code>&lt;script></code> tags at the end of your page's (or post's)", 'scripts-n-styles' ) ?> ...</em></p>
				<label for="SnS_scripts_in_head" class="title"><?php _e( '<strong>Scripts</strong> (for the <code>head</code> element):', 'scripts-n-styles' ) ?> </label>
				<textarea class="codemirror js" name="SnS_scripts_in_head" id="SnS_scripts_in_head" rows="5" cols="40" style="width: 98%;"><?php echo isset( $scripts[ 'scripts_in_head' ] ) ? $scripts[ 'scripts_in_head' ] : ''; ?></textarea>
				<p><em>... <code>&lt;/head></code> <?php _e( 'tag', 'scripts-n-styles' ) ?>.</em></p>
				<label for="SnS_scripts" class="title"><strong>Scripts</strong>: </label>
				<textarea class="codemirror js" name="SnS_scripts" id="SnS_scripts" rows="5" cols="40" style="width: 98%;"><?php echo isset( $scripts[ 'scripts' ] ) ? $scripts[ 'scripts' ] : ''; ?></textarea>
				<p><em>... <code>&lt;/body></code> <?php _e( 'tag', 'scripts-n-styles' ) ?>.</em></p>
			</div>
			
			<div class="wp-tab-panel" id="SnS_styles-tab">
				<label for="SnS_styles" class="title"><?php _e( '<strong>Styles</strong>:', 'scripts-n-styles' ) ?> </label>
				<textarea class="codemirror css" name="SnS_styles" id="SnS_styles" rows="5" cols="40" style="width: 98%;"><?php echo isset( $styles[ 'styles' ] ) ? $styles[ 'styles' ] : ''; ?></textarea>
				<p><em><?php _e( 'This code will be included <strong>verbatim</strong> in <code>&lt;style></code> tags in the <code>&lt;head></code> tag of your page (or post).', 'scripts-n-styles' ) ?></em></p>
			</div>
			
			<div class="wp-tab-panel" id="SnS_classes_body-tab">
				<strong class="title"><?php _e( 'Classes', 'scripts-n-styles' ) ?></strong>
				<div id="sns-classes">
					<p>
						<label for="SnS_classes_body"><?php _e( '<strong>Body Classes</strong>:', 'scripts-n-styles' ) ?> </label>
						<input name="SnS_classes_body" id="SnS_classes_body" type="text" class="code" style="width: 99%;"
							value="<?php echo isset( $styles[ 'classes_body' ] ) ? $styles[ 'classes_body' ] : ''; ?>" />
						<small><?php _e( 'Standard:', 'scripts-n-styles' ) ?> <code><?php self::current_classes( 'body', $post->ID ); ?></code></small>
					</p>
					<p>
						<label for="SnS_classes_post"><strong>Post Classes</strong>: </label>
						<input name="SnS_classes_post" id="SnS_classes_post" type="text" class="code" style="width: 99%;"
							value="<?php echo isset( $styles[ 'classes_post' ] ) ? $styles[ 'classes_post' ] : ''; ?>" />
						<small><?php _e( 'Standard:', 'scripts-n-styles' ) ?> <code><?php self::current_classes( 'post', $post->ID ); ?></code></small>
					</p>
					<p><em><?php _e( 'These <strong>space separated</strong> class names will be added to the <code>body_class()</code> or <code>post_class()</code> function (provided your theme uses these functions).', 'scripts-n-styles' ) ?></em></p>
				</div>
				
				<?php 
				/*
				 * Note: Styles Dropdown section only makes sense when Javascript is enabled. (Otherwise, no TinyMCE.)
				 */
				?>
				<div id="mce-dropdown-names" style="display: none;">
					<h4><?php _e( 'The Styles Dropdown', 'scripts-n-styles' ) ?></h4>
					<div id="add-mce-dropdown-names">
						<p><?php _e( 'Add (or update) a class for the "Styles" drop-down:', 'scripts-n-styles' ) ?></p>
						<p class="sns-mce-title">
							<label for="SnS_classes_mce_title"><?php _e( 'Title:', 'scripts-n-styles' ) ?></label>
							<input name="SnS_classes_mce_title" id="SnS_classes_mce_title"
								value="" type="text" class="code" style="width: 80px;" />
						</p>
						<p class="sns-mce-type">
							<label for="SnS_classes_mce_type"><?php _e( 'Type:', 'scripts-n-styles' ) ?></label>
							<select name="SnS_classes_mce_type" id="SnS_classes_mce_type" style="width: 80px;">
								<option value="inline"><?php _ex( 'Inline', 'css type', 'scripts-n-styles' ) ?></option>
								<option value="block"><?php _ex( 'Block', 'css type', 'scripts-n-styles' ) ?></option>
								<option value="selector"><?php _ex( 'Selector:', 'css type', 'scripts-n-styles' ) ?></option>
							</select>
						</p>
						<p class="sns-mce-element">
							<label for="SnS_classes_mce_element"><?php _e( 'Element:', 'scripts-n-styles' ) ?></label>
							<input name="SnS_classes_mce_element" id="SnS_classes_mce_element"
								value="" type="text" class="code" style="width: 80px;" />
						</p>
						<p class="sns-mce-classes">
							<label for="SnS_classes_mce_classes"><?php _e( 'Classes:', 'scripts-n-styles' ) ?></label>
							<input name="SnS_classes_mce_classes" id="SnS_classes_mce_classes"
								value="" type="text" class="code" style="width: 80px;" />
						</p>
						<p class="sns-mce-wrapper" style="display: none;">
							<label for="SnS_classes_mce_wrapper"><?php _e( 'Wrapper:', 'scripts-n-styles' ) ?></label>
							<input name="SnS_classes_mce_wrapper" id="SnS_classes_mce_wrapper" type="checkbox" value="true" />
						</p>
					</div>
					
					<div id="delete-mce-dropdown-names" style="display: none;">
						<p id="instructions-mce-dropdown-names"><?php _e( 'Classes currently in the dropdown:', 'scripts-n-styles' ) ?></p>
					</div>
				</div>
			</div>
			
			<div class="wp-tab-panel" id="SnS_enqueue_scripts-tab">
				<strong class="title">Include Scripts</strong>
				<select name="SnS_enqueue_scripts[]" id="SnS_enqueue_scripts" size="5" multiple="multiple" style="height: auto; float: left; margin: 6px 10px 8px 0;">
					<?php 
					if ( ! empty( $scripts[ 'enqueue_scripts' ] ) && is_array( $scripts[ 'enqueue_scripts' ] ) ) {
						foreach ( $registered_handles as $value ) { ?>
							<option value="<?php echo $value ?>"<?php foreach ( $scripts[ 'enqueue_scripts' ] as $handle ) selected( $handle, $value ); ?>><?php echo $value ?></option> 
						<?php }
					} else {
						foreach ( $registered_handles as $value ) { ?>
							<option value="<?php echo $value ?>"><?php echo $value ?></option> 
						<?php }
					} ?>
				</select>
				<?php if ( ! empty( $scripts[ 'enqueue_scripts' ] ) && is_array( $scripts[ 'enqueue_scripts' ] ) ) { ?>
					<p><?php _e( 'Currently Enqueued Scripts:', 'scripts-n-styles' ) ?>
					<?php foreach ( $scripts[ 'enqueue_scripts' ] as $handle )  echo '<code>' . $handle . '</code> '; ?>
					</p>
				<?php } ?>
				<p><em><?php _e( 'The chosen scripts will be enqueued and placed before your codes if your code is dependant on certain scripts (like jQuery).', 'scripts-n-styles' ) ?></em></p>
			</div>
		<?php
	}
	
	function current_classes( $type, $post_id ) {
		if ( 'body' == $type ) {
			global $wp_query, $pagenow;
			
			if ( 'post-new.php' == $pagenow ) {
				echo join( ' ', get_body_class( '', $post_id ) );
				echo ' ' . __( '(plus others once saved.)', 'scripts-n-styles' );
				return;
			}
			// This returns more of what actually get used on the theme side.
			$save = $wp_query;
			$param = ( 'page' == get_post_type( $post_id ) ) ? 'page_id': 'p';
			$wp_query = new WP_Query( "$param=$post_id" );
			echo join( ' ', get_body_class( '', $post_id ) );
			$wp_query = $save;
			
		} else {
			echo join( ' ', get_post_class( '', $post_id ) );
		}
	}
	
    /**
	 * Admin Action: 'admin_print_styles' Action added during 'add_meta_boxes' (which restricts output to Edit Screens).
	 * Enqueues the CSS for admin styling of the Meta Box.
     */
	static function meta_box_styles() {
		$options = get_option( 'SnS_options' );
		$cm_theme = isset( $options[ 'cm_theme' ] ) ? $options[ 'cm_theme' ] : 'default';
		
		wp_enqueue_style( 'codemirror', plugins_url( 'libraries/CodeMirror2/lib/codemirror.css', Scripts_n_Styles::$file), array(), '2.18' );
		wp_enqueue_style( "codemirror-$cm_theme", plugins_url( "libraries/CodeMirror2/theme/$cm_theme.css", Scripts_n_Styles::$file), array( 'codemirror' ), '2.18' );
		wp_enqueue_style( 'sns-meta-box-styles', plugins_url( 'css/meta-box-styles.css', Scripts_n_Styles::$file), array( 'codemirror' ), Scripts_n_Styles::VERSION );
	}
	
    /**
	 * Admin Action: 'admin_print_styles' Action added during 'add_meta_boxes' (which restricts output to Edit Screens).
	 * Enqueues the JavaScript for the admin Meta Box.
     */
	static function meta_box_scripts() {
		$options = get_option( 'SnS_options' );
		$cm_theme = isset( $options[ 'cm_theme' ] ) ? $options[ 'cm_theme' ] : 'default';
		
		wp_enqueue_script(
			'codemirror',
			plugins_url( 'libraries/CodeMirror2/lib/codemirror.js', Scripts_n_Styles::$file),
			array(),
			'2.18' );
		wp_enqueue_script(
			'codemirror-css',
			plugins_url( 'libraries/CodeMirror2/mode/css/css.js', Scripts_n_Styles::$file),
			array(  'codemirror' ),
			'2.18' );
		wp_enqueue_script(
			'codemirror-javascript',
			plugins_url( 'libraries/CodeMirror2/mode/javascript/javascript.js', Scripts_n_Styles::$file),
			array(  'codemirror' ),
			'2.18' );
		/*wp_register_script(
			'codemirror-xml',
			plugins_url( 'libraries/CodeMirror2/mode/xml/xml.js', Scripts_n_Styles::$file),
			array(  'codemirror' ),
			'2.11' );*/
		/*wp_register_script(
			'codemirror-htmlmixed',
			plugins_url( 'libraries/CodeMirror2/mode/htmlmixed/htmlmixed.js', Scripts_n_Styles::$file),
			array( 	'codemirror-xml',
					'codemirror-css',
					'codemirror-javascript'
				),
			'2.11' );*/
		/*wp_register_script(
			'codemirror-clike',
			plugins_url( 'libraries/CodeMirror2/mode/clike/clike.js', Scripts_n_Styles::$file),
			array(  'codemirror' ),
			'2.11' );
		wp_register_script(
			'codemirror-php',
			plugins_url( 'libraries/CodeMirror2/mode/php/php.js', Scripts_n_Styles::$file),
			array( 	'codemirror-xml',
					'codemirror-css',
					'codemirror-javascript',
					'codemirror-clike'
				),
			'2.11' );*/
		wp_enqueue_script(
			'sns-meta-box-scripts',
			plugins_url( 'js/meta-box-scripts.js', Scripts_n_Styles::$file),
			array( 	'editor',
					'jquery-ui-tabs',
					'codemirror-javascript',
					'codemirror-css'//,
					//'codemirror-htmlmixed',
					//'codemirror-php'
				),
			Scripts_n_Styles::VERSION, true );
			
		wp_localize_script( 'sns-meta-box-scripts', 'codemirror_options', array( 'theme' => $cm_theme ) );
	}
	
    /**
	 * Admin Action: 'save_post'
	 * Saves the values entered in the Meta Box when a post is saved (on the Edit Screen only, excluding autosaves) if the user has permission.
	 * @param int $post_id ID value of the WordPress post.
     */
	static function save_post( $post_id ) {
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( $_POST[ self::NONCE_NAME ], Scripts_n_Styles::$file )
			|| ! current_user_can( 'unfiltered_html' ) 
			|| wp_is_post_revision( $post_id ) // is needed for get_post_meta compatibility.
			|| ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
		) return;
			
		/* 
			NOTE: There is no current_user_can( 'edit_post' ) check here, because as far as I 
			can tell, in /wp-admin/post.php the calls edit_post(), write_post(), post_preview(), 
			wp_untrash_post(), etc., the check is already done prior to the 'save_post' action, 
			which is where this function is called. Other calls are from other pages so the 
			NONCE covers those cases, and that leaves autosave, which is also checked here. 
		*/
		
		$SnS = get_post_meta( $post_id, '_SnS', true );
		$scripts = isset( $SnS['scripts'] ) ? $SnS[ 'scripts' ]: array();
		$styles = isset( $SnS['styles'] ) ? $SnS[ 'styles' ]: array();
		
		$scripts = self::maybe_set( $scripts, 'scripts_in_head' );
		$scripts = self::maybe_set( $scripts, 'scripts' );
		$scripts = self::maybe_set( $scripts, 'enqueue_scripts' );
		$styles = self::maybe_set( $styles, 'styles' );
		$styles = self::maybe_set( $styles, 'classes_body' );
		$styles = self::maybe_set( $styles, 'classes_post' );
		
		// This one isn't posted, it's ajax only. Cleanup anyway.
		if ( isset( $styles[ 'classes_mce' ] ) && empty( $styles[ 'classes_mce' ] ) )
			unset( $styles[ 'classes_mce' ] );
		
		if ( empty( $scripts ) ) {
			if ( isset( $SnS['scripts'] ) )
				unset( $SnS['scripts'] );
		} else {
			$SnS['scripts'] = $scripts;
		}
		
		if ( empty( $styles ) ) {
			if ( isset( $SnS['styles'] ) )
				unset( $SnS['styles'] );
		} else {
			$SnS['styles'] = $styles;
		}
		
		if ( empty( $SnS ) )
			delete_post_meta( $post_id, '_SnS' );
		else
			update_post_meta( $post_id, '_SnS', $SnS );
	}
	
    /**
	 * maybe_set()
	 * Filters $o and Checks if the sent data $i is empty (intended to clear). If not, updates.
     */
	function maybe_set( $o, $i, $p = 'SnS_' ) {
		if ( empty( $_REQUEST[ $p . $i ] ) ) {
			if ( isset( $o[ $i ] ) ) unset( $o[ $i ] );
		} else {
			$o[ $i ] = $_REQUEST[ $p . $i ];
		}
		return $o;
	}
}
?>