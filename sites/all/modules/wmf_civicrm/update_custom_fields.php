<?php

/**
 * Create WMF specific custom fields.
 *
 * @throws \CiviCRM_API3_Exception
 */
function _wmf_civicrm_update_custom_fields() {
  civicrm_initialize();
  $customGroupSpecs = [
    'wmf_donor' => [
      'group' => [
        'extends' => 'Contact',
        'name' => 'wmf_donor',
        'table_name' => 'wmf_donor',
        'title' => ts('WMF Donor'),
        'is_active' => 1,
        'style' => 'inline',
      ],
      'fields' => _wmf_civicrm_get_wmf_donor_fields(),
    ],
    'contribution_extra' => [
        'group' => [
        'extends' => 'Contribution',
        'name' => 'contribution_extra',
        'table_name' => 'wmf_contribution_extra',
        'title' => ts('Contribution Extra'),
        'is_active' => 1,
      ],
      'fields' => _wmf_civicrm_get_wmf_contribution_extra_fields(),
    ],
    'Communication' => [
      'group' => [
        'name' => 'Communication',
        'extends' => 'Contact',
        'style' => 'Inline',
        'collapse_display' => 0,
        'title' => 'Communication',
        'table_name' => 'civicrm_value_1_communication_4',
      ],
      'fields' => _wmf_civicrm_get_communication_fields(),
    ],
    'Gift_Data' => [
      'group' => [
        'name' => 'Gift_Data',
        'title' => ts('Gift Data'),
        'extends' => 'Contribution',
        'style' => 'inline',
        'is_active' => 1,
      ],
      'fields' => _wmf_civicrm_get_gift_data_fields(),
    ],
    'Prospect' => [
      'group' => [
        'name' => 'Prospect',
        'title' => 'Prospect',
        'extends' => 'Contact',
        'style' => 'tab',
        'is_active' => 1,
        'table_name' => 'civicrm_value_1_prospect_5',
      ],
      'fields' => _wmf_civicrm_get_prospect_fields(),
    ],
    'Anonymous' => [
      'group' => [
        'name' => 'Anonymous',
        'title' => 'Benefactor Page Listing',
        'extends' => 'Contact',
        'style' => 'Inline',
        'is_active' => 1,
      ],
      'fields' => _wmf_civicrm_get_benefactor_fields(),
    ],
    'Partner' => [
      'group' => [
        'name' => 'Partner',
        'title' => 'Partner',
        'extends' => 'Contact',
        'style' => 'Inline',
        'is_active' => 1,
        // Setting weight here is a bit hit & miss but one day the api
        // will do the right thing...
        'weight' => 1,
      ],
      'fields' => _wmf_civicrm_get_partner_fields(),
    ],
    'Stock_Information' => [
      'group' => [
        'name' => 'Stock_Information',
        'title' => 'Stock Information',
        'extends' => 'Contribution',
        'style' => 'Inline',
        'is_active' => 1,
        // Setting weight here is a bit hit & miss but one day the api
        // will do the right thing...
        'weight' => 1,
      ],
      'fields' => _wmf_civicrm_get_stock_fields(),
    ],
  ];
  foreach ($customGroupSpecs as $groupName => $customGroupSpec) {
    $customGroup = civicrm_api3('CustomGroup', 'get', ['name' => $groupName]);
    if (!$customGroup['count']) {
      $customGroup = civicrm_api3('CustomGroup', 'create', $customGroupSpec['group']);
    }
    // We mostly are trying to ensure a unique weight since weighting can be re-ordered in the UI but it gets messy
    // if they are all set to 1.
    $weight = CRM_Core_DAO::singleValueQuery('SELECT max(weight) FROM civicrm_custom_field WHERE custom_group_id = %1',
      [1 => [$customGroup['id'], 'Integer']]
    );

    foreach ($customGroupSpec['fields'] as $index => $field) {
      $existingField = civicrm_api3('CustomField', 'get', [
        'custom_group_id' => $customGroup['id'],
        'name' => $field['name'],
      ]);

      if ($existingField['count']) {
        if (isset($field['option_values'])) {
          // If we are on a developer site then sync up the option values. Don't do this on live
          // because we could get into trouble if we are not up-to-date with the options - which
          // we don't really aspire to be - or not enough to let this code run on prod.
          $env = civicrm_api3('Setting', 'getvalue', ['name' => 'environment']);
          if ($env === 'Development' && empty($existingField['option_group_id'])) {
            $field['id'] = $existingField['id'];
            // This is a hack because they made a change to the BAO to restrict editing
            // custom field options based on a form value - when they probably should
            // have made the change in the form. Without this existing fields don't
            // get option group updates. See https://issues.civicrm.org/jira/browse/CRM-16659 for
            // original sin.
            $field['option_type'] = 1;
            // When we next upgrade bulkCreate is renamed to bulkSave & handles updates too.
            // but in the meantime special-handle them.
            // Also this shouldn't really ever affect prod fields - or not at the moment.
            civicrm_api3('CustomField', 'create', $field);
          }
        }
        unset($customGroupSpec['fields'][$index]);
      }
      else {
        $weight++;
        $customGroupSpec['fields'][$index]['weight'] = $weight;
      }
    }
    if ($customGroupSpec['fields']) {
      // We created the bulkCreate function in core to help us & ported it. But, in the final
      // version merged to core it was renamed to bulkSave & adapted to support update as well.
      // Next upgrade of Civi we'll need to adjust here & a few lines above we can save some lines.
      CRM_Core_BAO_CustomField::bulkSave($customGroupSpec['fields'], ['custom_group_id' => $customGroup['id']]);
    }
  }
  civicrm_api3('System', 'flush', ['triggers' => 0, 'session' => 0]);
}

/**
 * Get fields from prospect custom group.
 *
 * @return array
 */
function _wmf_civicrm_get_benefactor_fields() {
  return [
    'Listed_as_Anonymous' => [
      'name' => 'Listed_as_Anonymous',
      'label' => 'Listed as',
      'data_type' => 'String',
      'html_type' => 'Select',
      'default_value' => 'not_replied',
      'is_searchable' => 1,
      'text_length' => 255,
      'note_columns' => 60,
      'note_rows' => 4,
      'option_values' => [
        'anonymous' => 'Anonymous',
        'not_replied' => 'Not replied',
        'public' => 'Public',
      ],
    ],
    'Listed_on_Benefactor_Page_as' => [
      'name' => 'Listed_on_Benefactor_Page_as',
      'label' => 'Listed on Benefactor Page as',
      'data_type' => 'String',
      'html_type' => 'Text',
      'is_searchable' => 1,
      'text_length' => 255,
      'note_columns' => 60,
      'note_rows' => 4,
    ],
  ];
}

/**
 * Get fields for gift data custom group.
 *
 * @return array
 */
function _wmf_civicrm_get_gift_data_fields() {
  return [
    'Fund' => [
      'name' => 'Fund',
      'column_name' => 'fund',
      'label' => ts('Restrictions'),
      'data_type' => 'String',
      'html_type' => 'Select',
      'default_value' => 'Unrestricted - General',
      'is_active' => 1,
      'is_required' => 1,
      'is_searchable' => 1,
    ],
    'Campaign' => [
      'name' => 'Campaign',
      'column_name' => 'campaign',
      'label' => ts('Gift Source'),
      'data_type' => 'String',
      'html_type' => 'Select',
      'default_value' => 'Community Gift',
      'is_active' => 1,
      'is_required' => 1,
      'is_searchable' => 1,
    ],
    'Appeal' => [
      'name' => 'Appeal',
      'column_name' => 'appeal',
      'label' => ts('Direct Mail Appeal'),
      'data_type' => 'String',
      'html_type' => 'Select',
      'default_value' => 'spontaneousdonation',
      'is_active' => 1,
      'is_required' => 1,
      'is_searchable' => 1,
    ],
  ];
}

/**
 * Get fields from prospect custom group.
 *
 * @return array
 */
function _wmf_civicrm_get_prospect_fields() {
  return [
    'Origin' => [
      'name' => 'Origin',
      'label' => 'Origin',
      'data_type' => 'String',
      'html_type' => 'Select',
      'help_post' => 'How do we know about the prospect?',
      'text_length' => 255,
      'note_columns' => 60,
      'note_rows' => 4,
      //"option_group_id":"65",
    ],
    'Steward' => [
      'name' => 'Steward',
      'label' => 'Steward',
      'data_type' => 'String',
      'html_type' => 'Select',
      'is_searchable' => 1,
      'default_value' => 5,
      'note_columns' => 60,
      'note_rows' => 4,
      //"option_group_id":"44",
    ],
    'Solicitor' => [
      'name' => 'Solicitor',
      'label' => 'Solicitor',
      'data_type' => 'String',
      'html_type' => 'Select',
      'is_searchable' => 1,
      'note_columns' => 60,
      'note_rows' => 4,
      //"option_group_id":"45",
    ],
    'Biography' => [
      'name' => 'Biography',
      'label' => 'Biography',
      "data_type" => 'Memo',
      'html_type' => 'RichTextEditor',
      'is_searchable' => 1,
      'help_post' => 'Who they are, what they do.',
      'attributes' => 'rows=4, cols=60',
      'text_length' => 255,
      'note_columns' => 60,
      'note_rows' => 4,
    ],
    'Estimated_Net_Worth' => [
      'name' => 'Estimated_Net_Worth',
      'label' => 'Estimated Net Worth',
      'column_name' => 'estimated_net_worth_144',
      'data_type' => 'String',
      'html_type' => 'Select',
      'text_length' => 255,
      'note_columns' => 60,
      'note_rows' => 4,
      'option_values' => [
        1 => '$20 Million +',
        2 => '$10 Million - $19.99 Million',
        3 => '$5 Million - $9.99 Million',
        4 => '$2 Million - $4.99 Million',
        5 => '$1 Million - $1.99 Million',
        6 => '$500,000 - $999,999',
        7 => '>$5B',
        8 => '>$1B',
        9 => '>$10B',
        10 => '$100 Million +',
        'A' => 'Below $25,000',
        'B' => '$25,000 - $49,999',
        'C' => '$50,000 - $74,999',
        'D' => '$75,000 - $99,999',
        'E' => '$150,000 - $199,999',
        'F' => '$150,000 - $199,999',
        'G' => '$200,000 - $249,999',
        'H' => '$250,000 - $499,999',
        'I' => '$500,000 - $749,999',
        'J' => '$750,000 - $999,999',
        'K' => '$1,000,000 - $2,499,999',
        'L' => '$2,500,000 - $4,999,999',
        'M' => '$5,000,000 - $9,999,999',
        'N' => 'Above $10,000,000',
      ],
    ],
    'Philanthropic_History' => [
      'name' => 'Philanthropic_History',
      'label' => 'Philanthropic History',
      'data_type' => 'Memo',
      'html_type' => 'RichTextEditor',
      'is_searchable' => 1,
      'attributes' => 'rows=4, cols=60',
      'text_length' => 255,
      'note_columns' => 60,
      'note_rows' => 4,
    ],
    'Philanthropic_Interests' => [
      'name' => 'Philanthropic_Interests',
      'label' => 'Philanthropic Interests',
      'data_type' => 'String',
      'html_type' => 'Select',
      'is_searchable' => 1,
      'note_columns' => 60,
      'note_rows' => 4,
      'option_values' => [
        1 => 'Technology',
        2 => 'Education',
        3 => 'Political Campaign',
        4 => 'Cultural Arts',
        5 => 'Poverty',
        6 => 'Environment',
      ],
    ],
    'Subject_Area_Interest' => [
      'name' => 'Subject_Area_Interest',
      'label' => 'Subject Area Interest',
      'data_type' => 'String',
      'html_type' => 'Multi-Select',
      'is_searchable' => 1,
      'help_post' => 'Subject Area, Interests',
      'text_length' => 255,
      'note_columns' => 60,
      'note_rows' => 4,
      //"option_group_id":"115",
    ],
    'Interests' => [
      'name' => 'Interests',
      'label' => 'Interests',
      'data_type' => 'Memo',
      'html_type' => 'RichTextEditor',
      'is_searchable' => 1,
      'help_post' => 'Pet projects, hobbies, other conversation worthy interests',
      'attributes' => 'rows=4, cols=60',
      'text_length' => 255,
      'note_columns' => 60,
      'note_rows' => 4,
    ],
    'University_Affiliation' => [
      'name' => 'University_Affiliation',
      'label' => 'University Affiliation',
      'data_type' => 'String',
      'html_type' => 'Multi-Select',
      'is_searchable' => 1,
      'help_post' => 'University Attended, Taught At, Employed By',
      'text_length' => 255,
      'note_columns' => 60,
      'note_rows' => 4,
      'option_values' => [
        'Stanford' => 'Stanford',
        'CAL - Berkeley' => 'CAL__Berkeley',
        'MIT' => 'MIT',
        'Carnegie Mellon' => 'Carnegie Mellon',
        'Harvard' => 'Harvard',
        1 => 'University of Chicago',
        2 => 'Yale',
      ],
    ],
    'Board_Affiliations' => [
      'name' => 'Board_Affiliations',
      'label' => 'Board Affiliations',
      'data_type' => 'Memo',
      'html_type' => 'RichTextEditor',
      'is_searchable' => 1,
      'note_columns' => 60,
      'note_rows' => 4,
    ],
    'Notes' => [
      'name' => 'Notes',
      'label' => 'Notes',
      'data_type' => 'Memo',
      'html_type' => 'RichTextEditor',
      'is_searchable' => 1,
      'note_columns' => 60,
      'note_rows' => 4,
    ],
    'Stage' => [
      'name' => 'Stage',
      'label' => 'MG Stage',
      'data_type' => 'String',
      'html_type' => 'Select',
      'is_searchable' => 1,
      'note_columns' => 60,
      'note_rows' => 4,
      // "option_group_id":"32",
    ],
    'Endowment_Stage' => [
      'name' => 'Endowment_Stage',
      'label' => 'Endowment Stage',
      'data_type' => 'String',
      'html_type' => 'Select',
      'is_searchable' => 1,
      'note_columns' => 60,
      'note_rows' => 4,
    ],
    'On Hold' => [
      'name' => 'On_Hold',
      'label' => 'On Hold',
      'data_type' => 'Boolean',
      'html_type' => 'Radio',
      'default_value' => 0,
      'is_searchable' => 1,
      'help_post' => 'Prospects archived from being stewarded',
      'text_length' => 255,
      'note_columns' => 60,
      'note_rows' => 4,
    ],
    'Affinity' => [
      'name' => 'Affinity',
      'label' => 'Affinity',
      'data_type' => 'String',
      'html_type' => 'Select',
      'is_searchable' => 1,
      'default_value' => 'Unknown',
      'note_columns' => 60,
      'note_rows' => 4,
      'option_values' => [
        1 => '(0) Neutral ',
        2 => '(+) Positive',
        3 => '(-) Negative',
        'On Hold' => 'On Hold',
        'Unknown' => 'Unknown',
      ],
    ],

    'Capacity' => [
      'name' => 'Capacity',
      'label' => 'Capacity',
      'data_type' => 'String',
      'html_type' => 'Select',
      'is_searchable' => 1,
      'help_post' => 'Low = <$5k\r\nMedium = $5k to $99,999\r\nHigh = >$100k and over',
      'note_columns' => 60,
      'note_rows' => 4,
      'option_values' => [
        1 => '$0 - $999',
        2 => '$1,000 - $4,999',
        3 => '$5,000 - $9,999',
        4 => '$10,000 - $49,000',
        5 => '$50,000 - $99,999',
        6 => '$100,000 - $250,000',
        7 => '$250,000 to $500,000',
        8 => '$500,000 +',
        'Connector' => 'Connector ',
        'Low' => 'Low',
        'Medium' => 'Medium',
        'High  ' => 'High',
      ],
    ],
    'Reviewed' => [
      'name' => 'Reviewed',
      'label' => 'Reviewed',
      'data_type' => 'Date',
      'html_type' => 'Select Date',
      'is_searchable' => 1,
      'is_search_range' => 1,
      'start_date_years' => 10,
      'end_date_years' => 1,
      'date_format' => 'mm/dd/yy',
      'note_columns' => 60,
      'note_rows' => 4,
    ],
    'Income_Range' => [
      'name' => 'Income_Range',
      'label' => 'Income Range',
      'column_name' => 'income_range',
      'data_type' => 'String',
      'html_type' => 'Select',
      'is_searchable' => 1,
      'text_length' => 255,
      'note_columns' => 60,
      'note_rows' => 4,
      'option_values' => [
        'a' => 'Below $30,000',
        'b ' => '$30,000 - $39,999',
        'c' => '$40,000 - $49,999',
        'd' => '$50,000 - $59,999',
        'e' => '$60,000 - $74,999',
        'f' => '$75,000 - $99,999',
        'g' => '$100,000 - $124,999',
        'h' => '$125,000 - $149,999',
        'i' => '$150,000 - $199,999',
        'j' => '$200,000 - $249,999',
        'k' => '$250,000 - $299,999',
        'l' => '$300,000 - $499,999',
        'm' => 'Above $500,000',
      ],
    ],
    'Charitable_Contributions_Decile' => [
      'name' => 'Charitable_Contributions_Decile',
      'column_name' => 'charitable_contributions_decile',
      'label' => 'Charitable Contributions Decile',
      'data_type' => 'String',
      'html_type' => 'Select',
      'is_searchable' => 1,
      'text_length' => 255,
      'note_columns' => 60,
      'note_rows' => 4,
      'option_values' => [
        '1' => '1',
        '2' => '2',
        '3' => '3',
        '4' => '4',
        '5 ' => '5',
        '6' => '6',
        '7' => '7',
        '8' => '8',
        '9' => '9',
        '10' => '10',
        '11' => '11',
      ],
    ],
    'Disc_Income_Decile' => [
      'name' => 'Disc_Income_Decile',
      'label' => 'Disc Income Decile',
      'column_name' => 'disc_income_decile',
      'data_type' => 'String',
      'html_type' => 'Select',
      'is_searchable' => 1,
      'text_length' => 255,
      'note_columns' => 60,
      'note_rows' => 4,
      'option_values' => [
        '1' => '1',
        '2' => '2',
        '3' => '3',
        '4' => '4',
        '5' => '5',
        '6' => '6',
        '7' => '7',
        '8' => '8',
        '9' => '9',
        '10' => '10',
        '11' => '11',
      ],
    ],
    'Voter_Party' => [
      'name' => 'Voter_Party',
      'label' => 'Voter Party',
      'column_name' => 'voter_party',
      'data_type' => 'String',
      'html_type' => 'Select',
      'is_searchable' => 1,
      'text_length' => 255,
      'note_columns' => 60,
      'note_rows' => 4,
      'option_values' => [
        'democrat ' => 'Democrat',
        'republican' => 'Republican',
        'green' => 'Green',
        'independent' => 'Independent',
        'libertarian' => 'Libertarian',
        'no_party' => 'No Party',
        'other' => 'Other',
        'unaffiliated' => 'Unaffiliated',
        'unregistered' => 'Unregistered',
        'working_fam' => 'Working Fam',
        'conservative' => 'Conservative',
      ],
    ],
    'ask_amount' => [
      'name' => 'ask_amount',
      'label' => 'Ask Amount',
      'data_type' => 'Money',
      'html_type' => 'Text',
      'is_searchable' => 1,
      'is_search_range' => 1,
    ],
    'expected_amount' => [
      'name' => 'expected_amount',
      'label' => 'Expected Amount',
      'data_type' => 'Money',
      'html_type' => 'Text',
      'is_searchable' => 1,
      'is_search_range' => 1,
    ],
    'likelihood' => [
      'name' => 'likelihood',
      'label' => 'Likelihood (%)',
      'data_type' => 'Int',
      'html_type' => 'Text',
      'is_searchable' => 1,
      'is_search_range' => 1,
    ],
    'expected_close_date' => [
      'name' => 'expected_close_date',
      'label' => 'Expected Close Date',
      'data_type' => 'Date',
      'html_type' => 'Select Date',
      'is_searchable' => 1,
      'is_search_range' => 1,
    ],
    'close_date' => [
      'name' => 'close_date',
      'label' => 'Close Date',
      'data_type' => 'Date',
      'html_type' => 'Select Date',
      'is_searchable' => 1,
      'is_search_range' => 1,
    ],
    'next_step' => [
      'name' => 'next_step',
      'label' => 'Next Step',
      'data_type' => 'Memo',
      'html_type' => 'RichTextEditor',
      'note_columns' => 60,
      'note_rows' => 4,
    ],
    'PG_Stage' => [
      'name' => 'PG_Stage',
      'label' => 'PG Stage',
      'data_type' => 'String',
      'html_type' => 'Select',
      'note_columns' => 60,
      'note_rows' => 4,
      'is_searchable' => 1,
      'option_values' => [
        '1' => 'Cultivation',
        '2' => "Cont'd Cultivation",
      ],
    ],
    'Survey_Responses' => [
      'name' => 'Survey_Responses',
      'label' => 'Survey Responses',
      'data_type' => 'String',
      'html_type' => 'Text',
      'note_columns' => 60,
      'note_rows' => 4,
      'help_pre' => 'Data field to store any MGF survey related data for future reference.  Please date appropriately and do not overwrite previous responses.',
    ],
    'Family_Composition' => [
      'name' => 'Family_Composition',
      'label' => 'Family Composition',
      'data_type' => 'String',
      'html_type' => 'Select',
      'column_name' => 'family_composition_173',
      'is_searchable' => 1,
      'option_values' => [
        1  => 'Single',
        2 => 'Single with Children',
        3 => 'Couple',
        4 => 'Couple with children',
        5 => 'Multiple Generations',
        6 => 'Multiple Surnames (3+)',
        7 => 'Other',
      ],
    ],
    'Occupation' => [
      'name' => 'Occupation',
      'label' => 'Occupation',
      'column_name' => 'occupation_175',
      'data_type' => 'String',
      'html_type' => 'Select',
      'is_searchable' => 1,
      'option_values' => [
        1 => 'Professional/Technical',
        2 => 'Upper Management/Executive',
        3 => 'Sales/Service',
        4  => 'Office/Clerical',
        5 => 'Skilled Trade',
        6 => 'Retired',
        7 => 'Administrative/Management',
        8 => 'Self Employed',
        9 => 'Military',
        10 => 'Farming/Agriculture',
        11 => 'Medical/Health Services',
        12 => 'Financial Services',
        13 => 'Teacher/Educator',
        14 => 'Legal Services',
        15 => 'Religious',
      ],
    ],
  ];
}

/**
 * Get fields for partner custom group.
 *
 * @return array
 */
function _wmf_civicrm_get_partner_fields() {
  return [
    'Partner' => [
      'name' => 'Partner',
      'label' => 'Partner',
      'data_type' => 'String',
      'html_type' => 'Text',
      'is_searchable' => 1,
      'text_length' => 255,
      'note_columns' => 60,
      'note_rows' => 4,
    ],
  ];
}

/**
 * Get fields for _wmf_contribution_extra.
 *
 * @return array
 */
function _wmf_civicrm_get_wmf_contribution_extra_fields() {
  return [
    'settlement_date' => [
      'name' => 'settlement_date',
      'column_name' => 'settlement_date',
      'label' => ts('Settlement Date'),
      'data_type' => 'Date',
      'html_type' => 'Select Date',
      'is_active' => 1,
      'is_searchable' => 1,
      'is_search_range' => 1,
      'is_view' => 1,
      'date_format' => 'M d, yy',
      'time_format' => 2,
    ],
    'no_thank_you' => [
      'name' => 'no_thank_you',
      'column_name' => 'no_thank_you',
      'label' => ts('No Thank-you Reason'),
      'data_type' => 'String',
      'html_type' => 'Text',
      'is_active' => 1,
      'is_searchable' => 1,
      'is_view' => 0,
    ],
    'total_usd' => [
      'name' => 'total_usd',
      'column_name' => 'total_usd',
      'label' => ts('Total in USD (approx)'),
      'data_type' => 'Money',
      'html_type' => 'Text',
      'is_active' => 1,
      'is_searchable' => 1,
      'is_search_range' => 1,
      'is_view' => 1,
    ],
  ];
}

/**
 * Get fields for communication custom group.
 *
 * @return array
 */
function _wmf_civicrm_get_communication_fields() {
  return [
    'opt_in' => [
      'name' => 'opt_in',
      'column_name' => 'opt_in',
      'label' => ts('Opt In'),
      'data_type' => 'Boolean',
      'html_type' => 'Radio',
      'is_active' => 1,
      'is_searchable' => 0,
    ],
    'do_not_solicit' => [
      'name' => 'do_not_solicit',
      'column_name' => 'do_not_solicit',
      'label' => ts('Do not solicit'),
      'data_type' => 'Boolean',
      'html_type' => 'Radio',
      'is_active' => 1,
      'is_required' => 0,
      'is_searchable' => 1,
      'default_value' => 0,
    ],
    'Employer_Name' => [
      'name' => 'Employer_Name',
      'label' => ts('Employer Name'),
      'data_type' => 'String',
      'html_type' => 'Text',
      'is_active' => 1,
      'is_searchable' => 1,
    ],
    'optin_source' => [
      'name' => 'optin_source',
      'label' => ts('Opt-in Source'),
      'data_type' => 'String',
      'html_type' => 'Text',
      'is_active' => 1,
      'is_searchable' => 1,
    ],
    'optin_medium' => [
      'name' => 'optin_medium',
      'label' => ts('Opt-in Medium'),
      'data_type' => 'String',
      'html_type' => 'Text',
      'is_active' => 1,
      'is_searchable' => 1,
    ],
    'optin_campaign' => [
      'name' => 'optin_campaign',
      'label' => ts('Opt-in Campaign'),
      'data_type' => 'String',
      'html_type' => 'Text',
      'is_active' => 1,
      'is_searchable' => 1,
    ],
  ];
}

/**
 * Get the legacy wmf donor fields we want to remove.
 *
 * @return array
 */
function _wmf_civicrm_get_wmf_donor_fields_to_remove() {
  $fields = [];
  for ($year = WMF_MIN_ROLLUP_YEAR; $year <= WMF_MAX_ROLLUP_YEAR; $year++) {
    $fields["is_{$year}_donor"] = "is_{$year}_donor";
  }
  $fields['do_not_solicit_old'] = 'do_not_solicit_old';
  return $fields;
}

/**
 * Get fields for stock info
 *
 * @return array[]
 */
function _wmf_civicrm_get_stock_fields() {
  return [
    'description_of_stock' => [
      'name' => 'Description_of_Stock',
      'column_name' => 'description_of_stock',
      'label' => ts('Description of Stock'),
      'data_type' => 'String',
      'html_type' => 'Text',
      'is_active' => 1,
      'is_searchable' => 1,
      'is_view' => 0,
    ],
    'stock_value' => [
      'name' => 'Stock Value',
      'column_name' => 'stock_value',
      'label' => ts('Stock Value'),
      'data_type' => 'Money',
      'html_type' => 'Text',
      'is_active' => 1,
      'is_searchable' => 1,
      'is_view' => 0,
    ]
  ];
}
