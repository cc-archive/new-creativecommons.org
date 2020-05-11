=== Integration for Engaging Networks and Gravity Forms ===
Contributors: drywallbmb, kenjigarland, rxnlabs
Tags: forms, crm, integration
Requires at least: 3.6
Tested up to: 5.3
Requires PHP: 5.4.45
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A Gravity Forms Add-On to feed submission data into the Engaging Networks CRM/fundraising/advocacy platform.

== Description ==

If you're using the [Gravity Forms](http://www.gravityforms.com/) plugin, you can now integrate it with the [Engaging Networks](https://www.engagingnetworks.net/) platform. This Add-On supports creating or updating basic supporter records, either as standalone or within the context of EN Page Builder pages.

To use this Add-On, you'll need to:

1. Have an licensed, active version of Gravity Forms >= 1.9.3
2. Have a working Engaging Networks instance
3. Configure your Engaging Networks account to have an API key and support connections from your server's IP address(es).

If you meet those requirements, this plugin is for you, and should make building new forms and passing supporter data into EN much easier than manually mucking with HTML provided by Engaging Networks.

*Initial development of this plugin was funded in part by [Access Now](https://accessnow.org/). Subsequent development was funded in part by [Amnesty International USA](https://www.amnestyusa.org/).*

== Installation ==

1. Log into your WordPress account and go to Plugins > Add New. Search for "Gravity Forms Engaging Networks" in the "Add plugins" section, then click "Install Now". Once it installs, it will say "Activate". Click that and it should say "Active". Alternatively, you can upload the gravityforms-en directory directly to your plugins directory (typically /wp-content/plugins/)
2. Navigate to Forms > Settings in the WordPress admin
3. Click on "Engaging Networks" in the lefthand column of that page
4. Enter your organization's ID as well as a valid API key for your Engaging Networks account.
5. Once you've entered your Engaging Networks account details, create a form or edit an existing form's settings. You'll see an "Engaging Networks" tab in settings where you can create a feed. This allows you to pick and choose which form fields you'll send over to EN from the form, including the Page Builder page to assign responses to. You also have the option of setting some conditional logic to pick and choose which information gets sent.

== Frequently Asked Questions ==

= Help! I can't get this plugin to get any data from Engaging Networks! What should I do? =

There are typically two hurdles to successfully getting this plugin and Engaging Networks to communicate. The first and most common problem is that the API key you've entered isn't properly configured to be used from the IP address of the server you're using this plugin on — make sure to check with your host if you have questions about what IP address to enter into Engaging Networks.

A second, less-common problem is that your EN account is on a different server than this plugin assumes: While "www.e-activist.com" is the default, some EN accounts may be on "us.e-activist.com" or some other domain. To make this change, you'll need to add some code to your site (either in your theme or a plugin) to change it. The filter is called `gf_en_api_base_url` and here's an example use:

	function mytheme_change_en_url( $url ) {
		return 'https://us.e-activist.com/ens/service';
	}
	add_filter( 'gf_en_api_base_url', 'mytheme_change_en_url' );

= Does this work with Ninja Forms, Contact Form 7, Jetpack, etc? =

Nope. This is specifically an Add-On for Gravity Forms and will not have any effect if installed an activated without it.

= What version of Gravity Forms do I need? =

You must be running at least Gravity Forms 1.9.3.

= What kinds of data can this pass to Engaging Networks? =

As of 2.0, this Add-On can pass *basic constituent data* to EN as well as Page Builder form submissions.

Page submissions can include custom supporter fields and Opt-Ins, although as of now the EN API does not provide a way for this plugin to "know" which fields are present or required on a given Page. (We hope the EN API will eventually expose a list of optional and required fields shown on a given Page so that we can simplifyy this Add-On's interface and be more confident form submissions will be successful, but until then, you'll have to wing it.)


== Changelog ==

= 2.1.2 =
* If the API auth token is expired, attempt to renew the auth token and send the form submission data to Engaging Networks

= 2.1.1 =
* Correcting datacenter labels in the admin to include hyphens. No functional changes.

= 2.1 =
* Added a new configuration setting to control for which EN datacenter is used.

= 2.0.71 =
* Updated the plugin settings page. Fixed the URL to the article that instructs users how to generate a API key for Engaging Networks.

= 2.0.7 =
* Refactored code for mapping email addresses and added a new potential EN field name for Email.

= 2.0.6 =
* Introduced `gf_en_api_base_url` filter to facilitate changing the base URL for connecting to the ENS API.
* Improved admin screen for inputting API key to suggest the proper IP address and remove superfluous client_id field.

= 2.0.5 =
* Bugfix to address issue with country and state fields not being properly converted to abbreviations before being sent to EN.

= 2.0.4 =
* Bugfix to address issue with passing "Opt-in" questions with empty values rather than Y or N; empty values are no longer passed to EN.

= 2.0.3 =
* Bugfix to address issue with email addresses not properly mapping to EN email fields due to capitalization inconsistencies.

= 2.0.2 =
* Bugfix to eliminate unnecessary "Register your copy of Gravity Forms" message in plugin list.

= 2.0.1 =
* Bugfix for handling field mapping of email address under some circumstances.

= 2.0.0 =
* Support introduced for Engaging Networks' Page Builder system, which deprecates previous campaigns. This support includes the ability to identify which Page a given form should submit to as well as the ability to map Opt-Ins and custom supporter fields.

= 1.1.2 =
* Initial public release.
