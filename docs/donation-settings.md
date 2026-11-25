# The Donation tab

Every GiftKit form has a **Donation** tab in its settings: **Forms**, open the form, then **Settings → Donation**. It holds the settings you're likely to change, in the order below. Click **Save Settings** after changing any of them.

## Add the form to a page

The form's shortcode, with a **Copy** button. Paste it into any page, or use the Gravity Forms block and choose the form. In a page builder such as Elementor, use its shortcode widget.

## Donation

**Preset amounts.** The amount buttons donors choose from, separated by commas, for example `25, 50, 100, 250`. They appear in the order you list them.

**Let donors enter another amount.** Adds an **Other** button, so donors can type their own amount.

**Minimum gift.** Gifts below this are refused, including amounts typed in by donors. A minimum also discourages bots that test stolen cards with tiny amounts. Leave it empty for no minimum.

**One-time gift name.** How one-time gifts are described in your Stripe Dashboard, for example "Donation to The Maker City". Stripe shows it with the gift amount, any processing fee and the entry number: "Donation to The Maker City: $25.00 gift + $0.75 to cover fees (entry 6244)".

**Offer monthly gifts.** Turns the **One time / Monthly** choice on or off. When it's off, every gift is a one-time gift, and **Monthly gift name** and **Manage-your-gift link** are hidden.

**Monthly gift name.** The name of monthly gifts, shown to donors at checkout and in your Stripe Dashboard.

**Manage-your-gift link.** Your Stripe customer portal link. Monthly donors see it in the thank-you message, so they can update their card or cancel on their own. See [Let monthly donors manage their gift](stripe-customer-portal.md).

## Processing fee

**Fee percentage.** Donors can choose to add this percentage to their gift to cover your card processing costs. The checkbox on the form says "Add 3% to your gift to cover our transaction costs?" (with your percentage). Set it to 0 to hide the option.

**Note below the fee option.** A short line under the checkbox explaining why covering the fee helps.

## Appearance

**Button and accent color.** The color of buttons, the chosen amount and the gift type toggle. Pick a color that matches your site, or leave it empty to use Gravity Forms' default. A color set on a Gravity Forms block or shortcode takes priority over this one.

## After the gift

**Thank-you message.** What donors read after giving. It's the form's default confirmation, so it also appears under **Settings → Confirmations**, and changing it in either place changes both.

It can use Gravity Forms merge tags, such as the donor's first name or the gift total. `{giftkit_manage_gift_link}` adds the manage-your-gift link for monthly donors and shows nothing for one-time gifts.

If you set the confirmation to send donors to a page or URL instead of showing a message, this setting is replaced by a link to **Confirmations**.

**Email donors a thank-you.** Sends each donor an email after their gift: a thank-you, a summary of the gift (amount, one-time or monthly, and the date), their note if they left one, and for monthly donors the manage-your-gift link. It's an ordinary Gravity Forms notification called "Donor Thank-You", so **Edit the email** opens it under **Settings → Notifications**, where you can change the wording, the sender or the subject. Turning this off switches the notification off.

Donors may also get Stripe's own payment receipt, depending on your Stripe email settings. That's a payment record; GiftKit's email is the thank-you.

If your organization gives donors a written acknowledgement for tax purposes, add the wording you need to the email, for example a line saying whether any goods or services were provided in exchange for the gift. What's required depends on your country and your organization, so GiftKit doesn't add it for you.

Forms created before GiftKit added this email don't have it yet: open the Donation tab, leave this on, and click **Save Settings**.

## Credit

**Show "Powered by GiftKit" below the form.** Adds a small GiftKit logo and link under the form. It's off unless you turn it on, and the form works the same either way.

## Changing the form itself

A GiftKit form is an ordinary Gravity Forms form, so you can edit it in the form editor: reword labels, add fields or reorder them. GiftKit finds the fields it manages by their role rather than their position, so the settings on this tab keep working.

The form also has two Stripe feeds, under **Settings → Stripe**: one for one-time gifts and one for monthly gifts. GiftKit keeps the monthly gift name and the conditions that pick a feed by gift type in step with this tab, so there's usually no need to change them.
