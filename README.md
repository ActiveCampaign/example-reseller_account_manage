ActiveCampaign Reseller Account Custom API Script: Add account, template, generate log-in link, and more.

## Requirements

1. Valid ActiveCampaign reseller account with a billing profile set up
2. A web server where you can run PHP code
3. Our [ActiveCampaign PHP wrapper](https://github.com/ActiveCampaign/activecampaign-api-php) added to your application environment

## Installation and Usage

You can install **example-reseller_account_manage** by downloading (or cloning) the source.

Start by defining your custom domain, ActiveCampaign reseller URL, API Key, and path to the PHP library towards the top of the script:

<pre>
$your_domain = "yourdomain.com";
$reseller_api_url = "https://www.activecampaign.com";
$reseller_api_key = "";
$path_to_api_wrapper = "../../activecampaign-api-php/includes";
</pre>

Resellers can find their API URL and key in the reseller administration interface:

![Finding your API Key](http://d226aj4ao1t61q.cloudfront.net/jcgbye7yp_screenshot2012-09-18at10.35.10am.jpg)

## Documentation and Links

* [Blog post: Manage Your Reseller Accounts with our API](http://www.activecampaign.com/blog/manage-your-reseller-accounts-with-our-api/)
* [Full API documentation](http://activecampaign.com/api)

## Reporting Issues

We'd love to help if you have questions or problems. Report issues using the [Github Issue Tracker](https://github.com/ActiveCampaign/example-subscription_form_embed/issues) or email help@activecampaign.com.