=== Adschi Consultation Popup ===
Contributors: Mohammad Babaei
Donate link: https://adschi.com/
Tags: consultation, popup, form, recaptcha, elementor, divi
Requires at least: 5.0
Tested up to: 6.5
Stable tag: 1.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A modern, lightweight popup form triggered by a CSS class, designed for consultation requests (Name, Email, Phone, Date). Features reCAPTCHA, fast AJAX, and a CRM-style admin dashboard.

== Description ==

Adschi Consultation Popup is a lightweight, responsive, and easy-to-use WordPress plugin that allows you to capture consultation requests efficiently.
You can trigger the popup form from any button or link by simply adding the class `acp-trigger-popup`.

Features:
* Modern and lightweight popup form
* Captures Name, Email, Phone, and Requested Date
* Fast AJAX submission
* Built-in CRM-style admin dashboard to manage requests
* Google reCAPTCHA v2 integration to prevent spam
* Multi-language support (English, German, Persian)
* Email notifications for both admin and users
* Easy integration with page builders like Elementor, Divi, etc.

== Installation ==

1. Upload the `adschi-consultation-popup` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to `Consultation Requests -> Settings` to configure your form title, admin email, and Google reCAPTCHA keys.
4. Add the CSS class `acp-trigger-popup` to any button, link, or element where you want to trigger the popup. For example, in Elementor, edit the button widget, go to Advanced -> CSS Classes, and add `acp-trigger-popup`.

== Frequently Asked Questions ==

= How do I trigger the popup? =
Simply add the CSS class `acp-trigger-popup` to any HTML element, such as a button or a link.

= Does it support reCAPTCHA? =
Yes, you can enable Google reCAPTCHA v2 (Checkbox) by adding your Site Key and Secret Key in the plugin settings.

= Where do I see the consultation requests? =
You can manage all requests from the "Consultation Requests" menu in your WordPress admin dashboard. You can change the status, add admin notes, and delete requests.

== Screenshots ==

1. The modern frontend popup form.
2. The CRM-style backend dashboard to manage requests.
3. Settings page.

== Changelog ==

= 1.0 =
* Initial Release. Features include a fast AJAX frontend popup, admin dashboard CRM, and reCAPTCHA support.
