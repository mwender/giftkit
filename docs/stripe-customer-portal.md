# Let monthly donors manage their gift

Monthly donors eventually need to change their card or stop giving. Stripe's **customer portal** lets them do that on their own, and GiftKit can put a link to it in the thank-you message for every monthly gift.

Donors don't need an account on your site. They open the link, enter their email address, and Stripe emails them a login link to their portal.

## 1. Turn on Stripe's customer portal link

1. Sign in to Stripe and open the customer portal settings at [dashboard.stripe.com/settings/billing/portal](https://dashboard.stripe.com/settings/billing/portal).
2. Under **Ways to get started**, click **Activate link**.
3. On the same page, choose what donors can do in the portal: for example update their payment method, cancel their subscription, or view past invoices.
4. Copy the link Stripe shows. It looks like `https://billing.stripe.com/p/login/…`.

Optionally, set your logo and colours on Stripe's [branding settings](https://dashboard.stripe.com/settings/branding), so the portal looks like your organisation.

Stripe's own guide: [Activate the no-code customer portal](https://docs.stripe.com/customer-management/activate-no-code-customer-portal).

**Use the link from the mode your site takes payments in.** If you try this in Stripe's test mode first, repeat these steps in live mode before taking real gifts, and paste the live link into GiftKit. Test-mode links contain `test_`.

## 2. Add the link to GiftKit

1. In WordPress, go to **Forms**, open your donation form, and choose **Settings → Donation**.
2. Make sure **Offer monthly gifts** is on.
3. Paste the link into **Manage-your-gift link**, and save.

GiftKit then adds `{giftkit_manage_gift_link}` to the end of your **Thank-you message** (if it isn't there already). You can move it anywhere in the message. After a monthly gift, it becomes:

> To update your card or cancel your monthly gift at any time, [manage your monthly gift](#).

The link fills in the donor's email address for them. After a one-time gift, the tag shows nothing.

The same tag works in Gravity Forms notifications, so you can include the link in an email to monthly donors too. It's listed in the merge tag menu as **Manage monthly gift link**.

## How donors log in

Stripe finds the donor by the email address they gave on the form, so they should use that one. If a donor has given more than once, Stripe opens the most recent record with an active subscription. Donors can't change their email address in the portal.
