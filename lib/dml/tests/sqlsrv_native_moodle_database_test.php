<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Test sqlsrv dml support.
 *
 * @package    core
 * @category   dml
 * @copyright  2017 John Okely
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot.'/lib/dml/sqlsrv_native_moodle_database.php');

/**
 * Test case for sqlsrv dml support.
 *
 * @package    core
 * @category   dml
 * @copyright  2017 John Okely
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sqlsrv_native_moodle_database_testcase extends advanced_testcase {

    public function setUp() {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Data provider for test_has_query_order_by
     *
     * @return array data for test_has_query_order_by
     */
    public function has_query_order_by_provider() {
        // Fixtures taken from https://docs.moodle.org/en/ad-hoc_contributed_reports.

        return [
            'User with language => FALSE' => [
                'sql' => <<<EOT
SELECT username, lang
  FROM prefix_user
EOT
                ,
                'expectedmainquery' => <<<EOT
SELECT username, lang
  FROM prefix_user
EOT
                ,
                'expectedresult' => false
            ],
            'List Users with extra info (email) in current course => FALSE' => [
                'sql' => <<<EOT
SELECT u.firstname, u.lastname, u.email
  FROM prefix_role_assignments AS ra
  JOIN prefix_context AS context ON context.id = ra.contextid AND context.contextlevel = 50
  JOIN prefix_course AS c ON c.id = context.instanceid AND c.id = %%COURSEID%%
  JOIN prefix_user AS u ON u.id = ra.userid
EOT
                ,
                'expectedmainquery' => <<<EOT
SELECT u.firstname, u.lastname, u.email
  FROM prefix_role_assignments AS ra
  JOIN prefix_context AS context ON context.id = ra.contextid AND context.contextlevel = 50
  JOIN prefix_course AS c ON c.id = context.instanceid AND c.id = %%COURSEID%%
  JOIN prefix_user AS u ON u.id = ra.userid
EOT
                ,
                'expectedresult' => false
            ],
            'ROW_NUMBER() OVER (ORDER BY ...) => FALSE (https://github.com/jleyva/moodle-block_configurablereports/issues/120)' => [
                'sql' => <<<EOT
SELECT COUNT(*) AS 'Users who have logged in today'
  FROM (
         SELECT ROW_NUMBER() OVER(ORDER BY lastaccess DESC) AS Row
           FROM mdl_user
          WHERE lastaccess > DATEDIFF(s, '1970-01-01 02:00:00', (SELECT Convert(DateTime, DATEDIFF(DAY, 0, GETDATE()))))
       ) AS Logins
EOT
                ,
                'expectedmainquery' => <<<EOT
SELECT COUNT() AS 'Users who have logged in today'
  FROM () AS Logins
EOT
                ,
                'expectedresult' => false
            ],
            'CONTRIB-7725 workaround) => TRUE' => [
                'sql' => <<<EOT
SELECT COUNT(*) AS 'Users who have logged in today'
  FROM (
         SELECT ROW_NUMBER() OVER(ORDER BY lastaccess DESC) AS Row
           FROM mdl_user
          WHERE lastaccess > DATEDIFF(s, '1970-01-01 02:00:00', (SELECT Convert(DateTime, DATEDIFF(DAY, 0, GETDATE()))))
       ) AS Logins ORDER BY 1
EOT
                ,
                'expectedmainquery' => <<<EOT
SELECT COUNT() AS 'Users who have logged in today'
  FROM () AS Logins ORDER BY 1
EOT
                ,
                'expectedresult' => true
            ],
            'Enrolment count in each Course => TRUE' => [
                'sql' => <<<EOT
  SELECT c.fullname, COUNT(ue.id) AS Enroled
    FROM prefix_course AS c
    JOIN prefix_enrol AS en ON en.courseid = c.id
    JOIN prefix_user_enrolments AS ue ON ue.enrolid = en.id
GROUP BY c.id
ORDER BY c.fullname
EOT
                ,
                'expectedmainquery' => <<<EOT
  SELECT c.fullname, COUNT() AS Enroled
    FROM prefix_course AS c
    JOIN prefix_enrol AS en ON en.courseid = c.id
    JOIN prefix_user_enrolments AS ue ON ue.enrolid = en.id
GROUP BY c.id
ORDER BY c.fullname
EOT
                ,
                'expectedresult' => true
            ],
        ];
    }

    /**
     * Test has_query_order_by
     *
     * @dataProvider has_query_order_by_provider
     * @param string $sql the query
     * @param string $expectedmainquery the expected main query
     * @param bool $expectedresult the expected result
     */
    public function test_has_query_order_by(string $sql, string $expectedmainquery, bool $expectedresult) {
        $mainquery = preg_replace('/\(((?>[^()]+)|(?R))*\)/', '()', $sql);
        $this->assertSame($expectedmainquery, $mainquery);

        // The has_query_order_by static method is protected. Use Reflection to call the method.
        $method = new ReflectionMethod('sqlsrv_native_moodle_database', 'has_query_order_by');
        $method->setAccessible(true);
        $result = $method->invoke(null, $sql);
        $this->assertSame($expectedresult, $result);
    }
}

/**
 * Test class for testing temptables
 *
 * @copyright  2017 John Okely
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class temptables_tester {
    /**
     * Returns if one table, based in the information present in the store, is a temp table
     *
     * For easy testing, anything with the word 'temp' in it is considered temporary.
     *
     * @param string $tablename name without prefix of the table we are asking about
     * @return bool true if the table is a temp table (based in the store info), false if not
     */
    public function is_temptable($tablename) {
        if (strpos($tablename, 'temp') === false) {
            return false;
        } else {
            return true;
        }
    }
    /**
     * Dispose the temptables
     *
     * @return void
     */
    public function dispose() {
    }
}
