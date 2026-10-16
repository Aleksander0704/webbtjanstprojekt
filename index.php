<?php
//kopplingar
require_once __DIR__ . '/core.php';
require_once __DIR__ . '/blogg/blogg.php';
require_once __DIR__ . '/calendar/calendar.php';
require_once __DIR__ . '/wiki/wiki.php';
require_once __DIR__ . '/user/user.php';
require_once __DIR__ . '/theprovidor.php';


$option = $_GET['o'] ?? $_POST['o'] ?? null;
//user input
$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';
$role     = ($_POST['role'] ?? 'user') === 'admin' ? 'admin' : 'user';

switch($option){
    case 'blogg':
        blogg();
        break;
    case 'calendar':
        calendar();
        break;
    case 'wiki':
        wiki();
        break;
    case 'login':
        login();
        break;
    case 'verify':
        verify();
        break;
    case 'logout':
        logout();
        break;
    case 'adduser':
        adduser($username, $password, $role);
        break;
    case 'deluser':
        deluser($username);
        break;
    default:
        echo json_encode(["error" => "Ogiltig option"]);
}