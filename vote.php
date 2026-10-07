<?php
// Enregistrement d'un vote Pour / Neutre / Contre (Story 10)
require_once __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}
verify_csrf();

$back = safe_redirect_target($_POST['redirect'] ?? null);
$user = current_user();
if (!$user) {
    flash('info', 'Connectez-vous pour participer aux votes.');
    redirect('login.php?redirect=' . urlencode($back));
}

$requestId = (int) ($_POST['request_id'] ?? 0);
$value = $_POST['value'] ?? '';
$request = find_request($requestId);

if (!$request || !in_array($value, ['pro', 'neutral', 'con'], true)) {
    flash('danger', 'Vote invalide.');
    redirect($back);
}
if (!in_array($request['status'], ['voting', 'threshold'], true) || $request['is_reported']) {
    flash('warning', "Le vote n'est pas ouvert pour cette requête.");
    redirect($back);
}

$previous = user_vote($requestId, (int) $user['id']);
$stmt = db()->prepare('
    INSERT INTO votes (request_id, user_id, value) VALUES (?, ?, ?)
    ON DUPLICATE KEY UPDATE value = VALUES(value), created_at = NOW()
');
$stmt->execute([$requestId, $user['id'], $value]);

$labels = ['pro' => 'POUR 👍', 'neutral' => 'NEUTRE ⚖️', 'con' => 'CONTRE 👎'];
flash('success', $previous === null
    ? 'Votre vote ' . $labels[$value] . ' a bien été enregistré.'
    : 'Votre vote a été modifié : ' . $labels[$value] . '.');

check_threshold($requestId);
redirect($back);
