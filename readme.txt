=== Kama SpamBlock ===
Stable tag: trunk
Contributors: Tkama
Tested up to: 7.0.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Tags: spam, antispam, comments, bot, captcha

Light and invisible protection against basic automated comment spam. Pings and trackbacks check for real backlinks.



== Description ==

Kama Spamblock blocks simple automated comment spam. It is invisible to normal visitors and does not use captchas.

The plugin protects the standard WordPress comment endpoint, <code>wp-comments-post.php</code>. It adds a small check to the comment form. Direct requests that skip the form are blocked.

This is a basic check, not proof that a person wrote the comment. Bots that load the page and run JavaScript can pass it. Kama Spamblock does not replace a full anti-spam service. It also checks pings and trackbacks for a link back to your site.

Even if you are using an external comment system like Disqus, Kama Spamblock can add lightweight protection. Automated requests can be posted directly to the 'wp-comments-post.php' file, where the plugin can block basic bots.

= Simple and effective protection =

Kama Spamblock combines several small checks that are inexpensive for the site and invisible during normal commenting:

* The protective field name and its unique code rotate together every four hours. Ten recent pairs remain valid for pages served from full-page cache.
* Each marker is tied to the specific WordPress post, so a marker copied from another comment form is rejected.
* The form must remain open for at least three seconds before it can be accepted.
* Protective fields are added only after interaction with the comment submit button.
* If a valid comment is blocked, a JavaScript-only retry form preserves the entered data.
* The retry challenge changes its HTML structure to make basic scraping less reliable.
* Pingbacks and trackbacks must return a successful HTTP response, non-binary content, and a real backlink.

These checks deliberately remain lightweight. A capable bot that loads the page, runs JavaScript, and reproduces normal browser behaviour can still pass them.

= Using Kama Spamblock with other anti-spam plugins =

Kama Spamblock can work with a full anti-spam plugin or service. It blocks simple direct spam requests first. The other tool can then check the comments that remain. For example, it can analyse comment text, reputation, or behaviour. The plugins complement each other.

For this combination to work as intended:

* The other plugin should use the standard WordPress comment flow, or check comments after Kama Spamblock allows them.
* The site must show the standard comment form and allow the plugin's JavaScript to run. Set the correct ID for the form's submit button in the plugin settings.
* Test the setup if another plugin replaces the comment form, uses AJAX, or sends comments to its own endpoint. Kama Spamblock may need extra integration, or it may not check those comments.



== Screenshots ==

1. Plugin settings on standard WordPress <code>Settings > Discussion</code> page.

2. Spam alert, when spam comment is detected or if the user has JavaScript disabled in their browser. This alert allows sending the comment once again when it was blocked in any nonstandard cases.



== Frequently Asked Questions ==

= When posting a comment on the site, I received a message, 'Antispam blocked your comment!'. Is this a normal function of the plugin? =

No! The plugin is invisible to users. You should navigate to the 'Discussion' settings page in WordPress. At the bottom, you'll find 'Kama Spamblock settings.' Set the correct ID attribute for the comment form submit button there. You can obtain this attribute from the 'source code' of your site's page where the comment form is located. Look for: `type="submit" id="??????"`.



== Changelog ==

= 2.0.0 =
* NEW: (better spamblock) Rotate protective field names and unique codes together every four hours while keeping ten recent pairs valid for cached pages.
* NEW: (better spamblock) require at least three seconds before a comment can be submitted.
* NEW: (better spamblock) Bind comment markers to the specific WordPress post.
* NEW: Make the retry challenge harder for basic bots to parse by varying its HTML structure.
* IMP: Reject pingbacks and trackbacks when the source page has a non-2xx response or binary content.
* CHG: Remove the static unique code setting and UTC date from comment markers.

= 1.9.0 =
* IMP: Return a 403 response for blocked spam comments.
* FIX: Prevent malformed comment requests from causing PHP errors.
* FIX: Use a generated unique code immediately after plugin activation.
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
