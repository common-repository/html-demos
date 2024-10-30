<?php
/*
Plugin Name:  HTML Demos
Plugin URI:   http://joshbetz.com/2012/09/html-demos/
Description:  Manage HTML, CSS, and Javascript demos inside WordPress!
Version:      0.1
Author:       Josh Betz
Author URI:   http://joshbetz.com/
License:      GPLv2 or later
*/

class Html_Demos {

	private $boxes = array();
	private $js_libs = array();

	function __construct() {
		$this->boxes = array(
			'html' => __( 'HTML' ),
			'css' => __( 'CSS' ),
			'js' => __( 'JavaScript' )
		);
		$this->js_libs = array(
			'jquery' => 'jQuery'
		);

		add_action( 'init', array( $this, 'init' ) );
		add_filter( 'the_content', array( $this, 'the_content' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
		add_action( 'admin_print_footer_scripts', array( $this, 'codemirror_config' ) );

		add_action( 'save_post', array( $this, 'save_post' ), 10, 2 );

		register_activation_hook( __FILE__, array( $this, 'install' ) );
		register_deactivation_hook( __FILE__, array( $this, 'uninstall' ) );
	}

	function install() {
		global $wp_rewrite;
		$this->register_demo_post_type();
		$wp_rewrite->flush_rules();
	}

	function uninstall() {
		global $wp_rewrite;
		$wp_rewrite->flush_rules();
	}

	function init() {
		$this->register_demo_post_type();

		if ( isset( $_GET['post'] ) )
			$post = intval ( $_GET['post'] );

		if ( isset( $_GET['filetype'] ) )
			$type = $_GET['filetype'];

		if ( isset( $post, $type ) ) {
			$meta = get_post_meta( $post, $type, true );
			if ( ! empty( $meta ) ) {
				$url = site_url();
				switch ( $type ) {
					case 'css':
						header("Content-type: text/css");
						echo $meta;
						break;
					case 'js':
						header("Content-type: text/javascript");
						echo $meta;
						break;
				}
			}
			exit;
		}
	}

	function register_demo_post_type() {
		$args = array(
			'labels' => array(
				'name' => __( 'HTML Demos' ),
				'singular_name' => __( 'HTML Demo' ),
			),
			'public' => true,
			'has_archive' => true,
			'supports' => array( 'title', 'author', 'revisions', 'comments' ),
			'register_meta_box_cb' => array( $this, 'setup_editors' ),
			'taxonomies' => array( 'post_tag' ),
			'rewrite' => array(
				'slug' => apply_filters( 'html_demo_slug', 'demos' )
			)
		);
		register_post_type( 'html_demos', $args );
	}

	function setup_editors() {
		foreach ( $this->boxes as $box => $title )
			add_meta_box( $box, $title, array( $this, 'editor' ), 'html_demos', 'normal', 'high', array( 'name' => $box ) );

		add_meta_box( 'js_libs', __( 'JavaScript Library' ), array( $this, 'js_libs' ), 'html_demos', 'side' );
	}

	function editor( $post, $args ) {
		$name = $args['args']['name'];
		$content = esc_textarea( html_entity_decode( get_post_meta( $post->ID, $name, true ) ) );

		wp_nonce_field( basename( __FILE__ ), 'html_editor_nonce' );

		echo "<textarea id='html_demos_$name' name='$name' style='width:100%; resize:vertical;'>$content</textarea>";
	}

	function js_libs( $post ) {
		$library = get_post_meta( $post->ID, 'js_lib', true );

		echo "<select name='js_lib'>";
		echo "<option></option>";
		foreach ( $this->js_libs as $lib => $name ) {
			if ( $library == $lib ) $selected = ' selected';
			echo "<option value='$lib'$selected>$name</option>";
		}
		echo "</select>";
	}

	function the_content( $content ) {
		global $post;

		if ( 'html_demos' == $post->post_type ) {
			$site_url = get_site_url();
			$content = "";

			$html = get_post_meta( $post->ID, 'html', true );
			$css = get_post_meta( $post->ID, 'css', true );
			$js = get_post_meta( $post->ID, 'js', true );
			$lib = get_post_meta( $post->ID, 'js_lib', true );

			if ( ! empty( $lib ) )
				wp_enqueue_script( $lib );

			if ( ! empty( $js ) )
				wp_enqueue_script( "html_demo_{$post->ID}", sprintf( '%s/?post=%s&filetype=js', $site_url, intval( $post->ID ) ) );

			if ( ! empty ( $css ) )
				wp_enqueue_style( "html_demo_{$post->ID}", sprintf( '%s/?post=%s&filetype=css', $site_url, intval( $post->ID ) ) );

			$content .= $html;
		}

		return $content;
	}

	function admin_scripts() {
		wp_enqueue_style( 'codemirror', plugins_url( 'CodeMirror/lib/codemirror.css', __FILE__ ) );
		wp_enqueue_style( 'codemirror_theme', plugins_url( 'CodeMirror/theme/blackboard.css', __FILE__ ) );

		wp_enqueue_script( 'codemirror', plugins_url( 'CodeMirror/lib/codemirror.js', __FILE__ ) );
		wp_enqueue_script( 'codemirror_html', plugins_url( 'CodeMirror/mode/xml/xml.js', __FILE__ ), array( 'codemirror' ) );
		wp_enqueue_script( 'codemirror_css', plugins_url( 'CodeMirror/mode/css/css.js', __FILE__ ), array( 'codemirror' ) );
		wp_enqueue_script( 'codemirror_javascript', plugins_url( 'CodeMirror/mode/javascript/javascript.js', __FILE__ ), array( 'codemirror' ) );
	}

	function codemirror_config() { ?>
		<script>
			CodeMirror.fromTextArea(document.getElementById('html_demos_html'), {
				lineNumbers: true,
				mode: 'xml'
			});
			CodeMirror.fromTextArea(document.getElementById('html_demos_css'), {
				lineNumbers: true,
				mode: 'css'
			}),
			CodeMirror.fromTextArea(document.getElementById('html_demos_js'), {
				lineNumbers: true,
				mode: 'javascript'
			});
		</script>
<?php }

	function save_post( $id, $post ) {

		if ( ! isset( $_POST['html_editor_nonce' ] ) || ! wp_verify_nonce( $_POST['html_editor_nonce'], basename( __FILE__ ) ) )
			return;

		$post_type = get_post_type_object( $post->post_type );

		if ( !current_user_can( $post_type->cap->edit_post, $id ) )
			return;

		// Save JS Lib choice
		$js_lib = get_post_meta( $id, 'js_lib', true );
		$new = isset( $_POST['js_lib'] ) ? $_POST['js_lib'] : '';
		if ( ! empty( $new ) )
			update_post_meta( $id, 'js_lib', $new );
		else if ( empty( $new ) && ! empty( $js_lib ) )
			delete_post_meta( $id, 'js_lib' );

		// Save HTML, CSS, JS
		foreach ( $this->boxes as $field => $name ) {
			$new = isset( $_POST[$field] ) ? $_POST[$field] : '';
			update_post_meta( $id, $field, $new );
		}

	}

}

new Html_Demos();
