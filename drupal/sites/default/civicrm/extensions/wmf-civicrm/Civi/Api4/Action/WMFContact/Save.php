<?php
namespace Civi\Api4\Action\WMFContact;

use Civi\Api4\Address;
use Civi\Api4\Generic\AbstractAction;
use Civi\Api4\Generic\Result;
use Civi\WMFException\WMFException;
use Civi\Api4\Email;
use CRM_Core_BAO_CustomField;
use WmfDatabase;
use Civi\Api4\Contact;
use wmf_civicrm\ImportStatsCollector;

/**
 * Class Create.
 *
 * Create a contact with WMF special handling (both logical and legacy/scary).
 *
 * Potentially this could extend the main apiv4 Contact class but there are
 * some baby-steps to take first. In the meantime this at least allows
 * us to rationalise code into a class.
 *
 * @method $this setMessage(array $msg) Set WMF normalised values.
 * @method array getMessage() Get WMF normalised values.
 * @method $this setContactID(int $contactID) Set the contact id to update.
 * @method int|null getContactID() get the contact it to update
 *
 * @package Civi\Api4
 */
class Save extends AbstractAction {

  /**
   * WMF style input.
   *
   * @var array
   */
  protected $message = [];

  /**
   * Contact ID to update.
   *
   * @var int
   */
  protected $contactID;

  protected function getTimer(): \Statistics\Collector\AbstractCollector {
    return ImportStatsCollector::getInstance();
  }

  /**
   * @inheritDoc
   *
   * @param \Civi\Api4\Generic\Result $result
   *
   * @throws \CRM_Core_Exception
   * @throws \CiviCRM_API3_Exception
   * @throws \Civi\WMFException\WMFException
   */
  public function _run(Result $result): void {

    $isCreate = !$this->getContactID();
    $contact_id = $this->getContactID();
    $msg = $this->getMessage();
    if (!$contact_id) {
      $contact_id = $this->getExistingContactID($msg);
      if ($contact_id) {
        $msg['contact_id'] = $contact_id;
        $this->handleUpdate($msg);
        $result[] = ['id' => $contact_id];
        return;
      }
    }
    // Set defaults for optional fields in the message
    if (!array_key_exists('contact_type', $msg)) {
      $msg['contact_type'] = "Individual";
    }
    elseif ($msg['contact_type'] != "Individual" && $msg['contact_type'] != "Organization") {
      // looks like an unsupported type was sent, revert to default
      watchdog('wmf_civicrm', 'Non-supported contact_type received: %type', ['%type' => print_r($msg['contact_type'], TRUE)], WATCHDOG_INFO);
      $msg['contact_type'] = "Individual";
    }

    if (!array_key_exists('contact_source', $msg)) {
      $msg['contact_source'] = "online donation";
    }

    // Create the contact record
    $contact = [
      'id' => $contact_id,
      'contact_type' => $msg['contact_type'],
      'contact_source' => $msg['contact_source'],
      'debug' => TRUE,
      'addressee_custom' => $msg['addressee_custom'] ?? NULL,
      'addressee_display' => $msg['addressee_custom'] ?? NULL,
      'addressee_id' => empty($msg['addressee_custom']) ? NULL : 'Customized',

      // We speed up our imports by passing in this param.
      // going forwards there is scope to a) improve the processing
      // upstream rather than skip & b) not skip for Major Gifts contacts.
      'skip_greeting_processing' => TRUE,
    ];
    if (strtolower($msg['contact_type']) !== "organization") {
      foreach (['first_name', 'last_name', 'middle_name'] as $name) {
        if (isset($msg[$name])) {
          $contact[$name] = wmf_civicrm_string_clean($msg[$name], 64);
        }
      }
    }

    if (!$contact_id && isset($msg['email']) && wmf_civicrm_is_email_valid($msg['email'])) {
      // For updates we are still using our own process which may or may not confer benefits
      // For inserts however we can rely on the core api.
      $contact['email'] = $msg['email'];
    }
    if (strtolower($msg['contact_type']) == "organization") {
      // @todo probably can remove handling for sort name and display name now.
      $contact['sort_name'] = $msg['organization_name'];
      $contact['display_name'] = $msg['organization_name'];
      $contact['organization_name'] = $msg['organization_name'];
    }
    elseif (!empty($msg['employer_id'])) {
      $contact['employer_id'] = $msg['employer_id'];
    }
    if (!empty($msg['prefix_id:label'])) {
      // prefix_id:label is APIv4 format. name_prefix is our own fandango.
      // We should start migrating to APIv4 format so supporting it
      // is a first step.
      $msg['name_prefix'] = $msg['prefix_id:label'];
    }
    if (!empty($msg['name_prefix'])) {
      $contact['prefix_id'] = $msg['name_prefix'];
      wmf_civicrm_ensure_option_exists($msg['name_prefix'], 'prefix_id', 'individual_prefix');
    }
    if (!empty($msg['name_suffix'])) {
      $contact['suffix_id'] = $msg['name_suffix'];
      wmf_civicrm_ensure_option_exists($msg['name_suffix'], 'suffix_id', 'individual_suffix');
    }

    $cdId = NULL;
    if (isset($msg['contribution_tracking_id']) && is_numeric($msg['contribution_tracking_id'])) {
      $cdId = (int) $msg['contribution_tracking_id'];
    }
    $contact['preferred_language'] = $this->getPreferredLanguage($msg['language'] ?? '', $cdId, $msg['country'] ?? '');;

    // Copy some fields, if they exist
    $direct_fields = [
      'do_not_email',
      'do_not_mail',
      'do_not_phone',
      'do_not_sms',
      'is_opt_out',
    ];
    foreach ($direct_fields as $field) {
      if (isset($msg[$field])) {
        if (in_array($msg[$field], [0, 1, '0', '1', TRUE, FALSE], TRUE)) {
          $contact[$field] = $msg[$field];
        }
        elseif (strtoupper($msg[$field]) === 'Y') {
          $contact[$field] = TRUE;
        }
      }
    }

    $custom_vars = [];
    $custom_field_mangle = [
      'opt_in' => 'opt_in',
      'do_not_solicit' => 'do_not_solicit',
      'org_contact_name' => 'Name',
      'org_contact_title' => 'Title',
      'employer' => 'Employer_Name',
      // Partner is the custom field's name, Partner is also the custom group's name
      // since other custom fields have names similar to core fields (Partner.Email)
      // this api-similar namespacing convention seems like a good choice.
      'Partner.Partner' => 'Partner',
      'Organization_Contact.Phone' => 'Phone',
      'Organization_Contact.Email' => 'Email',
      // These 2 fields already have aliases but adding
      // additional ones with the new standard allows migration
      // and means that the import file does not have to mix and match.
      'Organization_Contact.Title' => 'Title',
      'Organization_Contact.Name' => 'Name',
    ];
    foreach ($custom_field_mangle as $msgField => $customField) {
      if (isset($msg[$msgField])) {
        $custom_vars[$customField] = $msg[$msgField];
      }
    }

    $custom_name_mapping = wmf_civicrm_get_custom_field_map(array_keys($custom_vars));
    foreach ($custom_name_mapping as $readable => $machined) {
      if (array_key_exists($readable, $custom_vars)) {
        $contact[$machined] = $custom_vars[$readable];
      }
    }

    if (WmfDatabase::isNativeTxnRolledBack()) {
      throw new WMFException(WMFException::IMPORT_CONTACT, "Native txn rolled back before inserting contact");
    }
    // Attempt to insert the contact
    try {
      $contact_result = civicrm_api3('Contact', 'Create', $contact);
      watchdog('wmf_civicrm', 'Successfully ' . ($contact_id ? 'updated' : 'created ') . ' contact: %id', ['%id' => $contact_result['id']], WATCHDOG_DEBUG);

      if (WmfDatabase::isNativeTxnRolledBack()) {
        throw new WMFException(WMFException::IMPORT_CONTACT, "Native txn rolled back after inserting contact");
      }
    }
    catch (\CiviCRM_API3_Exception $ex) {
      if (in_array($ex->getErrorCode(), ['constraint violation', 'deadlock', 'database lock timeout'])) {
        throw new WMFException(
          WMFException::DATABASE_CONTENTION,
          'Contact could not be added due to database contention',
          $ex->getExtraParams()
        );
      }
      throw new WMFException(
        WMFException::IMPORT_CONTACT,
        'Contact could not be added. Aborting import. Contact data was ' . print_r($contact, TRUE) . ' Original error: ' . $ex->getMessage()
        . ' Details: ' . print_r($ex->getExtraParams(), TRUE),
        $ex->getExtraParams()
      );
    }
    $contact_id = (int) $contact_result['id'];

    // Add phone number
    if (isset($msg['phone'])) {
      try {
        $phone_result = civicrm_api3('Phone', 'Create', [
          // XXX all the fields are nullable, should we set any others?
          'contact_id' => $contact_id,
          'location_type_id' => wmf_civicrm_get_default_location_type_id(),
          'phone' => $msg['phone'],
          'phone_type_id' => 'Phone',
          'is_primary' => 1,
          'debug' => TRUE,
        ]);
      }
      catch (\CiviCRM_API3_Exception $ex) {
        throw new WMFException(
          WMFException::IMPORT_CONTACT,
          "Failed to add phone for contact ID {$contact_id}: {$ex->getMessage()} " . print_r($ex->getExtraParams(), TRUE)
        );
      }
    }

    // Add groups to this contact.
    if (!empty($msg['contact_groups'])) {
      // TODO: Use CRM_Contact_GroupContact::buildOptions in Civi 4.4, also
      // in place of ::tag below.
      $supported_groups = array_flip(\CRM_Core_PseudoConstant::allGroup());
      $stacked_ex = [];
      foreach (array_unique($msg['contact_groups']) as $group) {
        try {
          $tag_result = civicrm_api3("GroupContact", "Create", [
            'contact_id' => $contact_id,
            'group_id' => $supported_groups[$group],
          ]);
        }
        catch (\CiviCRM_API3_Exception $ex) {
          $stacked_ex[] = "Failed to add group {$group} to contact ID {$contact_id}. Error: " . $ex->getMessage();
        }
      }
      if (!empty($stacked_ex)) {
        throw new WMFException(
          WMFException::IMPORT_CONTACT,
          implode("\n", $stacked_ex)
        );
      }
    }
    $this->addTagsToContact($msg['contact_tags'] ?? [], $contact_id);

    // Create a relationship to an existing contact?
    if (!empty($msg['relationship_target_contact_id'])) {
      $this->createRelationship($msg['relationship_target_contact_id'], $msg['relationship_type'], $contact_id);
    }
    if ($isCreate) {
      // Insert the location records if this is being called as a create.
      // For update it's handled in the update routing.
      wmf_civicrm_message_address_insert($msg, $contact_id);
    }
    if (WmfDatabase::isNativeTxnRolledBack()) {
      throw new WMFException(WMFException::IMPORT_CONTACT, "Native txn rolled back after inserting contact auxiliary fields");
    }
    $result[] = $contact_result;

  }


  /**
   * Start the timer on a process.
   *
   * @param string $description
   */
  protected function startTimer($description) {
    $this->getTimer()->startImportTimer($description);
  }

  /**
   * Start the timer on a process.
   *
   * @param string $description
   */
  protected function stopTimer($description) {
    $this->getTimer()->endImportTimer($description);
  }

  /**
   * Add tags to the contact.
   *
   * Note that this code may be never used - I logged
   * https://phabricator.wikimedia.org/T286225 to query whether the only
   * place that seems like it might pass in contact_tags is actually ever used.
   *
   * @param array $tags
   * @param int $contact_id
   *
   * @return void
   * @throws \Civi\WMFException\WMFException
   */
  protected function addTagsToContact(array $tags, int $contact_id): void {
    // Do we have any tags we need to add to this contact?
    if (!empty($tags)) {
      $supported_tags = array_flip(\CRM_Core_BAO_Tag::getTags('civicrm_contact'));
      $stacked_ex = [];
      foreach (array_unique($tags) as $tag) {
        try {
          civicrm_api3('EntityTag', 'Create', [
            'entity_table' => 'civicrm_contact',
            'entity_id' => $contact_id,
            'tag_id' => $supported_tags[$tag],
          ]);
        }
        catch (\CiviCRM_API3_Exception $ex) {
          $stacked_ex[] = "Failed to add tag {$tag} to contact ID {$contact_id}. Error: " . $ex->getMessage();
        }
      }
      if (!empty($stacked_ex)) {
        throw new WMFException(
          WMFException::IMPORT_CONTACT,
          implode("\n", $stacked_ex)
        );
      }
    }
  }

  /**
   * Get the preferred language.
   *
   * This is a bit of a nasty historical effort to come up with a civi-like
   * language string. It often creates nasty variants like 'es_NO' - Norwegian
   * Spanish - for spanish speakers who filled in the form while in Norway.
   *
   * We hateses it my precious.
   *
   * Bug https://phabricator.wikimedia.org/T279389 is open to clean this up.
   *
   * @param string $incomingLanguage language from external source
   * @param int|null $contributionTrackingID contribution tracking id - could this be unset?
   * @param string $country
   *
   * @throws \CiviCRM_API3_Exception
   */
  protected function getPreferredLanguage(string $incomingLanguage, ?int $contributionTrackingID, string $country): string {
    $preferredLanguage = '';
    if (!$incomingLanguage) {
      // TODO: use LanguageTag to prevent truncation of >2 char lang codes
      // guess from contribution_tracking data
      if ($contributionTrackingID) {
        $tracking = wmf_civicrm_get_contribution_tracking(['contribution_tracking_id' => $contributionTrackingID]);
        if ($tracking and !empty($tracking['language'])) {
          if (strpos($tracking['language'], '-')) {
            // If we are already tracking variant, use that
            [$language, $variant] = explode('-', $tracking['language']);
            $preferredLanguage = $language . '_' . strtoupper($variant);
          }
          else {
            $preferredLanguage = $tracking['language'];
            if (!empty($tracking['country'])) {
              $preferredLanguage .= '_' . $tracking['country'];
            }
          }
        }
      }
      if (!$preferredLanguage) {
        // FIXME: wish we had the contact_id here :(
        watchdog('wmf_civicrm', 'Failed to guess donor\'s preferred language, falling back to some hideous default', NULL, WATCHDOG_INFO);
      }
    }
    else {
      // If the language is already an existing full locale, don't mangle it
      if (strlen($incomingLanguage) > 2 && wmf_civicrm_check_language_exists($incomingLanguage)) {
        $preferredLanguage = $incomingLanguage;
      }
      else {
        $preferredLanguage = strtolower(substr($incomingLanguage, 0, 2));
        if ($country) {
          $preferredLanguage .= '_' . strtoupper(substr($country, 0, 2));
        }
      }
    }
    if ($preferredLanguage) {
      if (!wmf_civicrm_check_language_exists($preferredLanguage)) {
        $parts = explode('_', $preferredLanguage);
        if (wmf_civicrm_check_language_exists($parts[0])) {
          // in other words en_NO will be converted to en
          // rather than Norwegian English.
          $preferredLanguage = $parts[0];
        }
        else {
          // otherwise let's create it rather than fail.
          // seems like the easiest way not to lose visibility, data or the plot.
          wmf_civicrm_ensure_language_exists($preferredLanguage);
        }
      }
    }
    return $preferredLanguage;
  }

  /**
   * Create a relationship to another specified contact.
   *
   * @param int $relatedContactID
   * @param string $relationshipType
   * @param int $contact_id
   *
   * @throws \CiviCRM_API3_Exception
   * @throws \Civi\WMFException\WMFException
   */
  protected function createRelationship(int $relatedContactID, string $relationshipType, int $contact_id): void {
    $relationship_type = civicrm_api3("RelationshipType", "Get", [
      'name_a_b' => $relationshipType,
    ]);
    if (!$relationship_type['count']) {
      throw new WMFException(WMFException::IMPORT_CONTACT, "Bad relationship type: {$relationshipType}");
    }

    try {
      civicrm_api3("Relationship", "Create", [
        'contact_id_a' => $contact_id,
        'contact_id_b' => $relatedContactID,
        'relationship_type_id' => $relationship_type['id'],
        'is_active' => 1,
      ]);
    }
    catch (\CiviCRM_API3_Exception $ex) {
      throw new WMFException(WMFException::IMPORT_CONTACT, $ex->getMessage());
    }
  }

  /**
   * Handle a contact update - this is moved here but not yet integrated.
   *
   * This is an interim step... getting it onto the same class.
   *
   * @param array $msg
   *
   * @throws \API_Exception
   * @throws \Civi\WMFException\WMFException
   */
  public function handleUpdate(array $msg): void {
    // @todo Do not solicit appears like these fields but is a custom field. Not handled yet as not in the import
    // this was written (& tested) in conjunction with (Engage).
    $updateFields = [
      'do_not_email',
      'do_not_mail',
      'do_not_trade',
      'do_not_phone',
      'is_opt_out',
      'do_not_sms'
    ];
    $updateParams = array_intersect_key($msg, array_fill_keys($updateFields, TRUE));
    if (($msg['contact_type'] ?? NULL) === 'Organization') {
      // Find which of these keys we have update values for.
      $customFieldsToUpdate = array_filter(array_intersect_key($msg, array_fill_keys([
        'Organization_Contact.Name',
        'Organization_Contact.Email',
        'Organization_Contact.Phone',
        'Organization_Contact.Title',
      ], TRUE)));
      if (!empty($customFieldsToUpdate)) {
        if ($msg['gross'] >= 25000) {
          // See https://phabricator.wikimedia.org/T278892#70402440)
          // 25k plus gifts we keep both names for manual review.
          $existingCustomFields = Contact::get(FALSE)
            ->addWhere('id', '=', $msg['contact_id'])
            ->setSelect(array_keys($customFieldsToUpdate))
            ->execute()
            ->first();
          foreach ($customFieldsToUpdate as $fieldName => $value) {
            if (stripos($existingCustomFields[$fieldName], $value) === FALSE) {
              $updateParams[$fieldName] = empty($existingCustomFields[$fieldName]) ? $value : $existingCustomFields[$fieldName] . '|' . $value;
            }
          }
        }
        else {
          $updateParams = array_merge($updateParams, $customFieldsToUpdate);
        }
      }
    }
    else {
      // Individual-only custom fields
      $additionalFieldMap = [
        'employer_id' => 'employer_id', // Civi core pointer to an org contact
        'employer' => 'Communication.Employer_Name' // WMF-only custom field
      ];
      foreach($additionalFieldMap as $messageField => $civiField) {
        if (!empty($msg[$messageField])) {
          $updateParams[$civiField] = $msg[$messageField];
        }
      }
    }
    if (!empty($updateParams)) {
      Contact::update(FALSE)
        ->addWhere('id', '=', $msg['contact_id'])
        ->setValues($updateParams)
        ->execute();
    }

    // We have set the bar for invoking a location update fairly high here - ie state,
    // city or postal_code is not enough, as historically this update has not occurred at
    // all & introducing it this conservatively feels like a safe strategy.
    if (!empty($msg['street_address'])) {
      $this->startTimer('message_location_update');
      wmf_civicrm_message_location_update($msg, ['id' => $msg['contact_id']]);
      $this->stopTimer('message_location_update');
    }
    elseif (!empty($msg['email'])) {
      // location_update updates email, if set and address, if set.
      // However, not quite ready to start dealing with the situation
      // where less of the address is incoming than already exists
      // hence only call this part if street_address is empty.
      $this->startTimer('message_email_update');
      wmf_civicrm_message_email_update($msg, $msg['contact_id']);
      $this->stopTimer('message_email_update');
    }
  }


  /**
   * Look for existing exact-match contact in the database.
   *
   * Note if there is more than one possible match we treat it as
   * 'no match'.
   *
   * @param array $msg
   *
   * @return int|null
   *
   * @throws \API_Exception
   */
  protected function getExistingContactID(array $msg): ?int {
    if (empty($msg['first_name']) || empty($msg['last_name'])) {
      return NULL;
    }
    if (!empty($msg['email'])) {
      // Check for existing....
      $matches = Email::get(FALSE)
        ->addWhere('contact_id.first_name', '=', $msg['first_name'])
        ->addWhere('contact_id.last_name', '=', $msg['last_name'])
        ->addWhere('contact_id.is_deleted', '=', 0)
        ->addWhere('contact_id.is_deceased', '=', 0)
        ->addWhere('email', '=', $msg['email'])
        ->addWhere('is_primary', '=', TRUE)
        ->setSelect(['contact_id'])
        ->setLimit(2)
        ->execute();
      if (count($matches) === 1) {
        return $matches->first()['contact_id'];
      }
      return NULL;
    }
    // If we have sufficient address data we will look up from the database.
    // original discussion at https://phabricator.wikimedia.org/T283104#7171271
    // We didn't talk about min_length for the other fields so I just went super
    // conservative & picked 2
    $addressCheckFields = ['street_address' => 5, 'city' => 2, 'postal_code' => 2];
    foreach ($addressCheckFields as $field => $minLength) {
      if (strlen($msg[$field] ?? '') < $minLength) {
        return NULL;
      }
    }
    $matches = Address::get(FALSE)
      ->addWhere('city', '=',  $msg['city'])
      ->addWhere('postal_code', '=', $msg['postal_code'])
      ->addWhere('street_address', '=', $msg['street_address'])
      ->addWhere('contact_id.first_name', '=', $msg['first_name'])
      ->addWhere('contact_id.last_name', '=', $msg['last_name'])
      ->addWhere('contact_id.is_deleted', '=', 0)
      ->addWhere('contact_id.is_deceased', '=', 0)
      ->addWhere('is_primary', '=', TRUE)
      ->setSelect(['contact_id'])
      ->setLimit(2)
      ->execute();
    if (count($matches) === 1) {
      return $matches->first()['contact_id'];
    }

    return NULL;
  }

}
