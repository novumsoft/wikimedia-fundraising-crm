<?php
namespace DonorReview;

use \CRM_Utils_System;

class Crm {
    static function getTagId( $tag ) {
        $api = civicrm_api_classapi();

        $success = $api->Tag->get( array(
            'name' => $tag,
            'version' => '3',
        ) );
        if ( !$success or !$api->values ) {
            throw new Exception( "Missing tag {$tag}" );
        }
        return $api->values[0]->id;
    }

    static function setTag( $contact_id, $tag ) {
        $api = civicrm_api_classapi();

        $tag_id = Crm::getTagId( $tag );

        $success = $api->EntityTag->create( array(
            'contact_id' => $contact_id,
            'tag_id' => $tag_id,
            'version' => '3',
        ) );
        if ( !$success ) {
            throw new Exception( "Failed to set tag: " . $api->errorMsg() );
        }
    }

    static function clearTag( $contact_id, $tag ) {
        $api = civicrm_api_classapi();

        $tag_id = Crm::getTagId( $tag );

        $success = $api->EntityTag->delete( array(
            'contact_id' => $contact_id,
            'tag_id' => $tag_id,
            'version' => '3',
        ) );
        if ( !$success ) {
            throw new Exception( "Failed to clear tag: " . $api->errorMsg() );
        }
    }

    static function getTags( $contact_id ) {
        $api = civicrm_api_classapi();

        $success = $api->EntityTag->get( array(
            'contact_id' => $contact_id,
            'version' => '3',
        ) );
        if ( !$success ) {
            throw new Exception( "Failed to fetch tags: " . $api->errorMsg() );
        }
        $out = array();
        foreach ( $api->values as $row ) {
            $out[] = $row->tag_id;
        }
        return $out;
    }

    static function getContact( $contact_id ) {
        $api = civicrm_api_classapi();

        $result = $api->Contact->get( array(
            'id' => $contact_id,
            'return' => implode( ',', array(
                'country',
                'display_name',
                'email',
                'preferred_language',
                'street_address',
            ) ),
            'version' => '3',
        ) );
        if ( !$result or count( $api->values ) != 1 ) {
            throw new Exception( "No results for contact {$contact_id}: {$result->errorMsg()}" );
        }
        $contact = $api->values[0];

        $result = $api->Contribution->get( array(
            'contact_id' => $contact_id,
            'version' => '3',
        ) );
        if ( !$result ) {
            throw new Exception( "Error when fetching contributions for contact {$contact_id}: {$result->errorMsg()}" );
        }
        $contact->contributions = $api->values();

        $contact->tags = Crm::getTags( $contact_id );

        return $contact;
    }

    /**
     * Build an a href to the view contact page
     *
     * @param int $contactId CiviCRM Contact ID
     * @return string html A tag
     */
    static function getContactLink( $contactId ) {
        return l(
            $contactId,
            CRM_Utils_System::url( 'civicrm/contact/view', null, true ),
            array(
                'query' => array(
                    'reset' => 1,
                    'cid' => $contactId
                )
            )
        );
    }
}
