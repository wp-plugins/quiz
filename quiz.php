<?php
/*
Plugin Name: Quiz
Plugin URI: http://wordpress.org/extend/plugins/quiz/
Version: 1.2
Date: 2010-02-03
Description: Commenters must answer the question correctly. You provide the question for each post or page, unless you have post publishing capabilities.
Author: <a href="http://andyskelton.com/">Andy Skelton</a>, <a href="http://striderweb.com/">Stephen Rider</a> and <a href="http://coveredwebservices.com/">Mark Jaquith</a>
*/

// To manually place the quiz form in your comments form, use do_action('show_comment_quiz')

/*
	TODO: "Wrong answer" page -- Show comment content and caution to copy content before going back
	TODO: Improve auto-placement JavaScript OR make user-changeable
*/

class commentquiz {

	var $option_version = '1.2';
	var $option_name = 'plugin_commentquiz_settings';

	function commentquiz() {
		load_plugin_textdomain( 'commentquiz', PLUGINDIR . '/' . dirname( plugin_basename( __FILE__ ) ) );
		$options = get_option( $this->option_name );

		if ( !isset( $options['last_opts_ver'] ) || $options['last_opts_ver'] != $this->option_version )
			$this->set_defaults();
		if( ! is_admin() ) {
			// This is so end users can use do_action('show_comment_quiz') in themes
			add_action( 'show_comment_quiz', array( &$this, 'the_quiz' ) );
			// ...otherwise will add form automatically
			add_action( 'comment_form', array( &$this, 'the_quiz' ) );

			add_filter( 'preprocess_comment', array( &$this, 'process' ), 1 );
		}
		add_action( 'admin_menu', array( &$this, 'add_settings_page' ) );
		add_action( 'admin_menu', array( &$this, 'call_meta_box' ) );
		add_action( 'save_post', array( &$this, 'save_meta_box' ) );
	}

	function get_quiz( $id = null, $blankdefault = false ) {
		static $quiz;
		if( $quiz ) return $quiz;

		if( ! $id ) {
			global $post_ID;
			$id = $post_ID;
		}
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

	function get_plugin_data( $param = null ) {
		// You can optionally pass a specific value to fetch, e.g. 'Version' -- but it's inefficient to do that multiple times
		if( !function_exists( 'get_plugin_data' ) ) require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		static $plugin_data;
		if( ! $plugin_data ) {
			$plugin_data = get_plugin_data( __FILE__ );
			if ( ! isset( $plugin_data['Title'] ) ) {
				if ( '' != $plugin_data['PluginURI'] && '' != $plugin_data['Name'] ) {
					$plugin_data['Title'] = '<a href="' . $plugin_data['PluginURI'] . '" title="'. __( 'Visit plugin homepage' ) . '">' . $plugin_data['Name'] . '</a>';
				} else {
					$plugin_data['Title'] = $name;
				}
			}
		}

		$output = $plugin_data;
		if( $param && is_array( $plugin_data )  ) {
			foreach( $plugin_data as $key => $value ) {
				if( $param == $key ) {
					$output = $value;
					break;
				}
			}
		}
		return $output;
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
			'def_q' => __( 'Which is warmer, ice or steam?', 'commentquiz' ),
			'def_a' => __( 'steam', 'commentquiz' ),
			'compare_funcs' => 'strip_tags, strtolower, trim',
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
		add_action( 'wp_footer', array( &$this, 'form_position' ) );
		return true;
	}

// try to put form in a better location than _after_ the submit button!
	function form_position() {
		// only if the the_quiz() was called exactly once
		if( $this->form_shown != 1 ) return false;

		$form_position = '
<script type="text/javascript">
//<!--
	var u=document.getElementById("comment");
	if ( u ) {
		u.parentNode.parentNode.insertBefore(document.getElementById("commentquiz"), u.parentNode);
	}
//-->
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
					wp_die( __( 'You must answer the question to post a comment. Please <a href="javascript:window.history.back()">go back</a> and try again.', 'commentquiz' ) );

				$answer = $quiz['a'];
				$answer = array_map( 'trim', explode( ',', $answer ) );
				$response = stripslashes( $_POST['quiz'] );

				foreach( $answer as $a ) {
					if ( $this->compare( $a, $response ) )
						return $commentdata;
				}
				wp_die( __( 'You answered the question incorrectly.  Please <a href="javascript:window.history.back()">go back</a> and try again.', 'commentquiz' ) );
			}
		}
		return $commentdata;
	}

	function compare( $a, $b ) {

		if ( $a === $b ) return true;

		$options = get_option( $this->option_name );
		if ( $options['compare_funcs'] ) {
			$funcs = array_map( 'trim', explode( ',', $options['compare_funcs'] ) );
			foreach ( $funcs as $func ) {
				if ( is_callable( $func ) ) {
					$a = $func( $a );
					$b = $func( $b );
				}
			}
			if ( $a === $b ) return true;
		}

		return false;
	}

// ************************
//    Meta Box Functions
//    for post edit page
// ************************

	function call_meta_box() {
		if( function_exists( 'add_meta_box' ) ) {
			add_meta_box( 'edit_comment_quiz', 'Comment Quiz', array( &$this, 'meta_box' ), 'post', 'normal' );
			add_meta_box( 'edit_comment_quiz', 'Comment Quiz', array( &$this, 'meta_box' ), 'page', 'normal' );
		}
	}

	function meta_box() {
		global $post_ID;
		$quiz = $this->get_quiz( $post_ID, true );
		if( $quiz ) {
			$q = esc_attr( $quiz['q'] );
		} else {
			$q = 'noquiz';
		}
		$a = esc_attr( $quiz['a'] );
		$nonce = wp_create_nonce( plugin_basename( __FILE__ ) );
		$howto1 = __( 'Enter "noquiz" if you don\'t want a question for this post, or leave it blank to use the default question.', 'commentquiz' );
		$howto2 = __( 'You may enter multiple correct answers, separated by commas; e.g. <code>color, colour</code>', 'commentquiz' );
		$qlabel = __( 'Question', 'commentquiz' );
		$alabel = __( 'Answer', 'commentquiz' );
		echo <<< BOX
		<input type="hidden" name="comment_quiz_metabox" id="comment_quiz_metabox" value="$nonce" />
		<p><input type="text" name="quizQuestion" id="quizQuestion" size="25" value="$q" tabindex="3" /><label for="quizQuestion"> $qlabel</label><br />
		<input type="text" name="quizAnswer" id="quizAnswer" size="25" value="$a" tabindex="3" /><label for="quizAnswer"> $alabel</label>
		<span class="howto">$howto1<br />$howto2</span></p>
BOX;
	}

	function save_meta_box( $post_id ) {

		if ( ! wp_verify_nonce( $_POST['comment_quiz_metabox'], plugin_basename(__FILE__) ) ) {
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
			$page = add_options_page( __( 'Comment Quiz', 'commentquiz' ), __( 'Quiz', 'commentquiz' ), 'manage_options', 'quiz', array( &$this, 'settings_page' ) );
			add_filter( 'plugin_action_links', array( &$this, 'filter_plugin_actions' ), 10, 2 );
			add_action( 'load-' . $page, array( &$this, 'save_options' ) );
			return $page;
		}
		return false;
	}

	// Add homepage link to settings page footer
	function admin_footer() {
		$pluginfo = $this->get_plugin_data();
		printf( '%1$s plugin | Version %2$s | by %3$s<br />', $pluginfo['Title'], $pluginfo['Version'], $pluginfo['Author'] );
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

// these three functions are used by the settings page to display set options in the form controls when the page is opened

	function checkflag( $options, $optname ) {
		// for checkboxes
		return $options[$optname] ? ' checked="checked"' : '';
	}

	function checktext( $options, $optname, $optdefault = '' ) {
		// for text boxes and textareas
		return esc_attr( ( $options[$optname] ) ? $options[$optname] : $optdefault );
	}

	function checkcombo( $options, $optname, $thisopt, $is_default = false ) {
		// for dropdowns
		return (
			( $is_default && ! $options[$optname] ) || $options[$optname] == $thisopt ) ? ' selected="selected"' : '';
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
		add_action( 'in_admin_footer', array( &$this, 'admin_footer' ), 9 );

		// get options for use in formsetting functions
		$opts = get_option( $this->option_name );

	?>
<div class="wrap">
	<?php screen_icon(); ?>
	<h2><?php _e( 'Comment Quiz', 'commentquiz' ); ?></h2>
	<form method="post" action="<?php echo $this->options_url(); ?>">
		<?php
		if ( function_exists( 'wp_nonce_field' ) )
			wp_nonce_field( 'commentquiz-update-options' );
		?>
		<table class="form-table">
			<tbody>
				<tr valign="top">
					<th scope="row"><?php _e( 'Default Quiz', 'commentquiz' ) ?></th>
					<td><input type="text" name="commentquiz_options[def_q]" id="def_q" size="35" value="<?php echo $this->checktext( $opts, 'def_q', '' ); ?>" /><label for="def_q"> <?php _e( 'Question', 'commentquiz' ); ?></label><br />
						<input type="text" name="commentquiz_options[def_a]" id="def_a" size="15" value="<?php echo $this->checktext( $opts, 'def_a', '' ); ?>"/><label for="def_a"> <?php _e( 'Answer', 'commentquiz' ); ?></label><br />
						<span><?php _e( 'You may enter multiple correct answers, separated by commas; e.g. <code>color, colour</code>', 'commentquiz' ) ?></span>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php _e( 'Quiz Form', 'commentquiz' ) ?></th>
					<td><textarea name="commentquiz_options[quiz_form]" id="quiz_form" cols="60" rows="6"><?php echo $this->get_quiz_form( true, false ); ?></textarea><br />
					<span><?php _e( 'The form must contain a %question% placeholder.<br />To reset to default, blank this field and save settings.', 'commentquiz' ) ?></span>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php _e( 'Cleanup', 'commentquiz' ) ?></th>
					<td><input type="text" name="commentquiz_options[compare_funcs]" id="compare_funcs" size="35" value="<?php echo $this->checktext( $opts, 'compare_funcs', '' ) ; ?>" /><br />
					<span><?php _e( "Commenter's response will be passed through these PHP functions.<br />Separate function names with commas.", 'commentquiz' ) ?></span>
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

$commentquiz = new commentquiz;

// register_activation_hook( __FILE__, array( $commentquiz, 'set_defaults' ) );

// DEPRECATED -- backwards compatibility only -- use do_action('show_comment_quiz')
function the_quiz() {
	_deprecated_function( __FUNCTION__, '0.0', 'do_action(\'show_comment_quiz\')' );
	do_action( 'show_comment_quiz' );
}

