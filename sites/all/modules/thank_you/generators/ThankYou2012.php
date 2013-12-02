<?php namespace thank_you\generators;

class ThankYou2012 extends RenderTranslatedPage {
	function __construct() {
		$this->title = 'Fundraising_2012/Translation/Thank_you_letter';
		$this->proto_file = __DIR__ . '/../templates/html/thank_you.$1.html';

		$this->substitutions = array(
			'/\[given name\]/' => '{{contact.first_name}}',
			'/\[date\]/' => '{{contribution.receive_date}}',
			'/\[amount\]/' => '{{contribution.contribution_source|l10n_currency(locale)}}',
			'/\[contributionId\]/' => '{{contribution.id}}',

			'/\[ifRecurring\]/' => '{% if recurring %}',
			'/\[endifRecurring\]/' => '{% endif %}',

			'/\[#twitter ((?:(?!\]).)*)\]/' => '<a href="https://twitter.com/Wikipedia">$1</a>',
			'/\[#identica ((?:(?!\]).)*)\]/' => '<a href="https://identi.ca/wikipedia">$1</a>',
			'/\[#google ((?:(?!\]).)*)\]/' => '<a href="https://plus.google.com/+Wikipedia/posts">$1</a>',
			'/\[#facebook ((?:(?!\]).)*)\]/' => '<a href="https://www.facebook.com/wikipedia">$1</a>',
			'/\[#blog ((?:(?!\]).)*)\]/' => '<a href="https://blog.wikimedia.org">$1</a>',
			// TODO: DO WE HAVE TRANSLATIONS FOR THE ANNUAL REPORT
			'/\[#annual ((?:(?!\]).)*)\]/' => '<a href="https://wikimediafoundation.org/wiki/Annual_Report">$1</a>',
			// TODO: DO WE HAVE TRANSLATIONS FOR THE ANNUAL PLAN
			'/\[#plan ((?:(?!\]).)*)\]/' => '<a href="http://upload.wikimedia.org/wikipedia/foundation/4/4f/2012-13_Wikimedia_Foundation_Plan_FINAL_FOR_WEBSITE.pdf">$1</a>',
			// TODO: DO WE HAVE TRANSLATIONS FOR THE 5-YEAR, STRATEGIC PLAN
			'/\[#strategic ((?:(?!\]).)*)\]/' => '<a href="https://wikimediafoundation.org/wiki/Wikimedia_Movement_Strategic_Plan_Summary">$1</a>',
			'/\[#shop ((?:(?!\]).)*)\]/' => '<a href="https://shop.wikimedia.org">$1</a>',
			'/\[#unsubscribe ((?:(?!\]).)*)\]/' => '<a href="{{unsubscribe_link|raw}}">$1</a>',
			'/\[#recurringCancel ((?:(?!\]).)*)\]/' => '<a href="https://wikimediafoundation.org/wiki/Special:LandingCheck?landing_page=Cancel_or_change_recurring_payments&basic=true&country={{ country }}&language={{ language }}">$1</a>',
			'/\[#translate ((?:(?!\]).)*)\]/' => '<a href="https://meta.wikimedia.org/w/index.php?title=Special:Translate&group=page-Fundraising+2012%2FTranslation%2FThank+you+letter">$1</a>',
		);
	}
}