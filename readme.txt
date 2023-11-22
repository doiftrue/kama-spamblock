=== Plugin Name ===
Stable tag: trunk
Contributors: Tkama
Requires at least: 2.7
Tested up to: 5.8.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Tags: spam, spammer, autospam, spamblock, antispam, anti-spam, protect, comments, ping, trackback, bot, robot, human, captcha, invisible

Light and invisible method to block auto-spam when spam comment is posted. Pings and trackbacks cheks for real backlinks.



== Description ==

Block auto-spam when spam comment is posted. Absolutely invisible for users -- There is no any captcha code. Pings and trackbacks cheks for real backlink.

Install Kama Spamblock also useful even if you have external comment system like "Disqus". Because autospam could posted directly to "wp-comments-post.php" file. The plugin will protect such comments.




== Screenshots ==

1. Plugin settings on standart WordPress <code>Settings > Discussion</code> page.

2. Spam alert, when spam comment detected or if user have javascript disabled in his browser. This alert allows send comment once again, when it was blocked in any nonstandard cases.



== Frequently Asked Questions ==

= On comment post i see message "Antispam block your comment!" is it normal plugin work? =

NO! Plugin invisible for users. You need to go to WordPress "Discussion" setting page. At the bottom you will see "Kama Spamblock settings". Set there correct ID attribute of comment form submit button. This attribute you can get from "souse code" of you site page where comment form is. Look for: type="submit" id="-----"




== Changelog ==

= 1.8.1 =
* Code refactoring.
* `kama_spamblock__process_comment_types` hook added.

= 1.8 =
* FIX: WordPress 5.5 support.

= 1.7.5 =
* FIX: bug with uniq code comparation
* minor code fixes


= 1.7.4 =
* CHG: change sanitize-options-on-save function - sanitize_key() to sanitize_html_class() - it's not so hard but hard enough...
* CHG: 'sanitize_setting' function call. Seems it hasn't have back-compat for wordpress versions less then 4.7

= 1.7.3 =
* FIX: options fix of 1.7.2

= 1.7.2 =
* CHG: move translation to translation.wordpress.org
* ADD: new 'unique code' option.
* IMP: some code improvements.

= 1.7.0 =
* BUG: Last UP bug fix...

= 1.6.0 =
* CHG: check logic is little change in order to correctly work with page cache plugins

= 1.5.2 =
* ADD: delete is_singular check for themes where this check work wrong. Now plugin JS showen in all pages

= 1.5.1 =
* ADD: js include from numbers of hooks. If there is no "wp_footer" hook in theme

= 1.5.0 =
* ADD: Russian localization
