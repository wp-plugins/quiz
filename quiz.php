<?php
/*
Plugin Name: Quiz
Plugin URI: http://wordpress.org/extend/plugins/quiz/
Version: 1.0
Description: Commenters must answer the question correctly. You provide the question in the post content as [quiz Question? Answer]. You may also add a default question which you can cancel by placing [noquiz] in the post content. Configure by editing the plugin file.
Author: Andy Skelton
Author URI: http://andyskelton.com/
*/


/*     START TO EDIT HERE     */

/*     Set to true if you use the template tag <?php the_quiz(); ?>     */
define('QUIZ_TEMPLATE_TAG', false);

/*     If filled, all posts lacking the shortcode [quiz Question? Answer] will use these.     */
define('QUIZ_DEFAULT_QUESTION', '');
define('QUIZ_DEFAULT_ANSWER', '');

/*     This works nicely for the default theme. You may have to edit it for your theme.     */
define('QUIZ_FORM', '

<p id="quiz-p" style="clear:both">
<input type="text" name="quiz" id="quiz" value="" size="22" tabindex="4" value="" />
<label for="quiz"><small>%question%</small></label>
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

Implementation notes
	* 1.0 is spaghetti code!
	* Shortcodes are removed from post_content and saved into postmeta so that disabling the plugin won't cause all the answers to appear on the blog.
	* Editors that do not use the standard API will not work properly with this because we need certain filters to be used.

TODO:
	* Clean up code
	* Move config out of constants and into an admin screen
*/


function quiz_init() {
	add_filter( 'the_content', 'quiz_filter', 3 );
	add_filter( 'the_content', 'quiz_reconstitute', 2);

	if ( QUIZ_TEMPLATE_TAG == false )
		add_action('comment_form', 'quiz_form');

	add_filter('preprocess_comment', 'quiz_process', 1);

	add_action('wp_insert_post', 'quiz_strip', 10, 2);
	add_filter( 'edit_post_content', 'quiz_reconstitute', 10, 2);
}

add_action('init', 'quiz_init');

function quiz_filter($the_content, $_id = false) {
	global $id;

	$old_id = $id;

	if ( $_id )
		$id = $_id;

	$the_content = preg_replace_callback('/\[(quiz|noquiz)( [^]]+)?]/', 'quiz_extract', $the_content);

	$id = $old_id;

	return $the_content;
}

function quiz_extract($matches) {
	global $quiz, $id;

	if ( empty($quiz) || !is_array($quiz) )
		$quiz = array();

	if ( $matches[1] == 'noquiz' ) {
		$quiz[$id] = array('q' => '', 'a' => '');
	} elseif ( preg_match('/^([^?]+'.preg_quote(QUIZ_MARK).'.*?)\s+(.+)$/', $matches[2], $matches) ) {
		$quiz[$id] = array('q' => trim($matches[1]), 'a' => trim($matches[2]));
	} else {
		$quiz[$id] = __('Pattern not matched: [quiz Question? Answer]', 'quiz');
	}

	return '';
}

function quiz_form($id) {
	global $quiz, $current_user, $post;

	if ( empty($quiz[$id]) ) {
		if ( '' == QUIZ_DEFAULT_QUESTION && '' == QUIZ_DEFAULT_ANSWER )
			return;
		$quiz[$id] = array('q'=>QUIZ_DEFAULT_QUESTION, 'a'=>QUIZ_DEFAULT_ANSWER);
	}

	if ( isset($quiz[$id]['q']) && empty($quiz[$id]['q']) )
		return;

	if ( false == strpos(QUIZ_FORM, '%question%') )
		$quiz[$id] = __('QUIZ_FORM lacks %question%', 'quiz');

	if ( is_string($quiz[$id]) ) {
		if ( $current_user->ID == $post->post_author )
			echo '<p>' . $quiz[$id] . '</p>';
		return;
	}

	echo str_replace('%question%', $quiz[$id]['q'], QUIZ_FORM);
}

function the_quiz() {
	global $id;

	quiz_form($id);
}

function quiz_process($commentdata) {
	global $quiz;

	extract($commentdata);

	$id = $comment_post_ID;

	$post =& get_post($id);

	$content = quiz_reconstitute($post->post_content, $id);

	quiz_filter($content, $id);

	if ( (is_array($quiz[$id]) && !empty($quiz[$id]['q']) ) || ( !isset($quiz[$id]['q']) && !is_string($quiz[$id]) && '' != QUIZ_DEFAULT_QUESTION && '' != QUIZ_DEFAULT_ANSWER ) ) {
		if ( empty($_POST['quiz']) )
			wp_die(sprintf(__('You must answer the question to post a comment. Please <a href="javascript:window.history.back()">go back</a> and try again.', 'quiz'), $q));
		if ( ! quiz_compare($_POST['quiz'], $id) )
			wp_die(__('You answered the question incorrectly.  Please <a href="javascript:window.history.back()">go back</a> and try again.', 'quiz'), 'QUIZ');
	}

	return $commentdata;
}

function quiz_compare($b, $id) {
	global $quiz;
	
	if ( !empty($quiz[$id]['a']) )
		$a = $quiz[$id]['a'];
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

function quiz_strip($id, $post) {
	global $quiz;

	$content = quiz_filter($post->post_content, $id);

	if ( $content != $post->post_content ) {
		$post->post_content = $content;
		wp_update_post($post);
		delete_post_meta($id, 'quiz');
		add_post_meta($id, 'quiz', $quiz[$id], true);
	}
}

function quiz_reconstitute($content, $id = false) {
	if ( ! $id )
		$id = $GLOBALS['id'];

	if ( $quiz = get_post_meta($id, 'quiz', true) ) {
		if ( empty($quiz['q']) )
			$quiz = '[noquiz]';
		else
			$quiz = "[quiz {$quiz['q']} {$quiz['a']}]";

		if ( substr($content, -1) != "\n" )
			$pre = "\n";

		$content .= "$pre$quiz";
	}

	return $content;
}
?>
