=== Quiz ===
Contributors: andy, strider72
Tags: comment, spam, anti-spam, discussion
Tested up to: 2.7
Requires at least: 2.5
Stable tag: 1.1.1

Reduces spam and troll comments by requiring commenters to answer a question.

== Description ==

This plugin adds a question/answer to your comment form.  Commenters must answer the question correctly before their comment will be accepted.  This cuts down on spambots, and can also be helpful with troll-control.

Authors can write a new Quiz question and answer for each post, fall back on a default question, or have no question at all.

Answers are checked by a caseless comparison. So spelling must be correct but uppercase letters do not matter. Short answers of one or two words are best and punctuation should be avoided.

Questions should be crafted to check that the commenter read the post. So a post about your dog might have the question "What is my dog's name?" and a gallery post might ask "What brand of beer appears most often?"

You can enter multiple correct answers, separated by commas.  For example, if you enter:

6, six, half dozen

Either "6" or "six" or "half dozen" will be accepted as correct.

== Installation ==

Upload the 'quiz' folder to the '/wp-content/plugins/' directory and activate it in Admin.  If upgrading from 1.0, be sure to deactivate/upgrade/reactivate, or defaults won't be set.

== Instructions ==

When you write a post, enter the question and answer in the "Comment Quiz" meta box.  If you leave it blank, the default question will be used.  If you enter "noquiz" as the question, that post will not display a quiz.

To manually place the quiz form within you comments section, edit the comments.php file and insert the following:  do_action('comment_quiz')

There is a Settings screen if you want to customize things, including setting the default question or customizing the way the quiz displays on the page.

Tips on how to improve questions can be found at http://striderweb.com/nerdaphernalia/features/wp-comment-quiz/

(Please Note: the old "quicktag" method no longer works.  If you want to use that method, you should download version 1.0.  In 1.1 and above you enter the question in the meta box.)