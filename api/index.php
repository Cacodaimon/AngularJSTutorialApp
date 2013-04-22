<?php
require '../Slim/Slim.php';

\Slim\Slim::registerAutoloader();
$app = new \Slim\Slim();
$app->contentType('application/json');
$db = new PDO('sqlite:db.sqlite3');

function getTitleFromUrl($url)
{
    preg_match('/<title>(.+)<\/title>/', file_get_contents($url), $matches);
    return mb_convert_encoding($matches[1], 'UTF-8', 'UTF-8');
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

    returnResult('add', $sth->rowCount() == 1, $db->lastInsertId());
});

$app->put('/bookmark/:id', function ($id) use ($db, $app) {
    $sth = $db->prepare('UPDATE bookmark SET title = ?, url = ? WHERE id = ?;');
    $sth->execute([
        $app->request()->post('title'),
        $app->request()->post('url'),
        intval($id),
    ]);

    returnResult('edit', $sth->rowCount() == 1, $id);
});

$app->delete('/bookmark/:id', function ($id) use ($db) {
    $sth = $db->prepare('DELETE FROM bookmark WHERE id = ?;');
    $sth->execute([intval($id)]);

    returnResult('delete', $sth->rowCount() == 1, $id);
});

$app->get('/urlfopen', function () {
    returnResult('urlfopen', ini_get('allow_url_fopen'));
});

$app->get('/install', function () use ($db) {
    $db->exec('	CREATE TABLE IF NOT EXISTS bookmark (
					id INTEGER PRIMARY KEY, 
					title TEXT, 
					url TEXT UNIQUE);');

    returnResult('install');
});

$app->run();