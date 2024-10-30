=== Connect Contact Form 7 to Constant Contact ===
Contributors: thehowarde
Donate link: https://www.howardehrenberg.com
Tags: constant contact, Contact Form 7, email marketing, woocommerce, api, cf7
Requires at least: 4.8
Tested up to: 6.0
Requires PHP: 7.0
Stable tag: 1.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

This will connect Contact form 7 (or WooCommerce Checkout) to Constant Contact using the Constant Contact API V3. Requires an API Key and Secret for functionality to work.  Allows use of checkbox, all lists, and updates existing records.  Includes bot rejection and email domain checks.

== Description ==

Update March 2022. Users may need to update their API Secret and Application by visiting Constant Contact, and updating the settings page on this plugin.  See https://v3.developer.constantcontact.com/api_guide/auth_update_apps.html for the release of the Constant Contact Authentication Update.

This is an advanced Constant Contact to Contact Form 7 Connector. This plug-in will allow you to make a connection to Constant Contact's API using OAUTH protocol.  Retrieve all of your contact lists, and allow users to sign up for a single list, or multiple lists.  This will update existing contacts in your Constant Contact list, or add new if they don't exist.  In addition to adding or updating E-Mail addresses of contacts in your list, this will also allow you to push basic contact fields, including:

*   First Name
*   Last Name
*   Full Address Information including Country

Some uses for this plugin would be to add an optional checkbox to a regular contact form where users can subscribe to a single or multiple CTCT lists.  You could also include this with a product registration form, or pretty much make every form on your website a possibility for users to subscribe to your Constant Contact Lists.

### Additional Features
* Spam Prevention - Submitted e-mail addresses are subjected to a domain verification script, before they're submitted to Constant Contact.  This helps keep your contact list cleaner.
* Failsafe Methods- In the event that Constant Contact's API is down, the plugin will store failed attempts and retry twice daily until they are successfully added.
* Error Reporting - If email addresses are submitted and rejected, an email is sent to the admin. Admin will be informed of users who may have unsubscribed previously and other constant contact error codes.
* Authentication Failure Notification - in the event that the authorization to constant contact is lost, the admin will get a notification to re-authorize the application.
* Add Opt-In Checkbox to WooCommerce Checkout
* Includes prevention of bot submissions

Complete instructions can be found [How to create an API Key and Token](https://www.duckdiverllc.com/how-to-create-a-constant-contact-api-key/) and here [How to set up this plugin](https://www.duckdiverllc.com/connecting-constant-contact-and-contact-form-7/).

== Installation ==

1. Upload `dd-cf7-constant-contact-v3.php` to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Place `[ctct]` Form tag in your Contact Form 7 - Contact Form.

== Frequently Asked Questions ==

= How do I get a Constant Contact API Access Key and Secret? =

To get started, go to [Constant Contact Developer](https://app.constantcontact.com/pages/dma/portal/)

= Do you have detailed instructions on setting up the plugin? =

Yes.  There is a complete walkthrough here [How to create an API Key and Token](https://www.duckdiverllc.com/how-to-create-a-constant-contact-api-key/) and here [How to set up this plugin](https://www.duckdiverllc.com/connecting-constant-contact-and-contact-form-7/).

= The form isn't sending the data to Constant Contact =

If you are connected properly, which it will show on the settings page.  Then you must make sure you map your fields and tell the plugin if you are using the form tags or not.  See Screenshot #2 for the settings tab.

= Can I add Multiple Lists to the opt-in checkbox form tag? =

Yes, you can do this by using the list: option in the form tag (shortcode) multiple times, like this:
`[ctct ctct-815 list:f49xxxx0-0737-xxxx-a758-d4ae5292c4dd list:8dxxxx80-6131-xxxx-a4b4-d4ae528442b5]`

== Screenshots ==

1. Admin View of Constant Contact settings page.
2. CF7 Settings Tab for Contact Form
	1. Choose the list or lists you want to assign contacts to.
	2. This checkbox tells the plugin if you're using the shortcode from the form or whether you're using an automatic opt-in without a checkbox.
	3. ** You must map the fields to Constant Contact **
3. Potential Front end usage.

== Changelog ==

= 1.4 =
Updated to use the latest version of the Constant Contact Authentication Method. May require updating your settings on Constant Contact and within the plugin.  See https://v3.developer.constantcontact.com/api_guide/auth_update_apps.html

= 1.3.9 =
Rejects submissions posted via HTTP POST method - often used by robots to submit forms.

= 1.3.8 =
Added check for bot submissions

= 1.3.6 =
Fixes for cf7 form ID

= 1.3.3 =
Fixes for PHP 7.4 also when no list is chosen.

= 1.3 =
Allows for unsubscribed email to be sent to those who have previously unsubscribed. You can use an official Constant Contact form to re-subscribe.

= 1.2.7 =
Add if empty email... bail out

= 1.2.5 =
Fix error on form tag "ID"

= 1.2.4 =
Fix erroneous labeling of inactive forms.

= 1.2.3 =
Fix for Error in multiple lists with options checkboxes

= 1.2.2 =
Enable Multiple Lists on Opt-In

= 1.2.1 =
Add Refresh Lists

= 1.2 =
* Add WooCommerce Opt In Checkbox to Checkout.

= 1.1 =
* Add scheduled action to check for failures.
* Add error handling when API is down.
* Fix error output on settings page.

= 1.0.1 =
* Added site name to error reporting email for clarity.

= 1.0 =
* Initial Release

 == Upgrade Notice ==
 = None yet =
