<?php

/**
 * OrangeHRM is a comprehensive Human Resource Management (HRM) System that captures
 * all the essential functionalities required for any enterprise.
 * Copyright (C) 2006 OrangeHRM Inc., http://www.orangehrm.com
 *
 * OrangeHRM is free software; you can redistribute it and/or modify it under the terms of
 * the GNU General Public License as published by the Free Software Foundation; either
 * version 2 of the License, or (at your option) any later version.
 *
 * OrangeHRM is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with this program;
 * if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor,
 * Boston, MA  02110-1301, USA
 *
 */
/* For logging PHP errors */
include_once('lib/confs/log_settings.php');

if (!defined('ROOT_PATH')) {
    $rootPath = realpath(dirname(__FILE__));
    define('ROOT_PATH', $rootPath);
}

require_once(ROOT_PATH . '/installer/utils/installUtil.php');
global $dbConnection;


//2. add https part since website is not hosted in https
//3. add new field to store number of employees
function sendRegistrationData($postArr) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL,"https://www.orangehrm.com/registration/registerAcceptor.php");
    curl_setopt($ch, CURLOPT_POST, 1);
    $data = "userName=".$postArr['userName']
			."&userEmail=".$postArr['userEmail']
                        ."&userTp=".$postArr['userTp']
			."&userComments=".$postArr['userComments']
			."&firstName=".$postArr['firstName']
			."&company=".$postArr['company']
                        ."&empCount=".$postArr['empCount']
			."&updates=".(isset($postArr['chkUpdates']) ? '1' : '0');
    curl_setopt($ch, CURLOPT_POSTFIELDS,$data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec ($ch);
    curl_close ($ch);  
    if(strpos($response, 'SUCCESSFUL') === false) {
        return false;
    } else {
        return true;
    }
	    	
}

function createDbConnection($host, $username, $password, $dbname, $port) {
    if (!$port) {
        $dbConnection = mysqli_connect($host, $username, $password, $dbname);
    } else {
        $dbConnection = mysqli_connect($host, $username, $password, $dbname, $port);
    }

    if (!$dbConnection) {
        return;
    }
    $dbConnection->set_charset("utf8");
    //mysqli_autocommit($dbConnection, FALSE);
    return $dbConnection;
}

function executeSql($query) {
    global $dbConnection;
    
    $result = mysqli_query($dbConnection, $query);
   
    return $result;
}

function back($currScreen) {

    for ($i = 0; $i < 2; $i++) {
        switch ($currScreen) {

            default :
            case 0 : unset($_SESSION['WELCOME']);
                break;
            case 1 : unset($_SESSION['LICENSE']);
                break;
            case 2 : unset($_SESSION['DBCONFIG']);
                break;
            case 3 : unset($_SESSION['SYSCHECK']);
                break;
            case 4 : unset($_SESSION['DEFUSER']);
                break;
            case 5 : unset($_SESSION['CONFDONE']);
                break;
            case 6 : $_SESSION['UNISTALL'] = true;
                unset($_SESSION['CONFDONE']);
                unset($_SESSION['INSTALLING']);
                break;
            case 7 : return false;
                break;
        }

        $currScreen--;
    }

    return true;
}

//define('ROOT_PATH', dirname(__FILE__));

if (!isset($_SESSION['SID']))
    session_start();

clearstatcache();

if (is_file(ROOT_PATH . '/lib/confs/Conf.php') && !isset($_SESSION['INSTALLING'])) {
    header('Location: ./index.php');
    exit();
}

if (isset($_SESSION['error'])) {
    unset($_SESSION['error']);
}

/* This $_SESSION['cMethod'] is used to determine wheter to use an existing database or a new one */

$_SESSION['cMethod'] = 'new';

if (isset($_POST['cMethod'])) {
    $_SESSION['cMethod'] = $_POST['cMethod'];
}

if (isset($_POST['actionResponse']))
    switch ($_POST['actionResponse']) {

        case 'WELCOMEOK' : $_SESSION['WELCOME'] = 'OK';
            break;
        case 'LICENSEOK' : $_SESSION['LICENSE'] = 'OK';
            break;
        case 'SYSCHECKOK' : $_SESSION['SYSCHECK'] = 'OK';
            break;

        case 'DBINFO' : $uname = "";
            $passw = "";
            if (isset($_POST['dbUserName'])) {
                $uname = trim($_POST['dbUserName']);
            }
            if (isset($_POST['dbPassword'])) {
                $passw = trim($_POST['dbPassword']);
            }
            $dbInfo = array('dbHostName' => trim($_POST['dbHostName']),
                'dbHostPort' => trim($_POST['dbHostPort']),
                'dbHostPortModifier' => trim($_POST['dbHostPortModifier']),
                'dbName' => trim($_POST['dbName']),
                'dbUserName' => $uname,
                'dbPassword' => $passw);

            if (!isset($_POST['chkSameUser'])) {
                $dbInfo['dbOHRMUserName'] = trim($_POST['dbOHRMUserName']);
                $dbInfo['dbOHRMPassword'] = trim($_POST['dbOHRMPassword']);
            }

            if ($_POST['dbCreateMethod'] == 'existing') {
                $dbInfo['dbUserName'] = trim($_POST['dbOHRMUserName']);
                $dbInfo['dbPassword'] = trim($_POST['dbOHRMPassword']);
            }

            $_SESSION['dbCreateMethod'] = $_POST['dbCreateMethod'];

            $_SESSION['dbInfo'] = $dbInfo;

            $conn = mysqli_connect($dbInfo['dbHostName'], $dbInfo['dbUserName'], $dbInfo['dbPassword'], "", $dbInfo['dbHostPort']);

            if ($conn) {
                $mysqlHost = mysqli_get_server_info($conn);

                if (intval(substr($mysqlHost, 0, 1)) < 4 || substr($mysqlHost, 0, 3) === '4.0') {
                    $error = 'WRONGDBVER';
                } elseif ($_POST['dbCreateMethod'] == 'new' && mysqli_select_db($conn, $dbInfo['dbName'])) {
                    $error = 'DBEXISTS';
                } elseif ($_POST['dbCreateMethod'] == 'new' && !isset($_POST['chkSameUser'])) {

                    mysqli_select_db($conn, 'mysql');
                    $rset = mysqli_query($conn, "SELECT USER FROM user WHERE USER = '" . $dbInfo['dbOHRMUserName'] . "'");

                    if (mysqli_num_rows($rset) > 0) {
                        $error = 'DBUSEREXISTS';
                    } else {
                        $_SESSION['DBCONFIG'] = 'OK';
                    }
                } else {
                    $_SESSION['DBCONFIG'] = 'OK';
                }

                $errorMsg = mysqli_error($conn);
                $mysqlErrNo = mysqli_error($conn);

            } else {
                $error = 'WRONGDBINFO';
                $errorMsg = mysqli_connect_error();
                $mysqlErrNo = mysqli_connect_errno();
            }

            /* For Data Encryption: Begins */

            $_SESSION['ENCRYPTION'] = "Inactive";
            if (isset($_POST['chkEncryption'])) {

                $keyResult = createKeyFile('key.ohrm');
                if ($keyResult) {
                    $_SESSION['ENCRYPTION'] = "Active";
                } else {
                    $_SESSION['ENCRYPTION'] = "Failed";
                }
            }

            /* For Data Encryption: Ends */

            break;

        case 'DEFUSERINFO' :
            $_SESSION['defUser']['AdminUserName'] = trim($_POST['OHRMAdminUserName']);
            $_SESSION['defUser']['AdminPassword'] = trim($_POST['OHRMAdminPassword']);
            $_SESSION['DEFUSER'] = 'OK';
            break;

        case 'CANCEL' : session_destroy();
            header("Location: ./install.php");
            exit(0);
            break;

        case 'BACK' : back($_POST['txtScreen']);
            break;

        case 'CONFIRMED' : $_SESSION['INSTALLING'] = 0;
            break;

        case 'REGISTER' : $_SESSION['CONFDONE'] = 'OK';
            break;


        case 'REGINFO' 	:	$reqAccept = sendRegistrationData($_POST);
							break;

	case 'NOREG' 	:	$reqAccept = sendRegistrationData($_POST);

        case 'LOGIN' : session_destroy();
            setcookie('PHPSESSID', '', time() - 3600, '/');
            header("Location: ./");
            exit(0);
            break;
    }

if (isset($error)) {
    $_SESSION['error'] = $error;
}

if (isset($mysqlErrNo)) {
    $_SESSION['mysqlErrNo'] = $mysqlErrNo;
}

if (isset($errorMsg)) {
    $_SESSION['errorMsg'] = $errorMsg;
}

if (isset($reqAccept)) {
    $_SESSION['reqAccept'] = $reqAccept;
}

if (isset($_SESSION['INSTALLING']) && !isset($_SESSION['UNISTALL'])) {
    include(ROOT_PATH . '/installer/applicationSetup.php');
}

if (isset($_SESSION['UNISTALL'])) {
    include(ROOT_PATH . '/installer/cleanUp.php');
}

header('Location: ./installer/installerUI.php');
?>
