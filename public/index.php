<?php

/*
 * Startpunkten för hela webbtjänsten. ALLA anrop kommer hit.
 * Läser URL:en, t.ex. /acme/blog/posts, och skickar vidare till rätt fil i src/.
 * Ska innehålla så lite kod som möjligt.
 */

require_once __DIR__ . '/../src/core.php';

// /1/wiki/pages -> ['1', 'wiki', 'pages']
$path  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$delar = explode('/', trim($path, '/'));

$foretag = $delar[0] ?? '';   // "1" (används när core.php kan välja företagsdatabas)
$tjanst  = $delar[1] ?? '';   // "wiki"

switch ($tjanst) {
    case 'auth':
        require __DIR__ . '/../src/auth.php';
        break;
    case 'blog':
        require __DIR__ . '/../src/blog.php';
        break;
    case 'calendar':
        require __DIR__ . '/../src/calendar.php';
        break;
    case 'images':
        require __DIR__ . '/../src/images.php';
        break;
    case 'wiki':
        require __DIR__ . '/../src/wiki.php';
        break;
    default:
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Finns inte']);
}
