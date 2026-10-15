<?php
//kopplingar
require_once __DIR__ . '/core.php';
require_once __DIR__ . '/blogg/blogg.php';
require_once __DIR__ . '/calendar/calendar.php';
require_once __DIR__ . '/wiki/wiki.php';


$option = $_GET['option'] ?? $_POST['option'] ?? null;


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
}