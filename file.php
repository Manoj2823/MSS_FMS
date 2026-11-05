<?php require __DIR__ . '/includes/bootstrap.php';
require_login('documents.view');
$s = db()->prepare('SELECT file_path FROM vehicle_documents WHERE id=? AND deleted_at IS NULL');
$s->execute([(int) ($_GET['id'] ?? 0)]);
$f = $s->fetchColumn();
if (!$f || !preg_match('/^[a-f0-9]{32}\.(pdf|jpg|jpeg|png)$/', $f) || !is_file($p = __DIR__ . '/storage/uploads/' . $f)) {
    http_response_code(404);
    exit('Not found');
}
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($p);
header('Content-Type: ' . $mime);
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: inline; filename="' . $f . '"');
header('Content-Length: ' . filesize($p));
readfile($p);
