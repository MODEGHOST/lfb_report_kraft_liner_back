<?php

function erp_pull_today()
{
    $now = new DateTime('now', new DateTimeZone('Asia/Bangkok'));
    return $now->format('Y-m-d');
}

function erp_pull_ensure_table(PDO $pdo)
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS kraft_erp_pull (
            pull_date DATE NOT NULL,
            pulled_at DATETIME NOT NULL,
            username VARCHAR(100) NULL,
            display_name VARCHAR(255) NULL,
            status VARCHAR(20) NOT NULL,
            progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
            message TEXT NULL,
            PRIMARY KEY (pull_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    $have = array();
    foreach ($pdo->query('SHOW COLUMNS FROM kraft_erp_pull') as $col) {
        $have[$col['Field']] = true;
    }
    if (!isset($have['progress_percent'])) {
        $pdo->exec(
            'ALTER TABLE kraft_erp_pull
             ADD COLUMN progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER status'
        );
    }
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS kraft_erp_pull_log (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            pull_date DATE NOT NULL,
            started_at DATETIME NOT NULL,
            finished_at DATETIME NULL,
            username VARCHAR(100) NULL,
            display_name VARCHAR(255) NULL,
            status VARCHAR(20) NOT NULL,
            message TEXT NULL,
            PRIMARY KEY (id),
            KEY idx_erp_pull_log_started (started_at),
            KEY idx_erp_pull_log_date (pull_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    $pdo->exec(
        'INSERT INTO kraft_erp_pull_log
            (pull_date, started_at, finished_at, username, display_name, status, message)
         SELECT p.pull_date, p.pulled_at,
                CASE WHEN p.status = \'running\' THEN NULL ELSE p.pulled_at END,
                p.username, p.display_name, p.status, p.message
         FROM kraft_erp_pull p
         WHERE NOT EXISTS (
             SELECT 1 FROM kraft_erp_pull_log l WHERE l.pull_date = p.pull_date
         )'
    );
}

function erp_pull_expire_stale(PDO $pdo)
{
    $pdo->exec(
        'UPDATE kraft_erp_pull_log
         SET status = \'failed\',
             finished_at = NOW(),
             message = IF(message IS NULL OR message = \'\', \'งานดึงข้อมูลค้างเกินกำหนด\', message)
         WHERE status = \'running\' AND started_at < DATE_SUB(NOW(), INTERVAL 2 HOUR)'
    );
    $pdo->exec(
        'UPDATE kraft_erp_pull
         SET status = \'failed\',
             message = IF(message IS NULL OR message = \'\', \'งานดึงข้อมูลค้างเกินกำหนด\', message)
         WHERE status = \'running\' AND pulled_at < DATE_SUB(NOW(), INTERVAL 2 HOUR)'
    );
}

function erp_pull_history(PDO $pdo, $limit = 50)
{
    $limit = (int) $limit;
    if ($limit < 1) {
        $limit = 1;
    }
    if ($limit > 200) {
        $limit = 200;
    }
    $stmt = $pdo->query(
        'SELECT id, pull_date, started_at, finished_at, username, display_name, status, message
         FROM kraft_erp_pull_log
         ORDER BY id DESC
         LIMIT ' . $limit
    );
    $rows = array();
    while ($row = $stmt->fetch()) {
        $rows[] = array(
            'id' => (int) $row['id'],
            'pull_date' => $row['pull_date'],
            'started_at' => $row['started_at'],
            'finished_at' => $row['finished_at'],
            'username' => $row['username'],
            'display_name' => $row['display_name'],
            'status' => $row['status'],
            'message' => $row['message'],
        );
    }
    return $rows;
}

function erp_pull_row(PDO $pdo, $date)
{
    $stmt = $pdo->prepare(
        'SELECT pull_date, pulled_at, username, display_name, status, progress_percent, message
         FROM kraft_erp_pull
         WHERE pull_date = ?'
    );
    $stmt->execute(array($date));
    $row = $stmt->fetch();
    return $row ? $row : null;
}

function erp_pull_is_fresh_running($row)
{
    if (!$row || $row['status'] !== 'running' || empty($row['pulled_at'])) {
        return false;
    }
    $started = strtotime($row['pulled_at']);
    if ($started === false) {
        return false;
    }
    return (time() - $started) < 7200;
}

function erp_pull_latest_success(PDO $pdo)
{
    $stmt = $pdo->query(
        'SELECT pull_date, pulled_at, username, display_name, status, progress_percent, message
         FROM kraft_erp_pull
         WHERE status = \'success\'
         ORDER BY pulled_at DESC
         LIMIT 1'
    );
    $row = $stmt->fetch();
    return $row ? $row : null;
}

/** ครบ 24 ชม. นับจาก pulled_at ของรอบดึงมือล่าสุดที่สำเร็จ */
function erp_pull_manual_cooldown_until($successRow)
{
    if (!$successRow || $successRow['status'] !== 'success' || empty($successRow['pulled_at'])) {
        return null;
    }
    $started = strtotime($successRow['pulled_at']);
    if ($started === false) {
        return null;
    }
    return $started + 86400;
}

function erp_pull_next_manual_at($successRow)
{
    $until = erp_pull_manual_cooldown_until($successRow);
    if ($until === null || time() >= $until) {
        return null;
    }
    return date('Y-m-d H:i:s', $until);
}

function erp_pull_payload($row, $history = null, $latestSuccess = null)
{
    $allowed = true;
    $reason = '';
    $cooldownUntil = erp_pull_manual_cooldown_until($latestSuccess);
    $inCooldown = $cooldownUntil !== null && time() < $cooldownUntil;

    if ($row && erp_pull_is_fresh_running($row)) {
        $allowed = false;
        $reason = 'กำลังดึงข้อมูลอยู่';
    } elseif ($inCooldown) {
        $allowed = false;
        $reason = 'ยังไม่ครบ 24 ชั่วโมงหลังดึงครั้งล่าสุด';
    }

    $display = $row;
    if (!$display && $inCooldown && $latestSuccess) {
        $display = $latestSuccess;
    }

    $payload = array(
        'pull_date' => erp_pull_today(),
        'allowed' => $allowed,
        'reason' => $reason,
        'next_manual_at' => erp_pull_next_manual_at($latestSuccess),
        'last' => $display ? array(
            'pulled_at' => $display['pulled_at'],
            'username' => $display['username'],
            'display_name' => $display['display_name'],
            'status' => $display['status'],
            'progress_percent' => isset($display['progress_percent']) ? (int) $display['progress_percent'] : 0,
            'message' => $display['message'],
        ) : null,
    );
    if ($history !== null) {
        $payload['history'] = $history;
    }
    return $payload;
}

function erp_pull_claim(PDO $pdo, $username, $displayName)
{
    $today = erp_pull_today();
    $pdo->beginTransaction();
    try {
        $latestSuccess = erp_pull_latest_success($pdo);
        $cooldownUntil = erp_pull_manual_cooldown_until($latestSuccess);
        if ($cooldownUntil !== null && time() < $cooldownUntil) {
            $pdo->rollBack();
            return 'ยังไม่ครบ 24 ชั่วโมง ครั้งถัดไปได้ตั้งแต่ ' . date('Y-m-d H:i', $cooldownUntil);
        }

        $stmt = $pdo->prepare(
            'SELECT pull_date, pulled_at, username, display_name, status, progress_percent, message
             FROM kraft_erp_pull
             WHERE pull_date = ?
             FOR UPDATE'
        );
        $stmt->execute(array($today));
        $row = $stmt->fetch();
        if ($row && erp_pull_is_fresh_running($row)) {
            $pdo->rollBack();
            return 'กำลังดึงข้อมูลอยู่';
        }
        if ($row) {
            $update = $pdo->prepare(
                'UPDATE kraft_erp_pull
                 SET pulled_at = NOW(), username = ?, display_name = ?, status = ?, progress_percent = 0, message = ?
                 WHERE pull_date = ?'
            );
            $update->execute(array($username, $displayName, 'running', '', $today));
        } else {
            $insert = $pdo->prepare(
                'INSERT INTO kraft_erp_pull
                    (pull_date, pulled_at, username, display_name, status, progress_percent, message)
                 VALUES (?, NOW(), ?, ?, ?, 0, ?)'
            );
            $insert->execute(array($today, $username, $displayName, 'running', ''));
        }
        $closeLog = $pdo->prepare(
            'UPDATE kraft_erp_pull_log
             SET status = ?, finished_at = NOW(),
                 message = IF(message IS NULL OR message = \'\', ?, message)
             WHERE pull_date = ? AND status = ?'
        );
        $closeLog->execute(array('failed', 'เริ่มดึงรอบใหม่', $today, 'running'));
        $insertLog = $pdo->prepare(
            'INSERT INTO kraft_erp_pull_log
                (pull_date, started_at, username, display_name, status, message)
             VALUES (?, NOW(), ?, ?, ?, ?)'
        );
        $insertLog->execute(array($today, $username, $displayName, 'running', ''));
        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return 'เริ่มดึงข้อมูลไม่สำเร็จ';
    }
    return null;
}

function erp_pull_set_progress(PDO $pdo, $percent, $note)
{
    $percent = (int) $percent;
    if ($percent < 0) {
        $percent = 0;
    }
    if ($percent > 100) {
        $percent = 100;
    }
    $today = erp_pull_today();
    $stmt = $pdo->prepare(
        'UPDATE kraft_erp_pull
         SET progress_percent = ?, message = ?
         WHERE pull_date = ? AND status = ?'
    );
    $stmt->execute(array($percent, $note, $today, 'running'));
    $log = $pdo->prepare(
        'UPDATE kraft_erp_pull_log
         SET message = ?
         WHERE pull_date = ? AND status = ?
         ORDER BY id DESC
         LIMIT 1'
    );
    $log->execute(array($note, $today, 'running'));
}

function erp_pull_finish_if_running(PDO $pdo, $status, $message)
{
    $today = erp_pull_today();
    $stmt = $pdo->prepare(
        'UPDATE kraft_erp_pull
         SET status = ?, message = ?, progress_percent = IF(? = \'success\', 100, progress_percent), pulled_at = NOW()
         WHERE pull_date = ? AND status = ?'
    );
    $stmt->execute(array($status, $message, $status, $today, 'running'));
    $log = $pdo->prepare(
        'UPDATE kraft_erp_pull_log
         SET status = ?, message = ?, finished_at = NOW()
         WHERE pull_date = ? AND status = ?
         ORDER BY id DESC
         LIMIT 1'
    );
    $log->execute(array($status, $message, $today, 'running'));
}
