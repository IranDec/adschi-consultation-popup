=== Adschi Consultation Popup ===
Contributors: Mohammad Babaei
Donate link: https://adschi.com/
Tags: consultation, popup, form, recaptcha, elementor, divi
Requires at least: 5.0
Tested up to: 6.5
Stable tag: 1.6.2
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A modern, lightweight popup form triggered by a CSS class, designed for consultation requests (Name, Email, Phone, Date). Features reCAPTCHA, fast AJAX, a CRM-style admin dashboard, multiple form management, and an In-Post automatic banner/button module.

== Description ==

Adschi Consultation Popup is a lightweight, responsive, and easy-to-use WordPress plugin that allows you to capture consultation requests efficiently.
You can trigger the popup form from any button or link by simply adding the class `acp-trigger-popup-{id}`.

Features:
* Modern and lightweight popup form with multiple themes (Light/Dark)
* Captures Name, Email, Phone, Requested Date, Department, and File Uploads
* Fast AJAX submission
* Built-in CRM-style admin dashboard to manage requests with usage statistics
* Google reCAPTCHA v2, v3, and simple Math CAPTCHA integration to prevent spam
* Multi-language support (English, German, Persian) based on site locale
* Email notifications for both admin and users with detailed logs
* Easy integration with page builders like Elementor, Divi, etc.
* Multi-form support allowing creation of different forms for different purposes
* **New:** In-Post automatic module to inject call-to-action buttons and banners inside blog post content based on percentage rules. Includes click tracking statistics.

== Installation ==

1. Upload the `adschi-consultation-popup` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to `Consultation Requests -> Settings` to configure your admin email and Google reCAPTCHA keys.
4. Go to `Forms` to create a new form and get its trigger class (e.g., `acp-trigger-popup-form_123`).
5. Add the CSS class `acp-trigger-popup-{id}` to any button, link, or element where you want to trigger the popup.
6. (Optional) Go to `In-Post Module` to enable automatic injection of buttons/banners inside your blog posts.

== Frequently Asked Questions ==

= How do I trigger the popup? =
Create a form in the admin panel and simply add its CSS class `acp-trigger-popup-{id}` to any HTML element, such as a button or a link.

= Does it support reCAPTCHA? =
Yes, you can enable Google reCAPTCHA v2 (Checkbox), reCAPTCHA v3 (Invisible), or a simple Math CAPTCHA in the plugin settings.

= How do I automatically add buttons inside my blog posts? =
Go to the "In-Post Module" settings page. Enable it, select the target form, configure the button/banner design, and set your placement rules (e.g., show a button at 20% of the content, and a banner at 60%). The plugin will automatically insert them into your single blog posts and track their clicks.

= Where do I see the consultation requests? =
You can manage all requests from the "Consultation Requests" menu in your WordPress admin dashboard. You can change the status, add admin notes, and delete requests.

== Screenshots ==

1. The modern frontend popup form.
2. The CRM-style backend dashboard to manage requests.
3. Settings page.

== Changelog ==

= 1.6.2 =
* Fixed: In-Post Module DOM parser explicitly rejects penetrating page builder components (Divi Accordions, Elementor widgets, etc.), preventing buttons from being inserted inside those modules.

= 1.6.1 =
* Fixed: In-Post Module DOM parsing refactored to properly inject buttons between structural wrappers, ensuring they are not inserted inside single-block elements like Elementor components or FAQ blocks.

= 1.6 =
* Added: 6 cool CSS animations (Pulse, Shake, Shine, Bounce, Glow, Float) for the In-Post module Call-to-Action buttons. You can select them directly from the module settings to grab user attention.

= 1.5 =
* Fixed: In-Post Module now uses robust DOM parsing to avoid injecting buttons or banners inside nested HTML structures (like FAQ sections or inner divs).

= 1.4 =
* Added: In-Post Module to automatically inject call-to-action buttons and banners inside blog posts based on content percentage rules.
* Added: Click tracking statistics for the In-Post Module.
* Fixed: General improvements and translations.

= 1.3 =
* Added: Multi-form support. You can now create multiple forms with different fields, themes, and departments.
* Added: Email logging and advanced CRM statistics per form.
* Added: File upload field and department dropdown.
* Added: reCAPTCHA v3 and Math CAPTCHA support.
* Changed: Improved internationalization using standard site locales.
* Backward compatibility: Existing `acp-trigger-popup` classes still work and point to the default form.

= 1.0 =
* Initial Release. Features include a fast AJAX frontend popup, admin dashboard CRM, and reCAPTCHA support.
