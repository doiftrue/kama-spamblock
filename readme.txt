=== Plugin Name ===
Stable tag: trunk
Contributors: Tkama
Tested up to: 6.6.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Tags: spam, spammer, autospam, spamblock, antispam, anti-spam, protect, comments, ping, trackback, bot, robot, human, captcha, invisible

Light and invisible method to block auto-spam when a spam comment is posted. Pings and trackbacks check for real backlinks.



== Description ==

Effectively prevent automatic spam when a spam comment is posted with Kama Spamblock, a plugin that operates discreetly and remains completely invisible to users—no captcha codes required. The plugin not only blocks spam comments but also conducts thorough checks on pings and trackbacks to ensure the authenticity of backlinks.

Even if you are using an external comment system like Disqus, installing Kama Spamblock remains valuable. This is because autospam can be posted directly to the 'wp-comments-post.php' file, and the plugin serves as a robust defense against such comments.



== Screenshots ==

1. Plugin settings on standard WordPress <code>Settings > Discussion</code> page.

2. Spam alert, when spam comment is detected or if the user has JavaScript disabled in their browser. This alert allows sending the comment once again when it was blocked in any nonstandard cases.



== Frequently Asked Questions ==

= When posting a comment on the site, I received a message, 'Antispam blocked your comment!'. Is this a normal function of the plugin? =

No! The plugin is invisible to users. You should navigate to the 'Discussion' settings page in WordPress. At the bottom, you'll find 'Kama Spamblock settings.' Set the correct ID attribute for the comment form submit button there. You can obtain this attribute from the 'source code' of your site's page where the comment form is located. Look for: `type="submit" id="??????"`.



== Changelog ==

= 1.8.3 =
* FIX: XSS vulnerability fixed. Thanks to [Wordfence](https://www.wordfence.com/) for the report.
* IMP: Other minor improvements.

= 1.8.2 =
* Minor refactoring.

= 1.8.1 =
* Code refactoring.
* `kama_spamblock__process_comment_types` hook added.

= 1.8 =
* FIX: WordPress 5.5 support.

= 1.7.5 =
* FIX: bug with unique code comparison.
* Minor code fixes.

= 1.7.4 =
* CHG: changed sanitize-options-on-save function - sanitize_key() to sanitize_html_class() - it's not so hard but hard enough...
* CHG: 'sanitize_setting' function call. Seems it doesn't have back-compat for WordPress versions less than 4.7.

= 1.7.3 =
* FIX: options fix of 1.7.2.

= 1.7.2 =
* CHG: moved translation to translation.wordpress.org.
* ADD: new 'unique code' option.
* IMP: some code improvements.

= 1.7.0 =
* BUG: Last UP bug fix...

= 1.6.0 =
* CHG: check logic is slightly changed in order to work correctly with page cache plugins.

= 1.5.2 =
* ADD: deleted is_singular check for themes where this check works incorrectly. Now plugin JS is shown on all pages.

= 1.5.1 =
* ADD: JS included from a number of hooks if there is no "wp_footer" hook in the theme.

= 1.5.0 =
* ADD: Russian localization.
