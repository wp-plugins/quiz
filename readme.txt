=== Quiz ===
Contributors: andy, strider72
Tags: comment, spam, discussion
Tested up to: 2.5.1

Requiring commenters to answer a question reduces spam and troll comments.

== Description ==

Authors can write a new Quiz question and answer for each post, fall back on a default question, or have no question at all. Commenters must correctly answer the question before their comment is submitted.

Answers are checked by a caseless comparison. So spelling must be correct but uppercase letters do not matter. Short answers of one or two words are best and punctuation should be avoided.

Questions should be crafted to check that the commenter read the post. So a post about your dog might have the question "What is my dog's name?" and a gallery post might ask "What brand of beer appears most often?"

== Instructions ==

Upload `quiz.php` to the `/wp-content/plugins/` directory and edit that file to configure it.

When you write a post, include your question and answer in this format:

`[quiz Question? answer]`

Quiz will take everything up to the first "?" as the question and everything from the following word to the "]" as the answer.

Your answer is not actually stored in the post table so it will never be shown to visitors even if you disable the plugin.

Example:

`[quiz What is my first name? Andy]`

Correct answers include "Andy", "andy", "ANDY".

