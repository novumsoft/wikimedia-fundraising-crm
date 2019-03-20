<?php
use CRM_Wmffraud_ExtensionUtil as E;

class CRM_Wmffraud_Form_Report_FraudReportsBase extends CRM_Report_Form {

  protected $_customGroupExtends = [];

  protected $_customGroupGroupBy = FALSE;

  /**
   * @var string Fredge DB Name.
   */
  protected $fredge;

  /**
   * @var string Drupal DB Name.
   */
  protected $drupal;

  function __construct() {

    global $databases;
    $this->drupal = $databases['default']['default']['database'];
    $this->fredge = substr($this->drupal, 0,
      3) === 'dev' ? 'dev_fredge' : 'fredge';

    $this->_columns = [];
    $this->_columns['civicrm_contact'] = [
      'dao' => 'CRM_Contact_DAO_Contact',
      'fields' => [
        'sort_name' => [
          'title' => E::ts('Contact Name'),
          'required' => TRUE,
          'default' => TRUE,
          'no_repeat' => TRUE,
        ],
        'id' => [
          'no_display' => TRUE,
          'required' => TRUE,
        ],
        'first_name' => [
          'title' => E::ts('First Name'),
          'no_repeat' => TRUE,
        ],
        'last_name' => [
          'title' => E::ts('Last Name'),
          'no_repeat' => TRUE,
        ],
      ],
      'filters' => [
        'sort_name' => [
          'title' => E::ts('Contact Name'),
          'operator' => 'like',
        ],
        'id' => [
          'title' => E::ts('Contact ID')
        ],
      ],
    ];

    $this->_columns['payments_fraud'] = [
      'alias' => 'payments_fraud',
      'fields' => [
        'user_ip' => [
          'name' => 'user_ip',
          'title' => E::ts('IP Address'),
          'default' => TRUE,
          'dbAlias' => "INET_NTOA(payments_fraud_civireport.user_ip)",
        ],
        'validation_action' => [
          'title' => E::ts('Payment Action'),
        ],
        'fredge_date' => [
          'title' => E::ts('Payment attempt date'),
          'name' => 'date',
        ],
        'gateway' => [
          'title' => E::ts('Payment gateway'),
          'name' => 'gateway',
        ],
        'order_id' => [
          'title' => E::ts('Order ID'),
          'name' => 'order_id',
        ],
        'risk_score' => [
          'title' => E::ts('Risk Score'),
          'name' => 'risk_score',
        ],
        'server' => [
          'title' => E::ts('Server'),
          'name' => 'server',
        ],
      ],
      'filters' => [
        'user_ip' => [
          'name' => 'user_ip',
          'title' => E::ts('IP Address'),
          'type' => CRM_Utils_Type::T_STRING,
        ],
        'order_id' => [
          'title' => E::ts('Order ID'),
          'name' => 'order_id',
          'type' => CRM_Utils_Type::T_STRING,
        ],
        'validation_action' => [
          'title' => E::ts('Action'),
          'type' => CRM_Utils_Type::T_STRING,
        ],
        'fredge_date' => [
          'title' => E::ts('Payment attempt date'),
          'name' => 'date',
          'type' => CRM_Utils_Type::T_DATE,
        ],
      ],
      'order_bys' => [
        'fredge_date' => [
          'title' => E::ts('Payment attempt date'),
          'name' => 'date',
          'type' => CRM_Utils_Type::T_DATE,
        ],
        'user_ip' => [
          'name' => 'user_ip',
          'title' => E::ts('IP Address'),
          'type' => CRM_Utils_Type::T_STRING,
        ],
        'validation_action' => [
          'title' => E::ts('Action'),
          'type' => CRM_Utils_Type::T_STRING,
        ],
      ],
    ];

    $this->_columns['payments_fraud_breakdown'] = [
      'fields' => [
        'filter_name' => [
          'name' => 'filter_name',
          'title' => ts('Fraud filter'),
          'type' => CRM_Utils_Type::T_STRING,
          'dbAlias' => "GROUP_CONCAT(CONCAT(_fraud_breakdown_civireport.filter_name, _fraud_breakdown_civireport.risk_score) SEPARATOR \"-\")",
        ],
      ],
      'filters' => [
        'filter_name' => [
          'name' => 'filter_name',
          'title' => ts('Fraud filter'),
          'type' => CRM_Utils_Type::T_STRING
        ],
      ],
    ];

    $this->_columns['ip_failure_stats'] = [
      'fields' => [
        'ip_fails_count' => [
          'name' => 'ip_fails_count',
          'title' => E::ts('Rejects for IP in the specified date range'),
          'type' => CRM_Utils_Type::T_DATE,
          'pseudofield' => TRUE,
        ],
      ],
      'filters' => [
        'ip_fails_date' => [
          'name' => 'ip_fails_date',
          'title' => E::ts('IP has had rejects in this date range (min number based on ip failure threshold field'),
          'type' => CRM_Utils_Type::T_DATE,
          'pseudofield' => TRUE,
        ],
        'ip_fails_threshold' => [
          'name' => 'ip_fails_threshold',
          'title' => E::ts('IP failure threshold (in conjunction with fail date range'),
          'type' => CRM_Utils_Type::T_INT,

          'pseudofield' => TRUE,
          'default' => 1,
        ],
      ],
      'order_bys' => [
        'ip_fails_count' => [
          'name' => 'ip_fails_count',
          'title' => E::ts('IP rejects in the specified date range'),
          'type' => CRM_Utils_Type::T_DATE,
          'pseudofield' => TRUE,
        ],
      ],
    ];

    $this->_columns['email_failure_stats'] = [
      'fields' => [
        'email_fails_count' => [
          'name' => 'email_fails_count',
          'title' => E::ts('Email rejects in the specified date range'),
          'type' => CRM_Utils_Type::T_DATE,
        ],
      ],
      'filters' => [
        'email_fails_date' => [
          'name' => 'email_fails_date',
          'title' => E::ts('Email has had more than one failure in this date range'),
          'type' => CRM_Utils_Type::T_DATE,
          'pseudofield' => TRUE,
        ],
        'email_fails_threshold' => [
          'name' => 'email_fails_threshold',
          'title' => E::ts('Email failure threshold (in conjunction with fail date range'),
          'type' => CRM_Utils_Type::T_INT,
          'pseudofield' => TRUE,
          'default' => 1,
        ],
      ],
      'order_bys' => [
        'email_fails_count' => [
          'name' => 'email_fails_count',
          'title' => E::ts('Email rejects in the specified date range'),
          'type' => CRM_Utils_Type::T_DATE,
        ],
      ],
    ];

    $this->_columns['civicrm_email'] = [
      'dao' => 'CRM_Core_DAO_Email',
      'fields' => [
        'email' => [
          'title' => E::ts('Email'),
          'required' => TRUE,
          'default' => TRUE,
          'no_repeat' => TRUE,
        ],
      ],
      'filters' => [
        'email' => [
          'title' => E::ts('Email'),
          'operator' => 'like',
        ],
      ],
    ];

    $this->_columns['civicrm_contribution'] = [
      'dao' => 'CRM_Contribute_BAO_Contribution',
      'fields' => [
        'contribution_id' => [
          'required' => FALSE,
          'title' => E::ts('Contribution ID'),
          'name' => 'id',
        ],
        'receive_date' => [
          'name' => 'receive_date',
          'title' => E::ts('Receive Date'),
          'type' => CRM_Utils_Type::T_DATE + CRM_Utils_Type::T_TIME,
          'default' => TRUE,
        ],
        'total_amount' => [
          'name' => 'total_amount',
          'title' => E::ts('Total Amount'),
          'type' => CRM_Utils_Type::T_MONEY,
          'default' => TRUE,
        ],
        'contribution_status_id' => [
          'title' => ts('Contribution Status'),
          'default' => 1,
        ],
      ],
      'filters' => [
        'receive_date' => [
          'name' => 'receive_date',
          'title' => E::ts('Receive Date'),
          'type' => CRM_Utils_Type::T_DATE + CRM_Utils_Type::T_TIME,
          'operatorType' => CRM_Report_Form::OP_DATE,
        ],
        'contribution_status_id' => [
          'name' => 'contribution_status_id',
          'title' => E::ts('Contribution Status'),
          'operatorType' => CRM_Report_Form::OP_MULTISELECT,
          'options' => CRM_Contribute_PseudoConstant::contributionStatus(),
          'type' => CRM_Utils_Type::T_INT,
        ],
        'total_amount' => [
          'name' => 'total_amount',
          'title' => E::ts('Total amount'),
          'type' => CRM_Utils_Type::T_MONEY,
        ],
      ],
      'order_bys' => [
        'receive_date' => [
          'name' => 'receive_date',
          'title' => E::ts('Receive Date'),
          'type' => CRM_Utils_Type::T_DATE + CRM_Utils_Type::T_TIME,
          'operatorType' => CRM_Report_Form::OP_DATE,
        ],
        'total_amount' => [
          'name' => 'total_amount',
          'title' => E::ts('Total amount'),
          'type' => CRM_Utils_Type::T_MONEY,
        ],
      ],
    ];

    $this->_columns['contribution_tracking'] = [
      'fields' => [
        'contribution_tracking_id' => [
          'title' => E::ts('Contribution Tracking ID'),
          'name' => 'id',
        ],
        'utm_source' => [
          'title' => E::ts('UTM Source'),
        ],
        'utm_medium' => [
          'title' => E::ts('UTM Medium'),
        ],
        'utm_campaign' => [
          'title' => E::ts('UTM Campaign'),
        ],
      ],
      'filters' => [
        'utm_source' => [
          'title' => E::ts('UTM Source'),
          'type' => CRM_Utils_Type::T_STRING,
        ],
        'utm_medium' => [
          'title' => E::ts('UTM Medium'),
          'type' => CRM_Utils_Type::T_STRING,
        ],
        'utm_campaign' => [
          'title' => E::ts('UTM Campaign'),
          'type' => CRM_Utils_Type::T_STRING,
        ],
      ],
    ];

    parent::__construct();
  }

  /**
   * Build select clause for a single field.
   *
   * @param string $tableName
   * @param string $tableKey
   * @param string $fieldName
   * @param string $field
   *
   * @return bool
   */
  public function selectClause(&$tableName, $tableKey, &$fieldName, &$field) {
    foreach (['ip_fails', 'email_fails'] as $specialFilterFieldName) {
      if ($fieldName === $specialFilterFieldName . '_count') {
        if ($this->isFilteredByDateField('ip_fails_date') || $this->isFilteredByDateField('email_fails_date')) {
          // We can include this field because we have a date range - either the filter on that type of fail or
          // we can fall back to the other. ie. if reporter has said to return email_fails but has only specified a range
          // for ip_fails it seems reasonable & moderately intuitive to use that for both.
          return FALSE;
        }
        CRM_Core_Session::setStatus(E::ts('Cannot show %1 without a date range',
          [$specialFilterFieldName]));
        return 1;
      }
    }
    return FALSE;
  }

  /**
   * Generate where clause.
   *
   * This can be overridden in reports for special treatment of a field
   *
   * @param array $field Field specifications
   * @param string $op Query operator (not an exact match to sql)
   * @param mixed $value
   * @param float $min
   * @param float $max
   *
   * @return null|string
   */
  public function whereClause(&$field, $op, $value, $min, $max) {
    if ($field['name'] === 'user_ip' && stristr($value, '.')) {
      $value = ip2long($value);
    }
    return parent::whereClause($field, $op, $value, $min, $max);
  }

  /**
   * Has a filter on ip failures been applied.
   *
   * @return bool
   */
  protected function isFilteredByIPFails() {
    $fieldName = 'ip_fails_date';
    return $this->isFilteredByDateField($fieldName);
  }

  /**
   * Has a filter on email failures been applied.
   *
   * @return bool
   */
  protected function isFilteredByEmailFails() {
    $fieldName = 'email_fails_date';
    return $this->isFilteredByDateField($fieldName);
  }

  /**
   * @param $fieldName
   *
   * @return bool
   */
  protected function isFilteredByDateField($fieldName) {
    return (CRM_Utils_Array::value("{$fieldName}_relative", $this->_params))
      || CRM_Utils_Array::value("{$fieldName}_from", $this->_params)
      || CRM_Utils_Array::value("{$fieldName}_to", $this->_params);
  }

  /**
   * Alter display of rows.
   *
   * Iterate through the rows retrieved via SQL and make changes for display purposes,
   * such as rendering contacts as links.
   *
   * @param array $rows
   *   Rows generated by SQL, with an array for each row.
   */
  public function alterDisplay(&$rows) {
    $alterFields = [
      'civicrm_contact_sort_name' => 'alterLinkToContact',
      'payments_fraud_user_ip' => 'alterlinkToDetail',
      'civicrm_email_email' => 'alterlinkToDetail',
      'civicrm_contribution_contribution_status_id' => 'alterContributionStatus',
    ];
    foreach ($rows as $rowNum => &$row) {
      if (!empty($this->_noRepeats) && $this->_outputMode != 'csv') {
        $alterables = array_intersect_key($alterFields, $row);
        if (empty($alterables)) {
          // early return if nothing to do here.
          return;
        }
        foreach ($alterables as $field => $function) {
          if (!empty($row[$field])) {
            $this->$function($row[$field], $row, $field);
          }
        }

      }
    }
  }

  protected function alterContributionStatus($value, &$row, $selectedField) {
    $row[$selectedField] = CRM_Core_PseudoConstant::getLabel('CRM_Contribute_BAO_Contribution',
      'contribution_status_id', $value);
  }

  /**
   * @param $value
   * @param $row
   * @param $selectedField
   *
   * @return mixed
   */
  protected function alterLinkToContact($value, &$row, $selectedField) {
    // There is a js issue on putting in a popup.
    $row[$selectedField . '_link'] = CRM_Utils_System::url('civicrm/contact/view',
      [
        'reset' => 1,
        'cid' => $row['civicrm_contact_id'],
        'force' => 1,
        'selectedChild' => 'contribute'
      ]
    );

    $row[$selectedField . '_hover'] = ts('Show contact');
    return $value;
  }

  protected function alterLinkToDetail($value, &$row, $selectedField) {
    // field name looks like payments_fraud_user_ip but for the url we just use
    // user IP. This is a bit quick & dirty. Better would be to cycle through & figure
    // out which table name to strip.
    $urlField = str_replace(['payments_fraud_', 'civicrm_email_'], '',
      $selectedField);
    if ($urlField === 'user_ip') {
      $value = ip2long($value);
    }
    $urlParams = ['reset' => 1, 'force' => 1, 'section' => 2];
    $urlParams[$urlField . '_op'] = 'eq';
    $urlParams[$urlField . '_value'] = $value;
    $row[$selectedField . '_link'] = CRM_Utils_System::url('civicrm/report/wmffraud/paymentattempts',
      $urlParams
    );
    $row[$selectedField . '_hover'] = ts('Show Others');
    $row[$selectedField . '_class'] = "action-item crm-hover-button crm-popup";
    return $value;
  }

  /**
   * Get operators to display on form.
   *
   * We override this because our thresholds cannot be a range or greater than or less than.
   *
   * @param string $type
   * @param string $fieldName
   *
   * @return array
   */
  public function getOperationPair($type = "string", $fieldName = NULL) {
    if ($fieldName === 'ip_fails_threshold' || $fieldName === 'email_fails_threshold') {
      return ['eq' => ts('Is equal to'),];
    }

    // type is string
    if ($type == NULL) {
      $result = [
        'has' => ts('Contains'),
        'sw' => ts('Starts with'),
        'ew' => ts('Ends with'),
        'nhas' => ts('Does not contain'),
        'eq' => ts('Is equal to'),
        'neq' => ts('Is not equal to'),
        'nll' => ts('Is4 empty (Null)'),
        'nnll' => ts('Is not empty (Null)'),
        'in' => ts('Is one of') // add 'in' support for string filters
      ];
      return $result;
    }


    return parent::getOperationPair($type, $fieldName);
  }

  /**
   * Add join to ip failure calculation.
   */
  protected function addIpFailsJoin() {
    if ($this->isFilteredByIPFails()) {
      $join = 'INNER';
    }
    elseif ($this->isTableSelected('ip_fails')) {
      $join = 'LEFT';
    }
    else {
      return;
    }
    $threshold = (int) CRM_Utils_Array::value('ip_fails_threshold_value',
      $this->_params, 1);
    list($from, $to) = $this->getToFromForField('ip_fails_date');
    $this->_from .= " $join JOIN
      (
      SELECT user_ip, count(*) as ip_fails_count
      FROM {$this->fredge}.payments_fraud
      WHERE validation_action = 'reject'
        AND `date` BETWEEN '{$from}' AND '{$to}'
      GROUP BY user_ip
      HAVING ip_fails_count > $threshold
    ) as {$this->_aliases['ip_failure_stats']}
    ON {$this->_aliases['ip_failure_stats']}.user_ip = {$this->_aliases['payments_fraud']}.user_ip ";

  }

  /**
   * Add join to email failure calculation.
   *
   * If this is part of our filter we get the to & from from the filter.
   * If not & there is a filter on ip fails we can fall back on that filter.
   *
   * Including this table without a date filter is probably too expensive.
   */
  protected function addEmailFailsJoin() {
    $to = $from = NULL;
    if ($this->isFilteredByEmailFails()) {
      $join = 'INNER';
      list($from, $to) = $this->getToFromForField('email_fails_date');
    }
    elseif ($this->isTableSelected('email_failure_stats')) {
      $join = 'LEFT';
      list($from, $to) = $this->getToFromForField('email_fails_date');
      if (!$from || !$to) {
        list($from, $to) = $this->getToFromForField('ip_fails_date');
      }
    }
    if (!$to || !$from) {
      return;
    }

    $threshold = (int) CRM_Utils_Array::value('email_fails_threshold_value',
      $this->_params, 1);

    $this->_from .= " $join JOIN
      (
      SELECT email, count(*) as email_fails_count
      FROM {$this->fredge}.payments_fraud pf
      INNER JOIN {$this->drupal}.contribution_tracking dt ON dt.id = pf.contribution_tracking_id
      INNER JOIN civicrm_contribution c on dt.contribution_id = c.id
      INNER JOIN civicrm_email email ON email.contact_id = c.contact_id AND email IS NOT NULL
      WHERE validation_action = 'reject'
         AND `date` BETWEEN '{$from}' AND '{$to}'
      GROUP BY email
      HAVING email_fails_count > $threshold
    ) as {$this->_aliases['email_failure_stats']}
    ON {$this->_aliases['email_failure_stats']}.email = {$this->_aliases['civicrm_email']}.email";

  }

  protected function addJoinToContactAndEmail() {
    $this->_from .= "
      LEFT JOIN civicrm_contact {$this->_aliases['civicrm_contact']}
        ON {$this->_aliases['civicrm_contact']}.id = {$this->_aliases['civicrm_contribution']}.contact_id
      LEFT JOIN civicrm_email {$this->_aliases['civicrm_email']}
        ON {$this->_aliases['civicrm_contact']}.id = {$this->_aliases['civicrm_email']}.contact_id
    ";
  }

  protected function addJoinToPaymentsFraudBreakdown() {
    if ($this->isTableSelected('payments_fraud_breakdown')) {
      $this->_from .= "
      LEFT JOIN {$this->fredge}.payments_fraud_breakdown {$this->_aliases['payments_fraud_breakdown']}
        ON {$this->_aliases['payments_fraud']}.id = {$this->_aliases['payments_fraud_breakdown']}.payments_fraud_id
    ";
    }
  }

  /**
   * Store group bys into array.
   */
  protected function storeGroupByArray() {
    parent::storeGroupByArray();
    if ($this->isTableSelected('payments_fraud_breakdown') && empty($this->_groupByArray)) {
      $this->_groupByArray['payments_fraud_id'] = 'payments_fraud_civireport.id';
    }
  }

  /**
   * @param $fieldName
   *
   * @return array
   */
  protected function getToFromForField($fieldName) {
    list($from, $to)
      = $this->getFromTo(
      CRM_Utils_Array::value("{$fieldName}_relative", $this->_params),
      CRM_Utils_Array::value("{$fieldName}_from", $this->_params),
      CRM_Utils_Array::value("{$fieldName}_to", $this->_params),
      CRM_Utils_Array::value("{$fieldName}_from_time", $this->_params),
      CRM_Utils_Array::value("{$fieldName}_to_time", $this->_params)
    );
    return [$from, $to];
  }
}
