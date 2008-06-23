<?php
/*
Plugin Name: Quiz
Plugin URI: http://wordpress.org/extend/plugins/quiz/
Version: 1.1 beta1
Description: Commenters must answer the question correctly. You provide the question for each post or page.
Author: <a href="http://andyskelton.com/">Andy Skelton</a> and <a href="http://striderweb.com/">Stephen Rider</a>
*/

/* Old Method: Enter Q&A in the post content as [quiz Question? Answer]. You may also add a default question which you can cancel by placing [noquiz] in the post content. */

/*     START TO EDIT HERE     */

/*     Set to true if you use the template tag <?php $commentquiz->the_quiz(); ?>     */
define('QUIZ_TEMPLATE_TAG', false);

/*     If filled, all posts lacking the shortcode [quiz Question? Answer] will use these.     */
define('QUIZ_DEFAULT_QUESTION', '');
define('QUIZ_DEFAULT_ANSWER', '');

/*     This works nicely for the default theme. You may have to edit it for your theme.     */
define('QUIZ_FORM', '

<p id="quiz-p" style="clear:both">
	<label for="quiz"><small>%question%</small></label>
	<input type="text" name="quiz" id="quiz" size="22" tabindex="4" value="" />
</p>
<script type="text/javascript">
//<!--
	var u=document.getElementById("comment");
	if ( u ) {
		u.parentNode.parentNode.insertBefore(document.getElementById("quiz-p"), u.parentNode);
	}
//-->
</script>

');

/*     The question ends at the first whitespace after this mark.     */
define('QUIZ_MARK', '?');

/*     Answers will be treated by these functions before comparison.     */
define('QUIZ_COMPARE', 'strtolower, trim');

/*     STOP EDITING HERE     */


/*     DEVELOPER INFO

1.0 by AS:
	* 1.0 is spaghetti code!
	* Shortcodes are removed from post_content and saved into postmeta so that disabling the plugin won't cause all the answers to appear on the blog.
	* Editors that do not use the standard API will not work properly with this because we need certain filters to be used.
	
1.1 beta1 by SR:
	* "Quiz" meta box on edit screen.  No more shortcodes.
		o Data stored the same way, so should be backwards compatible	 
	* Moved functions into a class
	* abstracted get_quiz() and set_quiz() -- $quiz global was causing trouble
	* if add_meta_box() doesn't exist, should fall back to old shortcode method (untested)
	

TODO: "wrong answer" page -- remember comment content when going back!
TODO: fix tabbing in meta box
TODO: move config out of constants and into an admin screen
TODO: option to move meta box to right side
TODO: "wrong answer" page -- kill javascript href
TODO: more self explanatory function names (?)
TODO: nonces on meta box
TODO: include add_action for 'comment_quiz'; then people add to their themes with do_action('comment_quiz') & don't have to mess with function_exists
TODO: get rid of this "skelton" character and claim glory for self
*/

class commentquiz {	

	function init() {
		if ( QUIZ_TEMPLATE_TAG == false )
			add_action('comment_form', array(&$this, 'form') );

		add_filter('preprocess_comment', array(&$this, 'process'), 1);

		add_action('admin_menu', array(&$this, 'call_meta_box') );
		add_action('save_post', array(&$this, 'save_meta_box') );
	}

	function call_meta_box() {
		if( function_exists( 'add_meta_box' )) {
			add_meta_box( 'edit_comment_quiz', 'Comment Quiz', array(&$this, 'meta_box'), 'post', 'normal' );
			add_meta_box( 'edit_comment_quiz', 'Comment Quiz', array(&$this, 'meta_box'), 'page', 'normal' );
		} else { // use [quiz q? a]
			add_filter( 'the_content', array(&$this, 'filter'), 3 );
			add_filter( 'the_content', array(&$this, 'reconstitute'), 2);
			add_action('wp_insert_post', array(&$this, 'strip'), 10, 2);
			add_filter( 'edit_post_content', array(&$this, 'reconstitute'), 10, 2);
		}
	}

	function meta_box() {
		global $post_ID;
		$quiz = $this->get_quiz($post_ID);
		$q = $quiz['q'];
		$a = $quiz['a'];
		$nonce = wp_create_nonce( plugin_basename(__FILE__) );

//		<input type="hidden" name="commentquiz_nonce" id="commentquiz_nonce" value="{$nonce}" />
		echo <<< BOX
		<p>Commenters must answer the question you enter here before their comment will be accepted.</p>
		<label for="quizQuestion"><input type="text" name="quizQuestion" id="quizQuestion" value="{$q}" /> Question</label><br />
		<label for="quizAnswer"><input type="text" name="quizAnswer" id="quizAnswer" value="{$a}" /> Answer</label>
BOX;
	}

	function save_meta_box( $post_id ) {

//		if ( !wp_verify_nonce( $_POST[commentquiz_nonce], plugin_basename(__FILE__) ) ) return $post_id;
		
		if ( 'page' == $_POST['post_type'] ) {
			if ( !current_user_can( 'edit_page', $post_id ))
				return $post_id;
		} else if (!current_user_can( 'edit_post', $post_id )) {
			return $post_id;
		}

		$q = $_POST['quizQuestion'];
		$a = $_POST['quizAnswer'];
		$new_quiz = array('q'=>$q,'a'=>$a);

		$this->set_quiz($post_id, $new_quiz);

		return $new_quiz;
	}

	function get_quiz($id = null) {
		static $quiz;
		if( empty( $quiz ) )
			if( !$id ) {
				global $post_ID;
				$id = $post_ID;
			}
			$quiz = get_post_meta($id, 'quiz', true);
		return $quiz;
	}
	
	function set_quiz($post_id, $quiz) {
		delete_post_meta($post_id, 'quiz');
		add_post_meta($post_id, 'quiz', $quiz, true);		
	}

	function form($id) {
//		global $thequiz, $current_user, $post;
		global $current_user, $post;
//	 get_post_meta($post_id, $key, $single);
		$quiz = $this->get_quiz($id);

//error_log(print_r($quiz,true));
//error_log($quiz['q']);
		if ( empty($quiz) ) {
			if ( '' == QUIZ_DEFAULT_QUESTION && '' == QUIZ_DEFAULT_ANSWER )
				return;
			$quiz = array('q'=>QUIZ_DEFAULT_QUESTION, 'a'=>QUIZ_DEFAULT_ANSWER);
		}

		if ( isset($quiz['q']) && empty($quiz['q']) )
			return;

		if ( false == strpos(QUIZ_FORM, '%question%') )
			$quiz = __('QUIZ_FORM lacks %question%', 'quiz');

		if ( is_string($quiz) ) {
			if ( $current_user->ID == $post->post_author )
				echo '<p>' . $quiz . '</p>';
			return;
		}

		echo str_replace('%question%', $quiz['q'], QUIZ_FORM);
	}

	function the_quiz() {
		global $id;

		$this->form($id);
	}

	function process($commentdata) {
//		global $thequiz;

		extract($commentdata);
		$id = $comment_post_ID;
	$quiz = $this->get_quiz($id);
		$post =& get_post($id);
		$content = $this->reconstitute($post->post_content, $id);
		$this->filter($content, $id);

		if ( (is_array($quiz) && !empty($quiz['q']) ) || ( !isset($quiz['q']) && !is_string($quiz) && '' != QUIZ_DEFAULT_QUESTION && '' != QUIZ_DEFAULT_ANSWER ) ) {
			if ( empty($_POST['quiz']) )
				wp_die(sprintf(__('You must answer the question to post a comment. Please <a href="javascript:window.history.back()">go back</a> and try again.', 'quiz'), $q));
			if ( ! $this->compare($_POST['quiz'], $id) )
// TODO: JavaScript link BAD!  rrrrRRRRRrrrrrrr!
				wp_die(__('You answered the question incorrectly.  Please <a href="javascript:window.history.back()">go back</a> and try again.', 'quiz'), 'QUIZ');
		}

		return $commentdata;
	}

	function compare($b, $id) {
//		global $thequiz;
		$quiz = $this->get_quiz($id);
	
		if ( !empty($quiz['a']) )
			$a = $quiz['a'];
		else
			$a = QUIZ_DEFAULT_ANSWER;

		if ( $a === $b )
			return true;

		if ( QUIZ_COMPARE ) {
			$funcs = array_map('trim', explode(',', QUIZ_COMPARE) );
			foreach ( $funcs as $func ) {
				if ( is_callable($func) ) {
					$a = $func($a);
					$b = $func($b);
				}
			}

			if ( $a === $b )
				return true;
		}
	
		return false;
	}

// DEPRECATED
// removes the [quiz] shortcode from content
	function filter($the_content, $_id = false) {
		global $id;
		
		$old_id = $id;
		if ( $_id )
			$id = $_id;

		$the_content = preg_replace_callback('/\[(quiz|noquiz)( [^]]+)?]/', array(&$this, 'extract'), $the_content);

		$id = $old_id;

		return $the_content;
	}

// DEPRECATED
// parses regex matches and assigns to $thequiz array
	function extract($matches) {
//		global $thequiz, $id;
		global $id;
		$quiz = $this->get_quiz($id);

		if ( empty($quiz) || !is_array($quiz) )
			$quiz = array();

		if ( $matches[1] == 'noquiz' ) {
			$quiz = array('q' => '', 'a' => '');
		} elseif ( preg_match('/^([^?]+'.preg_quote(QUIZ_MARK).'.*?)\s+(.+)$/', $matches[2], $matches) ) {
			$quiz = array('q' => trim($matches[1]), 'a' => trim($matches[2]));
		} else {
			$quiz = __('Pattern not matched: [quiz Question? Answer]', 'quiz');
		}

		return '';
	}

// DEPRECATED
// When saving post, removes quiz from content and places it in meta
	function strip($id, $post) {
		global $thequiz;

		$content = $this->filter($post->post_content, $id);

		delete_post_meta($id, 'quiz');

		if ( $content != $post->post_content ) {
			$post->post_content = $content;
			wp_update_post($post);
			add_post_meta($id, 'quiz', $thequiz[$id], true);
		}
	}

// DEPRECATED
// when editing existing post, pulls quiz from meta and puts it back into post content
	function reconstitute($content, $id = false) {
		if ( ! $id )
			$id = $GLOBALS['id'];

		if ( $thequiz = get_post_meta($id, 'quiz', true) ) {
			if ( empty($thequiz['q']) )
				$thequiz = '[noquiz]';
			else
				$thequiz = "[quiz {$thequiz['q']} {$thequiz['a']}]";

			if ( substr($content, -1) != "\n" )
				$pre = "\n";

			$content .= "$pre$thequiz";
		}

		return $content;
	}

} // class commentquiz


$commentquiz = new commentquiz;
add_action( 'init', array($commentquiz, 'init') );


// DEPRECATED -- backwards compatibility only
function the_quiz() {
	$commentquiz->the_quiz();
}

?>