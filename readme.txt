=== Lock Pages ===
Contributors: gyrus
Donate link: http://www.babyloniantimes.co.uk/index.php?page=donate
Tags: admin, administration, cms, page, pages, custom post types, permalink, seo
Requires at least: 3.0
Tested up to: 4.9
Stable tag: 0.3.1

Lock Pages prevents specified pages (or all pages), posts, or custom post types from having their slug, parent, status or password edited, or from being deleted, by non-administrators.

== Description ==

NOTE: This plugin is not tested with Gutenberg, and we have no near-term plans to do so. If using WP > 5.0, use the Classic Editor if there are problems.

NOTE: This plugin was originally designed to only lock pages, and only later added custom post type functionality. Hence the name, and sometimes the terminology will say "pages" when it means "any post type".

Sometimes some pages or other posts are too important to allow them to be casually moved about or deleted by site editors. An editor may think nothing of renaming a page's slug, or deleting a page to replace it with something similar, perhaps unaware of effects on SEO. Also, certain pages might be essential to keep in place because of a site's structure, or because of aspects of a custom theme.

This plugin lets administrators "lock" any or all pages, and any post of any post type. "Locking" here basically means preventing non-admins from:

* Editing the item's slug
* Changing the item's parent
* Changing the item's template
* Deleting the item
* Changing the item's status
* Changing the item's password protection

Locking is implemented by preventing the actual database update being performed as well as, where possible, having the interface element for that field removed. Where possible interface elements are removed via WP filters on the server; otherwise, jQuery is used on the client.

NOTE: Currently, I've been unable to get this working with the Quick Edit functionality. As a stop-gap measure, which is only in place because it seems to be better than nothing, the Quick Edit link is removed for users who can't edit locked pages. I know, it's not great. But until I work out how to selectively block Quick Editing, I'm assuming a locked page should be locked. Users can always edit the other fields via the normal edit page.

Go to GitHub for [development code](https://github.com/gyrus/Lock-Pages) and [issue tracking](https://github.com/gyrus/Lock-Pages/issues).

== Installation ==

It's easiest to use the built-in plugin installer inside WordPress. Go to *Plugins > Add New*, and search for "lock pages".

To install manually:

1. Upload the `lock-pages` folder into your site's `/wp-content/plugins/` folder.
1. Go to the list of installed *Plugins* in WordPress and activate it.
1. Go to *Settings > Lock Pages* and change configuration if necessary.

That's it!

== Frequently Asked Questions ==

= Who can move, delete, etc. a page once it's locked? =

The definition of who is able to move or delete locked pages is made via a plugin setting that specifies a [capability](http://codex.wordpress.org/Roles_and_Capabilities). This also defines who can edit the plugin's settings, and who can lock individual pages. Be careful not to bar yourself!

It defaults to `manage_options`, which by default is only granted to the Administrator role, but it can be changed. You will get a warning if you enter a capability that doesn't exist in your system.

Lock Pages will work with Justin Tadlock's [Members](http://wordpress.org/extend/plugins/members/) plugin, if it's installed, to respect any custom capabilities you may have entered.

= Does the locking work with Quick Edit as well? =

No. I'm having problems getting this working properly. Until then, sadly, I've had to remove the Quick Edit link from locked pages for users who are barred from changing locked page attributes. Sorry! They can still edit other things with the normal edit screen.

= What's the donate link about? =

I feel I gain more than enough already from the WordPress community in monetary terms, through the work it lets me do and the services it lets me offer. For me, part of writing a plugin is precisely to give back to the community - so it seems odd to ask for money for a small plugin (even if it's not obligatory).

Still, I know that sometimes a plugin really makes your day and you want to give back in turn. If this plugin tickles you and you feel like giving, I've given the link to donate to Corporate Watch, who perform what I think is a hugely important role in holding large corporations to account.

Of course, contributing back to the WordPress community is also a great way to express gratitude for a plugin!

== Screenshots ==

1. The Lock Pages settings screen
2. The meta box on the post edit screen that lets you lock a specific item
3. If a user can edit an item, but can't override its lock, they'll get this notice letting them know
4. The posts list will show which ones are currently locked

== Changelog ==

= 0.3.1 (2018-12-18) =
* Added 'protect from all' option, to allow lock protection to apply even if user has capability to lock posts
* Added proper defaulting for updates, to include new option defaults (thanks Kevin VandeKrol!)
* Fixed issue with 4th parameter for `user_has_caps` filter, and issue with arrays passed for `$args[0]`
* Fixed minor PHP errors

= 0.3 (2015-08-19) =
* Added locking of posts and custom post types
* Changed PNG lock icon to dashicon
* Changed admin headings to H1

= 0.2.4 =
* Changed the way the parent drop-down is removed to prevent inteference with other uses of `wp_dropdown_pages`

= 0.2.3 =
* Removed PHP 4 constructor

= 0.2.2 =
* Added locking of page template

= 0.2.1 =
* Fixed clash with Simple Page Ordering plugin

= 0.2 =
* Updated check for page edit screen for WP 3
* Improved removal of Quick Edit functionality
* Removed edit permalink elements, slug meta box, parent drop-down, status and password fields for locked pages
* Changed initialization to use admin_init
* Improved refusal of delete capability for locked pages
* Added locking for page status and password protection
* Added JavaScript and changed inclusion of CSS to use wp_enqueue_style
* Created POT file for translations
* Added Spanish translations (thanks to Javier Gómez Pose!)
* Many coding style improvements
* Removed unused properties `$thispluginurl` and `$thispluginpath`, introduced use of `plugins_url`
* Fixed handling of `$args` in `lock_deletion` to account for different `user_has_caps` contexts
* Improved form security with esc_attr

= 0.1.6 =
* Refined check in lockParent so now uploads stay attached to a page even if you edit the attachment details after uploading.

= 0.1.5 =
* Added (hopefully temporary) blocking of Quick Edit functionality on pages that are locked for user who can't edit locked page elements.

= 0.1.4 =
* Added a check in lockParent to make sure it's not handling a file upload. Without this, the lock was preventing files being attached to a locked page.

= 0.1.3 =
* Added an important check in saveMeta function to make sure that a page (not a post, revision or autoupdate) is being saved. See http://alexking.org/blog/2008/09/06/wordpress-26x-duplicate-custom-field-issue

= 0.1.2 =
* Added prevention of deletion of locked pages.
* Added "Lock" column and icon on Edit Pages list.
* Streamlined permission checking.

= 0.1.1 =
* Fixed New Page meta box so the page lock checkbox is unchecked by default.
* Changed 'capability' parameter for `add_options_page()` to match the capability setting in the plugin, i.e. only users with the capability to edit "locked" pages can change the plugin settings.

= 0.1 =
* First release.

== Known issues ==

* Quick Edit presents problems. I've worked out how to create hidden fields in the Quick Edit box, and to put the values in the hidden div, but I can't work out how to dynamically populate the fields with the values, so the old values can be used if necessary on saving. For now Quick Edit is blocked for users who can't edit locked pages.
* Although I've fixed the lock_parent function so it allows uploaded files to be attached to a locked page, it still prevents media already in the library from being attached when inserted.

== Ideas ==

* Include the locking checkbox for admins in the Quick Edit form.
* On the settings screen, use a drop-down for selecting which capability is needed for editing locked page elements.
* Implement a system to deal with descendants, e.g. an option to lock all descendants of a locked page or not.