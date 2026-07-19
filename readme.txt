=== Plugin Name ===
Stable tag: trunk
Contributors: Tkama
Tested up to: 6.8.3
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Tags: spam, spammer, autospam, spamblock, antispam, anti-spam, protect, comments, ping, trackback, bot, robot, human, captcha, invisible

Light and invisible protection against basic automated comment spam. Pings and trackbacks check for real backlinks.



== Description ==

Kama Spamblock helps block basic automated spam comments while remaining completely invisible to users—no captcha codes required. It uses a lightweight browser check, so it is not intended to stop sophisticated bots that load pages and execute JavaScript. The plugin also checks pings and trackbacks for backlinks to your site.

Even if you are using an external comment system like Disqus, Kama Spamblock can add lightweight protection. Automated requests can be posted directly to the 'wp-comments-post.php' file, where the plugin can block basic bots.



== Screenshots ==

1. Plugin settings on standard WordPress <code>Settings > Discussion</code> page.

2. Spam alert, when spam comment is detected or if the user has JavaScript disabled in their browser. This alert allows sending the comment once again when it was blocked in any nonstandard cases.



== Frequently Asked Questions ==

= When posting a comment on the site, I received a message, 'Antispam blocked your comment!'. Is this a normal function of the plugin? =

No! The plugin is invisible to users. You should navigate to the 'Discussion' settings page in WordPress. At the bottom, you'll find 'Kama Spamblock settings.' Set the correct ID attribute for the comment form submit button there. You can obtain this attribute from the 'source code' of your site's page where the comment form is located. Look for: `type="submit" id="??????"`.



== Changelog ==

= 1.9.0 =
* IMP: Return a 403 response for blocked spam comments.
* FIX: Prevent malformed comment requests from causing PHP errors.
* FIX: Minor bugfix.
* CHG: Min PHP version increased to 7.4.
* IMP: Refactoring (Spam_Blocker class extracted).

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
