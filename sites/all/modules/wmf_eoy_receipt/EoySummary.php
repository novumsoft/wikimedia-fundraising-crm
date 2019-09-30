<?php

namespace wmf_eoy_receipt;

use CRM_Contribute_PseudoConstant;
use CRM_Core_PseudoConstant;
use db_switcher;
use wmf_communication\Mailer;
use wmf_communication\Templating;
use wmf_communication\Translation;

class EoySummary {

  static protected $templates_dir;

  static protected $template_name;

  static protected $option_keys = [
    'year',
    'test',
    'batch',
    'job_id',
  ];

  protected $batch_max = 100;

  protected $year;

  protected $test;

  protected $from_address;

  protected $from_name;

  /**
   * @var string
   */
  protected $civi_prefix = '';

  protected $job_id;

  public function __construct($options = []) {
    $this->year = variable_get('wmf_eoy_target_year', NULL);
    $this->batch_max = variable_get('wmf_eoy_batch_max', 100);
    $this->test = variable_get('wmf_eoy_test_mode', TRUE);

    foreach (self::$option_keys as $key) {
      if (array_key_exists($key, $options)) {
        $this->$key = $options[$key];
      }
    }

    $this->from_address = variable_get('thank_you_from_address', NULL);
    $this->from_name = variable_get('thank_you_from_name', NULL);

    $this->civi_prefix = (new db_switcher())->get_prefix('civicrm');

    self::$templates_dir = __DIR__ . '/templates';
    self::$template_name = 'eoy_thank_you';
  }

  //FIXME rename
  public function calculate_year_totals() {
    $job_timestamp = date("YmdHis");
    db_insert('wmf_eoy_receipt_job')->fields([
      'start_time' => $job_timestamp,
      'year' => $this->year,
    ])->execute();

    $sql = <<<EOS
SELECT job_id FROM {wmf_eoy_receipt_job}
    WHERE start_time = :start
EOS;
    $result = db_query($sql, [':start' => $job_timestamp]);
    $row = $result->fetch();
    $this->job_id = $row->job_id;

    $year_start = "{$this->year}-01-01 00:00:01";
    $year_end = "{$this->year}-12-31 23:59:59";
    $endowmentFinancialType = CRM_Core_PseudoConstant::getKey(
      'CRM_Contribute_BAO_Contribution', 'financial_type_id', 'Endowment Gift'
    );
    $completedStatusId = CRM_Contribute_PseudoConstant::getKey(
      'CRM_Contribute_BAO_Contribution','contribution_status_id', 'Completed'
    );

    $email_temp = <<<EOS
CREATE TEMPORARY TABLE wmf_eoy_receipt_email (
    email VARCHAR(254) COLLATE utf8_unicode_ci PRIMARY KEY
)
EOS;
    db_query($email_temp);

    $email_insert = <<<EOS
INSERT INTO wmf_eoy_receipt_email
SELECT email
FROM {$this->civi_prefix}civicrm_contribution contribution
JOIN {$this->civi_prefix}civicrm_email email
  ON email.contact_id = contribution.contact_id
  AND email.is_primary
WHERE receive_date BETWEEN '{$year_start}' AND '{$year_end}'
  AND financial_type_id <> $endowmentFinancialType
  AND contribution_status_id = $completedStatusId
  AND contribution_recur_id IS NOT NULL
  AND email <> 'nobody@wikimedia.org'
ON DUPLICATE KEY UPDATE email = email.email
EOS;
    $result = db_query($email_insert);
    $num_emails = $result->rowCount();
    watchdog('wmf_eoy_receipt',
      t("Found !num distinct emails with recurring giving during !year",
        [
          "!num" => $num_emails,
          "!year" => $this->year,
        ]
      )
    );

    $contact_temp = <<<EOS
CREATE TEMPORARY TABLE wmf_eoy_receipt_contact (
    contact_id INT(10) unsigned PRIMARY KEY,
    email VARCHAR(254) COLLATE utf8_unicode_ci,
    preferred_language VARCHAR(16),
    name VARCHAR(255),
    contact_contributions TEXT
)
EOS;
    db_query($contact_temp);

    db_query('SET session group_concat_max_len = 5000');
    // Build a table of contribution and contact data, grouped by contact
    $contact_insert = <<<EOS
INSERT INTO {wmf_eoy_receipt_contact}
SELECT
    contact.id,
    email.email,
    contact.preferred_language,
    contact.first_name,
    GROUP_CONCAT(CONCAT(
        DATE_FORMAT(contribution.receive_date, '%Y-%m-%d'),
        ' ',
        COALESCE(original_amount, total_amount),
        ' ',
        COALESCE(original_currency, currency)
    ))
FROM {wmf_eoy_receipt_email} eoy_email
JOIN {$this->civi_prefix}civicrm_email email
    ON email.email = eoy_email.email AND email.is_primary
JOIN {$this->civi_prefix}civicrm_contact contact
    ON email.contact_id = contact.id
JOIN {$this->civi_prefix}civicrm_contribution contribution
    ON email.contact_id = contribution.contact_id AND email.is_primary
LEFT JOIN {$this->civi_prefix}wmf_contribution_extra extra
    ON extra.entity_id = contribution.id
WHERE receive_date BETWEEN '{$year_start}' AND '{$year_end}'
    AND financial_type_id <> $endowmentFinancialType
    AND contribution_status_id = $completedStatusId
GROUP BY email.email, contact.id, contact.preferred_language, contact.first_name
EOS;

    $result = db_query($contact_insert);
    $num_contacts = $result->rowCount();
    watchdog('wmf_eoy_receipt',
      t("Found !num contact records for emails with recurring giving during !year",
        [
          "!num" => $num_contacts,
          "!year" => $this->year,
        ]
      )
    );
    db_query('DROP TEMPORARY TABLE {wmf_eoy_receipt_email}');

    // Insert data into the persistent table. We start inserting from the most
    // recent contact ID so in case of two records sharing an email address, we
    // use the more recent name and preferred language.
    $persist_insert = <<<EOS
INSERT INTO {wmf_eoy_receipt_donor}
  (job_id, email, preferred_language, name, status, contributions_rollup)
SELECT
    {$this->job_id} AS job_id,
    email,
    preferred_language,
    name,
    'queued',
    contact_contributions
FROM {wmf_eoy_receipt_contact}
ORDER BY contact_id DESC
ON DUPLICATE KEY UPDATE contributions_rollup = CONCAT(
    contributions_rollup, ',', contact_contributions
)
EOS;
    db_query($persist_insert);

    watchdog('wmf_eoy_receipt',
      t("Calculated summaries for !num recurring donors giving during !year",
        [
          "!num" => $num_emails,
          "!year" => $this->year,
        ]
      )
    );
    db_query('DROP TEMPORARY TABLE {wmf_eoy_receipt_contact}');
    return $this->job_id;
  }

  public function send_letters() {
    $mailer = Mailer::getDefault();

    $sql = <<<EOS
SELECT *
FROM {wmf_eoy_receipt_donor}
WHERE
    status = 'queued'
    AND job_id = :id
LIMIT {$this->batch_max}
EOS;
    $result = db_query($sql, [':id' => $this->job_id]);
    $succeeded = 0;
    $failed = 0;

    foreach ($result as $row) {
      $email = $this->render_letter($row);

      if ($this->test) {
        $email['to_address'] = variable_get('wmf_eoy_test_email', NULL);
      }

      $success = $mailer->send($email);

      if ($success) {
        $status = 'sent';
        $succeeded += 1;
      }
      else {
        $status = 'failed';
        $failed += 1;
      }

      db_update('wmf_eoy_receipt_donor')->fields([
        'status' => $status,
      ])->condition('email', $row->email)->execute();
    }

    watchdog('wmf_eoy_receipt',
      t("Successfully sent !succeeded messages, failed to send !failed messages.",
        [
          "!succeeded" => $succeeded,
          "!failed" => $failed,
        ]
      )
    );
  }

  public function render_letter($row) {
    if (!$this->from_address || !$this->from_name) {
      throw new \Exception("Must configure a valid return address in the Thank-you module");
    }
    $language = Translation::normalize_language_code($row->preferred_language);
    $totals = [];
    $contributions = [];
    foreach (explode(',', $row->contributions_rollup) as $contribution_string) {
      $terms = explode(' ', $contribution_string);
      $contribution = [
        'date' => $terms[0],
        // FIXME not every currency uses 2 sig digs
        'amount' => round($terms[1], 2),
        'currency' => $terms[2],
      ];
      $contributions[] = $contribution;
      if (!isset($totals[$contribution['currency']])) {
        $totals[$contribution['currency']] = [
          'amount' => 0.0,
          'currency' => $contribution['currency'],
        ];
      }
      $totals[$contribution['currency']]['amount'] += $contribution['amount'];
    }
    // Sort contributions by date
    usort($contributions, function($c1, $c2) {
      return $c1['date'] <=> $c2['date'];
    });

    $template_params = [
      'name' => $row->name,
      'contributions' => $contributions,
      'totals' => $totals,
      'year' => $this->year,
    ];
    $template = $this->get_template($language, $template_params);
    $email = [
      'from_name' => $this->from_name,
      'from_address' => $this->from_address,
      'to_name' => $row->name,
      'to_address' => $row->email,
      'subject' => $template->render('subject'),
      'plaintext' => $template->render('txt'),
      'html' => $template->render('html'),
    ];

    return $email;
  }

  protected function get_template($language, $template_params) {
    return new Templating(
      self::$templates_dir,
      self::$template_name,
      $language,
      $template_params
    );
  }
}
