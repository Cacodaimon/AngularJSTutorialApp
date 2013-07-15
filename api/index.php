<?php
require '../Slim/Slim.php';

\Slim\Slim::registerAutoloader();
$app = new \Slim\Slim();
$app->contentType('application/json');
$app->expires('-1000000');
$app->config('debug', true);

$app->container->singleton('db', function () {
    return new PDO('sqlite:db.sqlite3');
});

$app->returnResult = function($data, $success = true, $id = 0) {
    echo json_encode(is_array($data) ? $data : [
         'action'    => $data,
         'success'   => $success,
         'id'        => $id,
    ], JSON_NUMERIC_CHECK);
};

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

$app->group('/bookmark', function () use ($app) {
    $app->get('', function () use ($app) {
        $sth = $app->db->query('SELECT * FROM bookmark;');
        $app->returnResult($sth->fetchAll(PDO::FETCH_CLASS));
    });

    $app->get('/:id', function ($id) use ($app) {
        $sth = $app->db->prepare('SELECT * FROM bookmark WHERE id = ? LIMIT 1;');
        $sth->execute([$id]);
        $app->returnResult($sth->fetchAll(PDO::FETCH_CLASS)[0]);
    });

    $app->post('', function () use ($app) {
        $title = $app->request->post('title');
        $sth = $app->db->prepare('INSERT INTO bookmark (url, title) VALUES (?, ?);');
        $sth->execute([
            $url = $app->request->post('url'),
            empty($title) ? getTitleFromUrl($url) : $title,
        ]);
        saveFavicon($url, $id = $app->db->lastInsertId());

        $app->returnResult('add', $sth->rowCount() == 1, $id);
    });

    $app->put('/:id', function ($id) use ($app) {
        $sth = $app->db->prepare('UPDATE bookmark SET title = ?, url = ? WHERE id = ?;');
        $sth->execute([
            $app->request->put('title'),
            $url = $app->request->put('url'),
            $id,
        ]);
        saveFavicon($url, $id);

        $app->returnResult('add', $sth->rowCount() == 1, $id);
    });

    $app->delete('/:id', function ($id) use ($app) {
        $sth = $app->db->prepare('DELETE FROM bookmark WHERE id = ?;');
        $sth->execute([$id]);

        unlink("../icons/$id.ico");

        $app->returnResult('delete', $sth->rowCount() == 1, $id);
    });
});

$app->get('/install', function () use ($app) {
    $app->db->exec('	CREATE TABLE IF NOT EXISTS bookmark (
                        id INTEGER PRIMARY KEY,
                        title TEXT,
                        url TEXT UNIQUE);');

    $app->returnResult('install');
});

$app->run();