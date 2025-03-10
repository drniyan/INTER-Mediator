<?php
/*
 * Created by JetBrains PhpStorm.
 * User: msyk
 * Date: 11/12/14
 * Time: 14:21
 * Unit Test by PHPUnit (http://phpunit.de)
 *
 */

require_once('PHPUnit/Framework/TestCase.php');
require_once('../INTER-Mediator/DB_Interfaces.php');
require_once('../INTER-Mediator/DB_UseSharedObjects.php');
require_once('../INTER-Mediator/DB_AuthCommon.php');
require_once('../INTER-Mediator/DB_PDO.php');
require_once('../INTER-Mediator/DB_Settings.php');
require_once('../INTER-Mediator/DB_Formatters.php');
require_once('../INTER-Mediator/DB_Proxy.php');
require_once('../INTER-Mediator/DB_Logger.php');

class DB_PDO_Test extends PHPUnit_Framework_TestCase
{
    function setUp()
    {
        mb_internal_encoding('UTF-8');
        date_default_timezone_set('Asia/Tokyo');

        $this->db_proxy = new DB_Proxy();
        $this->db_proxy->initialize(array(),
            array(
                'authentication'=> array( // table only, for all operations
                    'user' => array('user1'), // Itemize permitted users
                    'group' => array('group2'), // Itemize permitted groups
                    'privilege' => array(), // Itemize permitted privileges
                    'user-table' => 'authuser', // Default values, or "_Native"
                    'group-table' => 'authgroup',
                    'corresponding-table' => 'authcor',
                    'challenge-table' => 'issuedhash',
                    'authexpired' => '300', // Set as seconds.
                    'storing' => 'cookie-domainwide', // 'cookie'(default), 'cookie-domainwide', 'none'
                ),
            ),
            array(
                'db-class'=>'PDO',
                'dsn'=>'mysql:unix_socket=/tmp/mysql.sock;dbname=test_db;',
                'user'=>'web',
                'password'=> 'password',
            ),
            false);
    }

    public function testAuthUser1()
    {
        $testName = "Check time calc feature of PHP";
        $expiredDT = new DateTime('2012-02-13 11:32:40');
        $currentDate = new DateTime('2012-02-14 11:32:51');
        //    $expiredDT = new DateTime('2012-02-13 00:00:00');
        //    $currentDate = new DateTime('2013-04-13 01:02:03');
        $intervalDT = $expiredDT->diff($currentDate, true);
        // var_export($intervalDT);
        $calc = (($intervalDT->days * 24 + $intervalDT->h) * 60 + $intervalDT->i) * 60 + $intervalDT->s;
        echo $calc;
        $this->assertTrue($calc === (11 + 3600 * 24), $testName);
    }

    public function testAuthUser2()
    {
        $testName = "Password Retrieving";
        $username = 'user1';
        $expectedPasswd = 'd83eefa0a9bd7190c94e7911688503737a99db0154455354';

        $retrievedPasswd = $this->db_proxy->dbClass->authSupportRetrieveHashedPassword($username);
        echo var_export($this->db_proxy->logger->debugMessage, true);
        $this->assertEquals($expectedPasswd, $retrievedPasswd, $testName);

    }

    public function testAuthUser3()
    {
        $testName = "Salt retrieving";
        $username = 'user1';
        $retrievedSalt = $this->db_proxy->dbClass->authSupportGetSalt($username);
        $this->assertEquals('54455354', $retrievedSalt, $testName);

    }

        public function testAuthUser4()
        {
            $testName = "Generate Challenge and Retrieve it";
            $username = 'user1';
            $challenge = $this->db_proxy->authCommon->generateChallenge();
            $this->db_proxy->dbClass->authSupportStoreChallenge($username, $challenge, "TEST");
            $this->assertEquals($challenge, $this->db_proxy->dbClass->authSupportRetrieveChallenge($username, "TEST"), $testName);
            $challenge = $this->db_proxy->authCommon->generateChallenge();
            $this->db_proxy->dbClass->authSupportStoreChallenge($username, $challenge, "TEST");
            $this->assertEquals($challenge, $this->db_proxy->dbClass->authSupportRetrieveChallenge($username, "TEST"), $testName);
            $challenge = $this->db_proxy->authCommon->generateChallenge();
            $this->db_proxy->dbClass->authSupportStoreChallenge($username, $challenge, "TEST");
            $this->assertEquals($challenge, $this->db_proxy->dbClass->authSupportRetrieveChallenge($username, "TEST"), $testName);

        }

        public function testAuthUser5()
        {
            $testName = "Simulation of Authentication";
            $username = 'user1';
            $password = 'user1'; //'d83eefa0a9bd7190c94e7911688503737a99db0154455354';

            $challenge = $this->db_proxy->authCommon->generateChallenge();
            $this->db_proxy->dbClass->authSupportStoreChallenge($username, $challenge, "TEST");

    //        $challenge = $this->db_pdo->authSupportRetrieveChallenge($username, "TEST");
            $retrievedHexSalt = $this->db_proxy->dbClass->authSupportGetSalt($username);
            $retrievedSalt = pack('N', hexdec($retrievedHexSalt));

            $hashedvalue = sha1($password . $retrievedSalt) . bin2hex($retrievedSalt);
            $calcuratedHash = sha1($challenge . $hashedvalue);
            $this->assertTrue(
                $this->db_proxy->authCommon->checkAuthorization($username, $calcuratedHash, "TEST"), $testName);
        }

        public function testAuthUser6()
        {
            $testName = "Create New User and Authenticate";
            $username = "testuser2";
            $password = "testuser2";
//                $this->assertTrue($this->db_proxy->authCommon->addUser( $username, $password ));

            $retrievedHexSalt = $this->db_proxy->dbClass->authSupportGetSalt($username);
            $retrievedSalt = pack('N', hexdec($retrievedHexSalt));

            $clientId = "TEST";
            $challenge = $this->db_proxy->authCommon->generateChallenge();
            $this->db_proxy->authCommon->saveChallenge($username, $challenge, $clientId);

            $hashedvalue = sha1($password . $retrievedSalt) . bin2hex($retrievedSalt);
            echo $hashedvalue;

            $this->assertTrue(
                $this->db_proxy->authCommon->checkAuthorization($username, sha1($challenge . $hashedvalue), $clientId),
                $testName);
        }

        function testUserGroup()
        {
            $testName = "Resolve containing group";
            $groupArray = $this->db_proxy->dbClass->getGroupsOfUser('user1');
            echo var_export($groupArray);
            $this->assertTrue(count($groupArray) > 0, $testName);
        }

        public function testNativeUser()
        {
            $testName = "Native User Challenge Check";
            $cliendId = "12345";

            $challenge = $this->db_proxy->authCommon->generateChallenge();
            echo "\ngenerated=", $challenge;
            $this->db_proxy->dbClass->authSupportStoreChallenge(0, $challenge, $cliendId);

            $this->assertTrue(
                $this->db_proxy->authCommon->checkChallenge($challenge, $cliendId), $testName);
        }

}