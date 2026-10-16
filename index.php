<?php
//kopplingar
require_once __DIR__ . '/core.php';
require_once __DIR__ . '/blogg/blogg.php';
require_once __DIR__ . '/calendar/calendar.php';
require_once __DIR__ . '/wiki/wiki.php';

require_once __DIR__ . '/user/user.php';
require_once __DIR__ . '/user/usertoken.php';

require_once __DIR__ . '/theprovidor.php';


$option = $_GET['o'] ?? $_POST['o'] ?? null;
//user input
$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';
$role     = ($_POST['role'] ?? 'user') === 'admin' ? 'admin' : 'user';

//token input
$token = $_POST['token'] ?? '';

switch($option){
    //test
    case 'calendar':
        checkService(1);
        calendar();
        break;
    case 'blogg':
        checkService(2);
        blogg();
        break;
    case 'wiki':
        checkService(4);
        wiki();
        break;
    //theprovidor
    case 'login':
        login();
        break;
    case 'verify':
        verify();
        break;
    case 'logout':
        logout();
        break;
    //user
    case 'adduser':
        adduser($username, $password, $role);
        break;
    case 'deluser':
        deluser($username);
        break;
    case 'listusers':
        listusers();
        break;
    //user token
    case 'createtoken':
        createToken($username, $password);
        break;
    /*
    case 'listtokens':
        listtoken();
        break;
    */
    case 'verifytoken':
        verifyToken($token);
        break;
    case 'userlogout':
        userlogout($token);
        break;
    default:
        echo json_encode(["error" => "Ogiltig option"]);
}