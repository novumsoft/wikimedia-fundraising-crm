<?php
// This file declares a managed database record of type "ReportTemplate".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate. For more details, see "hook_civicrm_managed" at:
// http://wiki.civicrm.org/confluence/display/CRMDOC42/Hook+Reference
return array (
  0 =>
  array (
    'name' => 'CRM_Wmffraud_Form_Report_PaymentAttempts',
    'entity' => 'ReportTemplate',
    'params' =>
    array (
      'version' => 3,
      'label' => 'Payment Attempts',
      'description' => 'Payment Attempts (org.wikimedia.wmffraud)',
      'class_name' => 'CRM_Wmffraud_Form_Report_PaymentAttempts',
      'report_url' => 'wmffraud/paymentattempts',
      'component' => 'CiviContribute',
    ),
  ),
);
