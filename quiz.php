<?php
/*
Plugin Name: Quiz
Plugin URI: http://wordpress.org/extend/plugins/quiz/
Version: 1.3.1
Description: You provide a question for each post or page. Visitors must answer the question correctly to comment, unless they have post publishing capabilities.
Author: <a href="http://andyskelton.com/">Andy Skelton</a>, <a href="http://striderweb.com/">Stephen Rider</a> and <a href="http://coveredwebservices.com/">Mark Jaquith</a>
Text Domain: quiz
Domain Path: /lang
*/

// To manually place the quiz form in your comments form, use do_action('show_comment_quiz')

/*
    FIXME: Some translation strings contain code or other content that should not be translatable
	TODO: "Wrong answer" page -- Show comment content and a caution to copy content before going back
*/

class Comment_Quiz_Plugin {
	static $instance;
	var $option_version = '1.2';
	var $option_name = 'plugin_commentquiz_settings';

	function __construct() {
		self::$instance = $this;
		load_plugin_textdomain( 'quiz', false, basename( dirname( __FILE__ ) ) . '/lang' );
		$options = get_option( $this->option_name );

		if ( !isset( $options['last_opts_ver'] ) || $options['last_opts_ver'] != $this->option_version )
			$this->set_defaults();
		if( ! is_admin() ) {
			// This is so end users can use do_action('show_comment_quiz') in themes
			add_action( 'show_comment_quiz', array( $this, 'the_quiz' ) );
			// ...otherwise will add form automatically
			add_action( 'comment_form_after_fields', array( $this, 'the_quiz' ) );
			add_action( 'comment_form', array( $this, 'the_quiz' ) ); // compatibility with older themes that lack 'comment_form_after_fields' hook

			add_filter( 'preprocess_comment', array( $this, 'process' ), 1 );
		}
		add_action( 'admin_menu', array( $this, 'add_settings_page' ));
		add_action( 'admin_menu', array( $this, 'call_meta_box'     ));
		add_action( 'save_post', array( $this, 'save_meta_box'     ));
		add_action( 'wp_ajax_validate_quiz', array( $this, 'ajax_callback'     ));
		add_action( 'wp_ajax_nopriv_validate_quiz', array( $this, 'ajax_callback'     ));
	}

	function get_quiz( $id = null, $blankdefault = false ) {
		if ( !$id )
			$id = $GLOBALS['post']->ID;
		$quiz = get_post_meta( $id, 'quiz', true );
		if ( ( isset( $quiz['q'] ) && 'noquiz' == $quiz['q'] ) || ( isset( $quiz['a'] ) && 'noquiz' == $quiz['a'] ) ) {
			return false;
		}
		if(	empty( $quiz ) || empty( $quiz['q'] ) || empty( $quiz['a'] ) ) {
			if( $blankdefault ) {
				$quiz['q'] = '';
				$quiz['a'] = '';
			} else {
				$options = get_option( $this->option_name );
				$quiz['q'] = $options['def_q'];
				$quiz['a'] = $options['def_a'];
			}
		}

		return $quiz;
	}

	function set_quiz( $post_id, $quiz ) {
		$allowedtags = array(
			'abbr' => array(
				'title' => array ()),
			'acronym' => array(
				'title' => array ()),
			'b' => array(), 'strong' => array(),
			'br' => array(),
			'code' => array(),
			'em' => array (), 'i' => array (),
			'q' => array(
				'cite' => array ()),
			'strike' => array(),
			'sub' => array(), 'sup' => array(),
			'u' => array()
		);
		foreach( $quiz as $key => $value )
			$quiz[$key] = wp_kses( $value, $allowedtags );

		update_post_meta( $post_id, 'quiz', $quiz );
	}

	function ajax_callback() {
		$quiz = $this->get_quiz( $_REQUEST['post_id'] );
		if ( $quiz ) {
			$answers = array_map( 'trim', explode( ',', $quiz['a'] ) );
			foreach ( $answers as $answer ) {
				if ( $this->compare( $answer, $_REQUEST['a'] ) )
					wp_die( 0 );
			}
			wp_die( 1 );
		} else {
			wp_die( 0 );
		}
	}

	function get_quiz_form( $html = false, $validate = true ) {
		$def_quiz_form = '
<p id="commentquiz" style="clear:both">
	Anti-Spam Quiz: <label for="quiz">%question% </label><input type="text" name="quiz" id="quiz" size="22" tabindex="4" value="" />
</p>
';
		$options = get_option( $this->option_name );
		if( ! $options ||
			empty( $options['quiz_form'] ) ||
		 	( $validate && ! strpos( $options['quiz_form'], '%question%' ) )
		) {
			$quiz_form = $def_quiz_form;
		} else {
			$quiz_form = $options['quiz_form'];
		}

		if( $html ) {
			$quiz_form = htmlspecialchars( $quiz_form );
		}
		return $quiz_form;
	}

	function upgrade_slashing_12( $curr_options ) {
		update_option( $this->option_name, stripslashes_deep( (array) $curr_options ) );
		return get_option( $this->option_name );
	}

	function set_defaults( $mode = 'merge' ) {
	// $mode can be set to "unset" or "reset"
		if ( 'unset' == $mode ) {
			delete_option( $this->option_name );
			return true;
		}

		$defaults = array(
			'last_opts_ver' => $this->option_version,
			'def_q' => __( 'Which is warmer, ice or steam?', 'quiz' ),
			'def_a' => __( 'steam', 'quiz' ),
			'quiz_form' => $this->get_quiz_form()
		);

		if ( 'reset' == $mode ) {
			delete_option( $this->option_name );
			add_option( $this->option_name, $defaults );
		} else if ( $curr_options = get_option( $this->option_name ) ) {
		// Merge existing prefs with new or missing defaults

		// Version-specific upgrades
			if ( !isset( $curr_options['last_opts_ver'] ) || version_compare( $curr_options['last_opts_ver'], '1.2', '<' ) )
				$curr_options = $this->upgrade_slashing_12( $curr_options ); // Upgrade to remove slashes

		// Merge
			$curr_options = array_merge( $defaults, $curr_options );
			$curr_options['last_opts_ver'] = $this->option_version; // always update
			update_option( $this->option_name, $curr_options );
		} else {
			add_option( $this->option_name, $defaults );
		}
		return true;
	}
// ****************************
//    Comment Form Functions
// ****************************

	var $form_shown = 0;

	function the_quiz() {
		// only show the form once on a page
		if ( $this->form_shown++ > 0 ) return false;

		global $current_user, $post, $id;
		$quiz = $this->get_quiz( $id );
		if ( ! $quiz ) return false;
		$quiz_form = $this->get_quiz_form();

		echo str_replace( '%question%', $quiz['q'], $quiz_form );
		add_action( 'wp_print_footer_scripts', array( $this, 'form_position' ) );
		return true;
	}

// try to put form in a better location than _after_ the submit button!
	function form_position() {
		// if the the_quiz() was only called from comment_form hook...
		if( did_action( 'show_comment_quiz' ) > 0 || did_action('comment_form_after_fields') > 0 ) return false;

		$form_position = '
<script>
var u=document.getElementById("comment");
if ( u ) {
	u.parentNode.parentNode.insertBefore(document.getElementById("commentquiz"), u.parentNode);
}
</script>
';
		echo $form_position;
		return true;
	}

	function process( $commentdata ) {
		extract( $commentdata );
		if( !current_user_can( 'publish_posts' ) &&
			$comment_type != 'pingback' &&
			$comment_type != 'trackback' )
			{
			$quiz = $this->get_quiz( $comment_post_ID );

			if ( $quiz ) {
				if ( empty( $_POST['quiz'] ) )
					wp_die( __( 'You must answer the question to post a comment. Please <a href="javascript:window.history.back()">go back</a> and try again.', 'quiz' ) );

				$answer = $quiz['a'];
				$answer = array_map( 'trim', explode( ',', $answer ) );
				$response = stripslashes( $_POST['quiz'] );

				foreach( $answer as $a ) {
					if ( $this->compare( $a, $response ) )
						return $commentdata;
				}
				wp_die( __( 'You answered the question incorrectly.  Please <a href="javascript:window.history.back()">go back</a> and try again.', 'quiz' ) );
			}
		}
		return $commentdata;
	}

	function compare( $a, $b ) {
		$a = trim( strtolower( strip_tags( $a ) ) );
		$b = trim( strtolower( strip_tags( $b ) ) );

		return apply_filters( 'comment_quiz_compare', $a === $b, $a, $b );
	}

// ************************
//    Meta Box Functions
//    for post edit page
// ************************

	function call_meta_box() {
		if( function_exists( 'add_meta_box' ) ) {
			add_meta_box( 'edit_comment_quiz', 'Comment Quiz', array( $this, 'meta_box' ), 'post', 'normal' );
			add_meta_box( 'edit_comment_quiz', 'Comment Quiz', array( $this, 'meta_box' ), 'page', 'normal' );
		}
	}

	function meta_box() {
		global $post;
		$quiz = $this->get_quiz( $post->ID, true );
		if( $quiz ) {
			$q = esc_attr( $quiz['q'] );
		} else {
			$q = 'noquiz';
		}
		$a = esc_attr( $quiz['a'] );
		$nonce = wp_create_nonce( plugin_basename( __FILE__ ) );
		$howto1 = __( 'Enter "noquiz" if you don\'t want a question for this post, or leave it blank to use the default question.', 'quiz' );
		$howto2 = __( 'You may enter multiple correct answers, separated by commas; e.g. <code>color, colour</code>', 'quiz' );
		$qlabel = __( 'Question', 'quiz' );
		$alabel = __( 'Answer', 'quiz' );
		echo <<< BOX
		<input type="hidden" name="comment_quiz_metabox" id="comment_quiz_metabox" value="$nonce" />
		<p><input type="text" name="quizQuestion" id="quizQuestion" size="25" value="$q" tabindex="3" /><label for="quizQuestion"> $qlabel</label><br />
		<input type="text" name="quizAnswer" id="quizAnswer" size="25" value="$a" tabindex="3" /><label for="quizAnswer"> $alabel</label>
		<span class="howto">$howto1<br />$howto2</span></p>
BOX;
	}

	function save_meta_box( $post_id ) {

		if ( !isset( $_POST['comment_quiz_metabox'] ) || !wp_verify_nonce( $_POST['comment_quiz_metabox'], plugin_basename(__FILE__) ) ) {
			 return $post_id;
		} else if ( 'page' == $_POST['post_type'] ) {
			if ( ! current_user_can( 'edit_page', $post_id ) )
				return $post_id;
		} else if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return $post_id;
		}

		$new_quiz = array( 'q'=>stripslashes($_POST['quizQuestion']), 'a'=>stripslashes($_POST['quizAnswer']) );
		$this->set_quiz( $post_id, $new_quiz );

		return $post_id;
	}

// *****************************
//    Settings Page Functions
// *****************************

	function options_url() {
		return admin_url( 'options-general.php?page=quiz' );
	}

	function add_settings_page() {
		if( current_user_can('manage_options') ) {
			$page = add_options_page( __( 'Comment Quiz', 'quiz' ), __( 'Quiz', 'quiz' ), 'manage_options', 'quiz', array( $this, 'settings_page' ) );
			add_filter( 'plugin_action_links', array( $this, 'filter_plugin_actions' ), 10, 2 );
			add_action( 'load-' . $page, array( $this, 'save_options' ) );
			return $page;
		}
		return false;
	}

// Add action link(s) to plugins page
	function filter_plugin_actions( $links, $file ){
		//Static so we don't call plugin_basename on every plugin row.
		static $this_plugin;
		if( ! $this_plugin ) $this_plugin = plugin_basename( __FILE__ );

		if( $file == $this_plugin ){
			$settings_link = '<a href="' . $this->options_url() . '">' . __( 'Settings' ) . '</a>';
			array_unshift( $links, $settings_link );
		}
		return $links;
	}

// this function used by the settings page to display set options in the form controls when the page is opened
	function checktext( $options, $optname, $optdefault = '' ) {
		// for text boxes and textareas
		return esc_attr( ( $options[$optname] ) ? $options[$optname] : $optdefault );
	}

// Saving the options
	function save_options() {
		if ( isset( $_POST['save_settings'] ) ) {
			check_admin_referer( 'commentquiz-update-options' );
			update_option( $this->option_name, stripslashes_deep( $_POST['commentquiz_options'] ) );
			wp_redirect( add_query_arg( 'updated', 1 ) );
			exit();
		}
	}

// finally, the Settings Page itself
	function settings_page() {
		// get options for use in formsetting functions
		$opts = get_option( $this->option_name );

	?>
<div class="wrap">
	<h2><?php _e( 'Comment Quiz', 'quiz' ); ?></h2>
	<form method="post" action="<?php echo $this->options_url(); ?>">
		<?php
		if ( function_exists( 'wp_nonce_field' ) )
			wp_nonce_field( 'commentquiz-update-options' );
		?>
		<table class="form-table">
			<tbody>
				<tr valign="top">
					<th scope="row"><?php _e( 'Default Quiz', 'quiz' ) ?></th>
					<td><input type="text" name="commentquiz_options[def_q]" id="def_q" size="35" value="<?php echo $this->checktext( $opts, 'def_q', '' ); ?>" /><label for="def_q"> <?php _e( 'Question', 'quiz' ); ?></label><br />
						<input type="text" name="commentquiz_options[def_a]" id="def_a" size="15" value="<?php echo $this->checktext( $opts, 'def_a', '' ); ?>"/><label for="def_a"> <?php _e( 'Answer', 'quiz' ); ?></label><br />
						<span><?php _e( 'You may enter multiple correct answers, separated by commas; e.g. <code>color, colour</code>', 'quiz' ) ?></span>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php _e( 'Quiz Form', 'quiz' ) ?></th>
					<td><textarea name="commentquiz_options[quiz_form]" id="quiz_form" cols="60" rows="6"><?php echo $this->get_quiz_form( true, false ); ?></textarea><br />
					<span><?php _e( 'The form must contain a %question% placeholder.<br />To reset to default, blank this field and save settings.', 'quiz' ) ?></span>
					</td>
				</tr>
			</tbody>
		</table>
		<div class="submit">
			<input type="submit" name="save_settings" class="button-primary" value="<?php _e( 'Save Changes' ) ?>" /></div>
	</form>
</div><!-- wrap -->
<?php
	}

} // END class commentquiz

new Comment_Quiz_Plugin;

// register_activation_hook( __FILE__, array( $commentquiz, 'set_defaults' ) );

// DEPRECATED -- backwards compatibility only -- use do_action('show_comment_quiz')
function the_quiz() {
	_deprecated_function( __FUNCTION__, '0.0', 'do_action(\'show_comment_quiz\')' );
	do_action( 'show_comment_quiz' );
}

