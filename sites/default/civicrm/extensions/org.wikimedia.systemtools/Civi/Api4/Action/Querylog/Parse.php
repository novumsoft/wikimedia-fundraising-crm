<?php


namespace Civi\Api4\Action\Querylog;

use Civi;
use Civi\Api4\Generic\AbstractAction;
use Civi\Api4\Generic\Result;
use League\Csv\Writer;

/**
 * Class Parse.
 *
 * Get the content of an email for the given template text, rendering tokens.
 *
 * @method $this setLimit(int $limit) Set Limit
 * @method int getLimit() Get Limit
 * @method $this setFileName(string $fileName) Set File Name
 * @method string getFileName() Get File Name
 */
class Parse extends AbstractAction {

  /**
   * Limit of entities to process.
   *
   * @var int
   */
  protected $limit;

  /**
   * Name of log file to parse.
   *
   * @var string
   */
  protected $fileName;

  /**
   * @inheritDoc
   *
   * @param \Civi\Api4\Generic\Result $result
   *
   * @throws \API_Exception
   * @throws \Civi\API\Exception\NotImplementedException
   * @throws \League\Csv\CannotInsertRecord
   */
  public function _run(Result $result) {
    $lines = file($this->getFileName(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $parsed = [];
    $writer = Writer::createFromPath(dirname($this->getFileName()) . '/query_log_parsed.csv', 'w+');
    $writer->insertOne(['Date', 'Query', 'Seconds taken', 'Affected rows', 'Affected columns']);

    $currentIndex = 0;
    foreach ($lines as $index => $line) {
      $line = trim($line);
      $date = substr($line,0,15);
      if (preg_match("/^[A-Za-z]{3} [0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}$/",$date)) {
        // We have a new log line.
        $date = date('Y-m-d H:i:s');
        $type = substr($line, 18, 5) === 'debug' ? 'debug' : 'info';
        $isQuery = substr($line, 24, 9) === ' $Query =';
        if ($isQuery) {
          if (!empty($parsed[$currentIndex])) {
            // Starting parsing a new query - write the previous one, if exists.
            $writer->insertOne($parsed[$currentIndex]);
          }
         // this is the low hanging fruit of data sanitising - get rid of emails.
          $query = preg_replace('/([a-zA-Z0-9_\-\.]+)@([a-zA-Z0-9_\-\.]+)\.([a-zA-Z]{2,5})/', $currentIndex . '@example.com', substr($line, 34));
          $parsed[$index] = [
            'timestamp' => $date,
            'query' => $query,
            'seconds' => '',
            'rows' => '',
            'columns' => '',
          ];
          $currentIndex = $index;
        }
        elseif ($type === 'info') {
          $re = '/QUERY DONE IN (\d*.\d*)  seconds. Result is (\d*) rows by (\d*) columns./m';
          preg_match_all($re, $line, $matches, PREG_SET_ORDER, 0);
          $parsed[$currentIndex]['seconds'] = $matches[0][1];
          $parsed[$currentIndex]['rows'] = $matches[0][2];
          $parsed[$currentIndex]['columns'] = $matches[0][2];
        }

      }
      else {
        // It's a continuance of the previous one.
        $parsed[$currentIndex]['query'] .= ' ' . $line;
      }

    }
    if ($parsed[$currentIndex]) {
      // Write the final line.
      $writer->insertOne($parsed[$currentIndex]);
    }
    $result[] = [
      'out_file' => dirname($this->getFileName()) . '/query_log_parsed.csv',
      'queries' => $currentIndex,
    ];

  }

}
