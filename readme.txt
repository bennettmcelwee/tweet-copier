=== Tweet Copier ===
Contributors: bennettmcelwee
Donate link: http://thunderguy.com/semicolon/donate/
Tags: twitter, tweet, import, copy
Requires at least: 3.0
Tested up to: 3.5.2
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Tweet Copier copies tweets from your Twitter account to your blog, and keeps your blog updated as you create new tweets.

== Description ==

Tweet Copier keeps your WordPress blog synchronised with your Twitter account. It copies all old tweets into your blog, and continuously updates it with your new tweets. You can configure the schedule and various other aspects of the plugin.

== Installation ==

= Install the plugin =

Log in your WordPress dashboard, go to the Plugins screen and search for *Tweet Copier*. When it appears, click *Install Now*, then *Activate*.

To install it manually from a zip file, just unzip the `tweet-copier.zip` file into the `/wp-content/plugins/` directory. Then log in your WordPress dashboard, go to the Plugins screen and click *Activate* in the *Tweet Copier* section.

= Set up the plugin =

First you'll need to obtain credentials from Twitter. This allows your Tweet Copier plugin to access Twitter.

1. Go to [Twitter Developers](https://dev.twitter.com/) and sign in using your normal Twitter login.
2. Click your icon at the top right and select *My Applications*
3. Click *Create a new application*
4. For *Name*, type `Tweet Mirror for` and your blog name, for example `Tweet Mirror for Spiderblog`. Fill in other fields as appropriate and create the application.
5. On the application screen, click *Create my access token*
6. Your credentials are the the codes next to *Consumer key*, *Consumer secret*, *Access token* and *Access token secret*

Now set up the Tweet Copier plugin.

1. Log in your WordPress dashboard, click *Settings* on the left and click *Tweet Copier*.
2. Enter the four credentials from the Twitter page.
3. Enter the screen name of the Twitter account you want to copy. It can be yours or anybody else's.
4. If you want, adjust the other settings.
5. Click <em>Save Settings</em>.

== Frequently Asked Questions ==

= Why do I need to create a Twitter application? =

Twitter requires programs (such as Tweet Copier) to use a "Consumer secret" in order to read tweets.
Every blog using Tweet Copier (yours, mine and everybody else's) has to have its own secret. The only way for your
blog to have its own secret is for you to create it, otherwise it wouldn't be a secret!

= Why don't the automatic updates always work? =

In WordPress, scheduled tasks are only checked when the blog is accessed by somebody. If you blog doesn't get
much traffic, then the automatic checking might not happen as often as you've requested it to.

== Screenshots ==

1. Tweet Copier settings screen. Log into Twitter to grab your credientials, then enter the Twitter screen name
you want to copy. Tweet Copier will do the rest.

== Changelog ==

= 1.0 =
* First released version.

== Upgrade Notice ==

= 1.0 =
* Fully functional.

== Advanced ==

= Network usage =

If you use Network Deactivation to disable Tweet Copier on all sites in a network, you may find that Tweet Copier keeps copying tweets even though the plugin is deactivated. To avoid this, try using the [Proper Network Activation plugin](http://wordpress.org/plugins/proper-network-activation/). This only applies if you use Network Activation - there should be no problem if you activate or deactivate from the individual sites.

