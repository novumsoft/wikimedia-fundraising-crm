<?php

/**
 * Create WMF specific custom fields.
 */
function _wmf_civicrm_update_custom_fields() {
  civicrm_initialize();
  $customGroupSpecs = [
    'Prospect' => [
      'group' => [
        'name' => 'Prospect',
        'title' => 'Prospect',
        'extends' => 'Contact',
        'style' => 'tab',
        'is_active' => 1,
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
  ];
  foreach ($customGroupSpecs as $groupName => $customGroupSpec) {
    $customGroup = civicrm_api3('CustomGroup', 'get', array('name' => $groupName));
    if (!$customGroup['count']) {
      $customGroup = civicrm_api3('CustomGroup', 'create', $customGroupSpec['group']);
    }
    // We mostly are trying to ensure a unique weight since weighting can be re-ordered in the UI but it gets messy
    // if they are all set to 1.
    $weight = CRM_Core_DAO::singleValueQuery('SELECT max(weight) FROM civicrm_custom_field WHERE custom_group_id = %1',
      array(1 => array($customGroup['id'], 'Integer'))
    );

    foreach ($customGroupSpec['fields'] as $field) {
      if (!civicrm_api3('CustomField', 'getcount', array(
        'custom_group_id' => $customGroup['id'],
        'name' => $field['name'],
      ))
      ) {
        $weight++;
        civicrm_api3('CustomField', 'create', array_merge(
          $field,
          array(
            'custom_group_id' => $customGroup['id'],
            'weight' => $weight,
          )
        ));
      }
    }
  }
}

/**
 * Get fields from prospect custom group.
 *
 * @return array
 */
function _wmf_civicrm_get_benefactor_fields() {
  return array(
    'Listed_as_Anonymous' => array(
      'name' => 'Listed_as_Anonymous',
      'label' => 'Listed as',
      'data_type' => 'String',
      'html_type' => 'Select',
      'default_value' => 'not_replied',
      'is_searchable' => 1,
      'text_length' => 255,
      'note_columns' => 60,
      'note_rows' => 4,
      'option_values' => array(
        'anonymous' => 'Anonymous',
        'not_replied' => 'Not replied',
        'public' => 'Public',
      ),
    ),
    'Listed_on_Benefactor_Page_as' => array(
      'name' => 'Listed_on_Benefactor_Page_as',
      'label' => 'Listed on Benefactor Page as',
      'data_type' => 'String',
      'html_type' => 'Text',
      'is_searchable' => 1,
      'text_length' => 255,
      'note_columns' => 60,
      'note_rows' => 4,
    ),
  );
}

/**
 * Get fields from prospect custom group.
 *
 * @return array
 */
function _wmf_civicrm_get_prospect_fields() {
  return array(
    'Origin' => array(
      'name' => 'Origin',
      'label' => 'Origin',
      'data_type' => 'String',
      'html_type' => 'Select',
      'help_post' => 'How do we know about the prospect?',
      'text_length' => 255,
      'note_columns' => 60,
      'note_rows' => 4,
      //"option_group_id":"65",
    ),
    'Steward' => array(
      'name'=> 'Steward',
      'label' => 'Steward',
      'data_type' => 'String',
      'html_type' => 'Select',
      'is_searchable' => 1,
      'default_value' => 5,
      'note_columns' => 60,
      'note_rows' => 4,
      //"option_group_id":"44",
    ),
    'Solicitor' => array(
      'name' => 'Solicitor',
      'label' => 'Solicitor',
      'data_type' => 'String',
      'html_type' => 'Select',
      'is_searchable' => 1,
      'note_columns' => 60,
      'note_rows' => 4,
      //"option_group_id":"45",
    ),
    'Prior_WMF_Giving' => array(
      'name' => 'Prior_WMF_Giving',
      'label' => 'Prior WMF Giving',
      'data_type' => 'Boolean',
      'html_type' => 'Radio',
      'text_length' => 255,
      'note_columns' => 60,
      'note_rows' => 4,
    ),
    'Biography' => array(
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
    ),
    'Estimated_Net_Worth' => array(
      'name' => 'Estimated_Net_Worth',
      'label' => 'Estimated Net Worth',
      'data_type' => 'String',
      'html_type' => 'Select',
      'text_length' => 255,
      'note_columns' => 60,
      'note_rows' => 4,
      'option_values' => array(
        1 => '$20 Million +',
        2 => '$10 Million - $19.99 Million',
        3 => '$5 Million - $9.99 Million',
        4 => '$2 Million - $4.99 Million',
        5 => '$1 Million - $1.99 Million',
        6 => '$500,000 - $999,999',
      ),
    ),
    'Philanthropic_History' => array(
      'name' => 'Philanthropic_History',
      'label' => 'Philanthropic History',
      'data_type' => 'Memo',
      'html_type' => 'RichTextEditor',
      'is_searchable' => 1,
      'attributes' => 'rows=4, cols=60',
      'text_length' => 255,
      'note_columns' => 60,
      'note_rows' => 4,
    ),
    'Philanthropic_Interests' => array(
      'name' => 'Philanthropic_Interests',
      'label' => 'Philanthropic Interests',
      'data_type' => 'String',
      'html_type' => 'Select',
      'is_searchable' => 1,
      'note_columns' => 60,
      'note_rows' => 4,
      'option_values' => array(
        1 => 'Technology',
        2 => 'Education',
        3 => 'Political Campaign',
        4 => 'Cultural Arts',
        5 => 'Poverty',
        6 => 'Environment',
      ),
    ),
    'Subject_Area_Interest' => array(
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
    ),
    'Interests' => array(
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
    ),
    'University_Affiliation' => array(
      'label' => 'University Affiliation',
      'data_type' => 'String',
      'html_type' => 'Multi-Select',
      'is_searchable' => 1,
      'help_post' => 'University Attended, Taught At, Employed By',
      'text_length' => 255,
      'note_columns' => 60,
      'note_rows' => 4,
      // "option_group_id":"116",
    ),
    'Board_Affiliations' => array(
      'name' => 'Board_Affiliations',
      'label' => 'Board Affiliations',
      'data_type' => 'Memo',
      'html_type' => 'RichTextEditor',
      'is_searchable' => 1,
      'note_columns' => 60,
      'note_rows' => 4,
    ),
    'Notes' => array(
      'name' => 'Notes',
      'label' => 'Notes',
      'data_type' => 'Memo',
      'html_type' => 'RichTextEditor',
      'is_searchable' => 1,
      'note_columns' => 60,
      'note_rows' => 4,
    ),
    'Stage' => array(
      'name' => 'Stage',
      'label' => 'Stage',
      'data_type' => 'String',
      'html_type' => 'Select',
      'is_searchable' => 1,
      'note_columns' => 60,
      'note_rows' => 4,
      // "option_group_id":"32",
    ),
    'On Hold' => array(
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
    ),
    'Affinity' => array(
      'name' => 'Affinity',
      'label' => 'Affinity',
      'data_type' => 'String',
      'html_type' => 'Select',
      'is_searchable' => 1,
      'default_value' => 'Unknown',
      'note_columns' => 60,
      'note_rows' => 4,
      // "option_group_id":"46",
    ),

    'Capacity' => array(
      'name' => 'Capacity',
      'label' => 'Capacity',
      'data_type' => 'String',
      'html_type' => 'Select',
      'is_searchable' => 1,
      'help_post' => 'Low = <$5k\r\nMedium = $5k to $99,999\r\nHigh = >$100k and over',
      'note_columns' => 60,
      'note_rows' => 4,
       // "option_group_id":"34",
    ),
    'Reviewed' => array(
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
    ),
    'Income_Range' => array(
      'name' => 'Income_Range',
      'label' => 'Income Range',
      'data_type' => 'String',
      'html_type' => 'Select',
      'is_searchable' => 1,
      'text_length' => 255,
      'note_columns' => 60,
      'note_rows' => 4,
        // "option_group_id":"111",
     ),
    'Charitable_Contributions_Decile' => array(
      'name' => 'Charitable_Contributions_Decile',
      'label' => 'Charitable Contributions Decile',
      'data_type' => 'String',
      'html_type' => 'Select',
      'is_searchable' => 1,
      'text_length' => 255,
      'note_columns' => 60,
      'note_rows' => 4,
      // "option_group_id":"112",
    ),
    'Disc_Income_Decile' => array(
      'name' => 'Disc_Income_Decile',
      'label' => 'Disc Income Decile',
      'data_type' => 'String',
      'html_type' => 'Select',
      'is_searchable' => 1,
      'text_length' => 255,
      'note_columns' => 60,
      'note_rows' => 4,
      'option_values' => array(
        '1' => 'A',
        '2' => 'B',
        '3' => 'C',
        '4' => 'D',
        '5' => 'E',
        '6' => 'F',
        '7' => 'G',
        '8' => 'H',
        '9' => 'I',
        '10' => 'J',
        '11' => 'J',
      )
    ),
    'Voter_Party' => array(
       'name' => 'Voter_Party',
       'label' => 'Voter Party',
       'data_type' => 'String',
       'html_type' => 'Select',
       'is_searchable' => 1,
       'text_length' => 255,
       'note_columns' => 60,
       'note_rows' => 4,
       //"option_group_id":"114",
     ),
    'ask_amount' => array(
      'name' => 'ask_amount',
      'label' => 'Ask Amount',
      'data_type' => 'Money',
      'html_type' => 'Text',
      'is_searchable' => 1,
      'is_search_range' => 1,
    ),
    'expected_amount' => array(
      'name' => 'expected_amount',
      'label' => 'Expected Amount',
      'data_type' => 'Money',
      'html_type' => 'Text',
      'is_searchable' => 1,
      'is_search_range' => 1,
    ),
    'likelihood' => array(
      'name' => 'likelihood',
      'label' => 'Likelihood (%)',
      'data_type' => 'Integer',
      'html_type' => 'Text',
      'is_searchable' => 1,
      'is_search_range' => 1,
    ),
    'expected_close_date' => array(
      'name' => 'expected_close_date',
      'label' => 'Expected Close Date',
      'data_type' => 'Date',
      'html_type' => 'Select Date',
      'is_searchable' => 1,
      'is_search_range' => 1,
    ),
    'close_date' => array(
      'name' => 'close_date',
      'label' => 'Close Date',
      'data_type' => 'Date',
      'html_type' => 'Select Date',
      'is_searchable' => 1,
      'is_search_range' => 1,
    ),
    'next_step' => array(
      'name' => 'next_step',
      'label' => 'Next Step',
      'data_type' => 'Memo',
      'html_type' => 'RichTextEditor',
      'note_columns' => 60,
      'note_rows' => 4,
    ),
  );
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
