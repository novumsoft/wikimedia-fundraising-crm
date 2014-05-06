<?php

class AzlChecksFile extends ChecksFile {
    function getRequiredColumns() {
        return array(
            'Batch',
            'Check Number',
            'City',
            'Contribution Type',
            'Country',
            'Direct Mail Appeal',
            'Email',
            'Gift Source',
            'Payment Instrument',
            'Postal Code',
            'Postmark Date',
            'Received Date',
            'Restrictions',
            'Source',
            'State',
            'Street Address',
            'Thank You Letter Date',
            'Total Amount',
        );
    }

    function getRequiredFields() {
        return array(
            'check_number',
            'date',
            'gift_source',
            'gross',
            'import_batch_number',
            'payment_method',
            'restrictions',
        );
    }
}
