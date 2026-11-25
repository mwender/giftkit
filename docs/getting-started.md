# Getting started

This page takes you from installing GiftKit to taking real gifts: connect Stripe, create the form, put it on a page, test it, then go live.

## What you need

- WordPress 6.5 or later, and PHP 8.1 or later
- [Gravity Forms](https://www.gravityforms.com/) 2.9 or later
- The Gravity Forms Stripe Add-On
- A [Stripe](https://stripe.com/) account for the organization receiving the gifts

## 1. Install and activate the plugins

1. Install and activate Gravity Forms and the Gravity Forms Stripe Add-On.
2. Install and activate GiftKit.

GiftKit needs the Stripe Add-On: until it's active, GiftKit shows a notice and stays out of the way.

## 2. Connect Stripe in test mode

Start in test mode, so you can try the form without real charges.

1. Go to **Forms → Settings → Stripe**.
2. Connect your Stripe account and choose **test mode**.
3. Click **Configure Webhooks**. The Stripe Add-On sets up the webhook in Stripe for you.

The webhook matters: it's how Stripe tells your site that a payment went through or a monthly gift renewed. Without it, gifts stay marked as "Processing" in your entries.

While you're in Stripe, check which payment methods are turned on in your Stripe Dashboard's payment method settings. Donors see each one as an option on the form, and some, such as buy now, pay later methods, may not be allowed for donations under their providers' terms.

## 3. Create the form

1. Go to **Forms → New Donation Form**, or click **New Donation Form** next to **Add New** on the Forms list.
2. Check the list under **Before you start**: it shows whether Stripe is connected, in which mode, and which currency gifts will be in.
3. Give the form a title.
4. If you want a new page for the form, leave **Also create a page for the form** ticked and give the page a title. It's saved as a draft, so nothing goes public yet. Untick it if you'll add the form to an existing page.
5. Click **Create Donation Form**.

GiftKit creates the form, its two Stripe feeds (one for one-time gifts, one for monthly gifts), a thank-you message and an admin notification. The next screen links to everything you'll want to do next.

## 4. Adjust the settings

Open the form's **Settings → Donation** tab. It holds everything you're likely to change: the preset amounts, monthly gifts, the processing fee, the button color and the thank-you message. The defaults work as they are, so change only what you need. See [The Donation tab](donation-settings.md) for every setting.

## 5. Add the form to a page

If GiftKit created a draft page, it already holds the form: edit and publish it when you're ready.

Otherwise, copy the shortcode from **Add the form to a page** at the top of the Donation tab and paste it into any page, or add the Gravity Forms block and choose the form. Page builders such as Elementor take the shortcode in their shortcode widget.

## 6. Make test gifts

With Stripe in test mode, give a few gifts as a donor would: one one-time gift, one monthly gift, and one with the processing fee. Use Stripe's test card **4242 4242 4242 4242** with any future expiry date and any security code.

Then check:

- **Forms → Entries:** each gift is there, and after a moment its payment shows as paid (one-time) or active (monthly). If they stay "Processing", the webhook isn't reaching your site: click **Configure Webhooks** again.
- **Stripe Dashboard:** each payment and subscription is there. One-time gifts are described with the gift, any fee and the entry number.
- **Your email:** the admin notification arrived.

## 7. Go live

1. Go to **Forms → Settings → Stripe**, switch to **live mode**, and click **Configure Webhooks** again. Live mode has its own webhook.
2. If you added a [manage-your-gift link](stripe-customer-portal.md), replace the test link with the live one.
3. Publish the page.

To be sure, make one small real gift, then refund it from the Stripe Dashboard.
