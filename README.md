![GiftKit for Gravity Forms](.wordpress-org/banner-1544x500.png)

# GiftKit for Gravity Forms #
**Contributors:** [TheWebist](https://profiles.wordpress.org/TheWebist/)  
**Tags:** donations, donation form, gravity forms, stripe, nonprofit  
**Requires at least:** 6.5  
**Tested up to:** 7.1  
**Requires PHP:** 8.1  
**Stable tag:** 1.0.0  
**License:** GPL2+  
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html  

A ready-made donation form for Gravity Forms, with one-time and monthly gifts, preset amounts, an optional processing fee and Stripe payments.

## Description ##

Building a good donation form in Gravity Forms takes a lot of setup: pages, pricing fields, conditional logic, two Stripe feeds, and styling that makes it feel like giving rather than filling in a form. GiftKit does all of that in one step, then keeps the handful of settings you'll actually change on a single **Donation** tab.

**What donors get**

* A three-page form: the gift, the donor's details, then payment
* One-time or monthly gifts, chosen with a clear toggle
* Preset amount buttons, plus their own amount if you allow it
* An optional "cover the processing fee" choice
* A summary of their gift before they pay, and a total that says whether it's charged once or every month
* Card payments through Stripe, including Link

**What you get**

* Forms → New Donation Form creates the form, both Stripe feeds and, if you like, a draft page to hold it
* A Donation settings tab for the preset amounts, minimum gift, monthly gifts, the fee and its note, the button color, and the thank-you message, with the form's shortcode ready to copy
* A minimum gift, which also discourages bots that test stolen cards with tiny amounts
* Everything else stays ordinary Gravity Forms: entries, notifications, confirmations and add-ons all work as usual

GiftKit is built on Gravity Forms' own fields, pricing and the official Stripe Add-On, so payments, subscriptions and receipts are handled by tools you already trust. Its colours follow the form's Gravity Forms styling, and amounts use your Gravity Forms currency.

**Requirements**

* [Gravity Forms](https://www.gravityforms.com/) 2.9 or later
* The [Gravity Forms Stripe Add-On](https://www.gravityforms.com/add-ons/stripe/), connected to your Stripe account

GiftKit isn't affiliated with or endorsed by Gravity Forms or Stripe.

## External services ##

GiftKit doesn't connect to any external service itself. Its forms take payments through the Gravity Forms Stripe Add-On, which sends payment details to [Stripe](https://stripe.com/).

When a donor submits a GiftKit form, the Stripe Add-On sends Stripe the gift amount, whether it's one-time or monthly, the donor's name, email address and billing address, and the donor's note, phone number and business name (if given) as payment metadata. Card details go directly from the donor's browser to Stripe. Stripe uses this to take the payment, set up monthly subscriptions and send receipts.

Stripe's [terms of service](https://stripe.com/legal) and [privacy policy](https://stripe.com/privacy) apply.

## Installation ##

1. Install and activate Gravity Forms and the Gravity Forms Stripe Add-On, and connect Stripe under Forms → Settings → Stripe.
2. Install and activate GiftKit.
3. Go to Forms → New Donation Form, give the form a title, and create it.
4. Adjust the form's Donation settings, then publish the page that holds it (or add the form to any page with the Gravity Forms block or shortcode).

## Frequently Asked Questions ##

### Do I need the Gravity Forms Stripe Add-On? ###

Yes. GiftKit takes payments through it, and sets up its feeds for you.

### Can I change the fields? ###

Yes. A GiftKit form is a normal Gravity Forms form, so you can edit labels, add fields or reorder them in the form editor. GiftKit finds the fields it manages by role rather than position, so its settings keep working.

### How do I test it without real charges? ###

Put the Stripe Add-On in test mode and use Stripe's test card numbers. The New Donation Form page shows whether Stripe is in test or live mode.

### Can I turn off monthly gifts or the processing fee? ###

Yes. Turn off "Offer monthly gifts" on the Donation tab, or set the fee percentage to 0.

### Can monthly donors update their card or cancel on their own? ###

Yes. Turn on Stripe's no-code customer portal link in your Stripe Dashboard, then paste it into "Manage-your-gift link" on the Donation tab. Monthly donors then see a "manage your monthly gift" link in the thank-you message, with their email filled in. Step-by-step instructions: https://giftkit.org/docs/stripe-customer-portal/

### Does GiftKit add a link to its website on my form? ###

Only if you choose to. "Show 'Powered by GiftKit' below the form" on the Donation tab is off by default, and the form works the same either way.

### Which currencies work? ###

Whichever currency Gravity Forms is set to. Amounts and totals use its symbol and formatting.

## Screenshots ##

### 1. Forms → New Donation Form: what donors will see, whether your site is ready, and your donation forms. ###
![Forms → New Donation Form: what donors will see, whether your site is ready, and your donation forms.](.wordpress-org/screenshot-1.png)

### 2. The Donation settings tab: amounts, monthly gifts, the processing fee and the thank-you message. ###
![The Donation settings tab: amounts, monthly gifts, the processing fee and the thank-you message.](.wordpress-org/screenshot-2.png)

### 3. Page 1 of the form: one-time or monthly, preset amounts, and a note. ###
![Page 1 of the form: one-time or monthly, preset amounts, and a note.](.wordpress-org/screenshot-3.png)

### 4. Page 2: the donor's details, with a summary of their gift. ###
![Page 2: the donor's details, with a summary of their gift.](.wordpress-org/screenshot-4.png)

### 5. Page 3: card payment through Stripe, the optional processing fee, and a total that says whether it's charged once or monthly. ###
![Page 3: card payment through Stripe, the optional processing fee, and a total that says whether it's charged once or monthly.](.wordpress-org/screenshot-5.png)


## Changelog ##

### 1.0.0 ###
* Initial release.

## Upgrade Notice ##

### 1.0.0 ###
Initial release.
