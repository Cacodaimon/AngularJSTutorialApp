<?php
require '../Slim/Slim.php';

\Slim\Slim::registerAutoloader();
$app = new \Slim\Slim();
$app->contentType('application/json');
$app->expires('-1000000');

$app->container->singleton('db', function () {
    return new PDO('sqlite:db.sqlite3');
});

function returnResult($data, $success = true, $id = 0) {
    echo json_encode(is_array($data) || is_object($data) ? $data : [
        'action'    => $data,
        'success'   => $success,
        'id'        => $id,
    ], JSON_NUMERIC_CHECK);
};

function getTitleFromUrl($url)
{
    preg_match('/<title>(.+)<\/title>/', file_get_contents($url), $matches);

    return empty($matches) ? $url : mb_convert_encoding($matches[1], 'UTF-8', 'UTF-8');
}

function saveFavicon($url, $id)
{
    $url = parse_url($url);
    $url = urlencode(sprintf('%s://%s',
            isset($url['scheme']) ? $url['scheme'] : 'http',
            isset($url['host']) ? $url['host'] : strtolower($url['path'])));

    copy("http://g.etfv.co/$url", "../icons/$id.ico");
}

$app->group('/bookmark', function () use ($app) {
    $app->get('', function () use ($app) {
        $sth = $app->db->query('SELECT * FROM bookmark;');
        returnResult($sth->fetchAll(PDO::FETCH_CLASS));
    });

    $app->get('/:id', function ($id) use ($app) {
        $sth = $app->db->prepare('SELECT * FROM bookmark WHERE id = ? LIMIT 1;');
        $sth->execute([$id]);
        returnResult($sth->fetchAll(PDO::FETCH_CLASS)[0]);
    });

    $app->post('', function () use ($app) {
        $title = $app->request->post('title');
        $sth = $app->db->prepare('INSERT INTO bookmark (url, title) VALUES (?, ?);');
        $sth->execute([
            $url = $app->request->post('url'),
            empty($title) ? getTitleFromUrl($url) : $title,
        ]);
        saveFavicon($url, $id = $app->db->lastInsertId());

        returnResult('add', $sth->rowCount() == 1, $id);
    });

    $app->put('/:id', function ($id) use ($app) {
        $sth = $app->db->prepare('UPDATE bookmark SET title = ?, url = ? WHERE id = ?;');
        $sth->execute([
            $app->request->put('title'),
            $url = $app->request->put('url'),
            $id,
        ]);
        saveFavicon($url, $id);

        returnResult('add', $sth->rowCount() == 1, $id);
    });

    $app->delete('/:id', function ($id) use ($app) {
        $sth = $app->db->prepare('DELETE FROM bookmark WHERE id = ?;');
        $sth->execute([$id]);
        unlink("../icons/$id.ico");

        returnResult('delete', $sth->rowCount() == 1, $id);
    });
});

$app->get('/install', function () use ($app) {
    $app->db->exec('	CREATE TABLE IF NOT EXISTS bookmark (
                        id INTEGER PRIMARY KEY,
                        title TEXT,
                        url TEXT UNIQUE);');

    returnResult('install');
});

$app->run();