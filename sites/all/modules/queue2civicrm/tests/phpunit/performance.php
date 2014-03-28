<?php

if ( !class_exists( "PHPUnit_Framework_TestCase" ) ) return;

require_once __DIR__ . '/../includes/MessageSource.php';

class PerformanceTest extends PHPUnit_Framework_TestCase {
    public static function getInfo() {
        return array(
            'name' => 'Peformance',
            'group' => 'Pipeline',
            'description' => 'Measure timing and database activity',
        );
    }

    /*
    public function setUp() {
    }

    public function tearDown() {
    }
    */

    /**
     * Inject 1000 queue entries of XXX type, and consume
     */
    public function test1000() {
        //FIXME override batch_max
        $generator = new MessageSource();
        foreach ( range( 1, 1000 ) as $i ) {
            $generator->inject();
        }

        // FIXME
        // set_variable( 'queue', 'test-donations' );
        // module_invoke( 'queue2civicrm', 'batch_process' );
        //system( "drush qc" );
    }
}
