<?php
require '../Slim/Slim.php';

\Slim\Slim::registerAutoloader();
$app = new \Slim\Slim();
$app->contentType('application/json');
$app->expires('-1000000');
$db = new PDO('sqlite:db.sqlite3');

function getTitleFromUrl($url)
{
    preg_match('/<title>(.+)<\/title>/', file_get_contents($url), $matches);

    return mb_convert_encoding($matches[1], 'UTF-8', 'UTF-8');
}

function getFaviconFromUrl($url)
{
    $url = parse_url($url);
    $url = urlencode(sprintf('%s://%s', 
        isset($url['scheme']) ? $url['scheme'] : 'http', 
        isset($url['host']) ? $url['host'] : strtolower($url['path'])));
    
    return "http://g.etfv.co/$url";
}

function saveFavicon($url, $id)
{
    file_put_contents("../icons/$id.ico", file_get_contents(getFaviconFromUrl($url)));
}

function returnResult($action, $success = true, $id = 0)
{
    echo json_encode([
        'action' => $action,
        'success' => $success,
        'id' => intval($id),
    ]);
}

$app->get('/bookmark', function () use ($db, $app) {
    $sth = $db->query('SELECT * FROM bookmark;');
    echo json_encode($sth->fetchAll(PDO::FETCH_CLASS));
});

$app->get('/bookmark/:id', function ($id) use ($db, $app) {
    $sth = $db->prepare('SELECT * FROM bookmark WHERE id = ? LIMIT 1;');
    $sth->execute([intval($id)]);
    echo json_encode($sth->fetchAll(PDO::FETCH_CLASS)[0]);
});

$app->post('/bookmark', function () use ($db, $app) {
    $title = $app->request()->post('title');
    $sth = $db->prepare('INSERT INTO bookmark (url, title) VALUES (?, ?);');
    $sth->execute([
        $url = $app->request()->post('url'),
        empty($title) ? getTitleFromUrl($url) : $title,
    ]);
    saveFavicon($url, $id = $db->lastInsertId());

    returnResult('add', $sth->rowCount() == 1, $id);
});

$app->put('/bookmark/:id', function ($id) use ($db, $app) {
    $sth = $db->prepare('UPDATE bookmark SET title = ?, url = ? WHERE id = ?;');
    $sth->execute([
        $app->request()->post('title'),
        $url = $app->request()->post('url'),
        intval($id),
    ]);
    saveFavicon($url, $id);

    returnResult('add', $sth->rowCount() == 1, $id);
});

$app->delete('/bookmark/:id', function ($id) use ($db) {
    $sth = $db->prepare('DELETE FROM bookmark WHERE id = ?;');
    $sth->execute([intval($id)]);

    unlink("../icons/$id.ico");

    returnResult('delete', $sth->rowCount() == 1, $id);
});

$app->get('/install', function () use ($db) {
    $db->exec('	CREATE TABLE IF NOT EXISTS bookmark (
					id INTEGER PRIMARY KEY, 
					title TEXT, 
					url TEXT UNIQUE);');

    returnResult('install');
});

$app->run();