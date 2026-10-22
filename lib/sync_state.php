<?php

function sync_ensure_purchase_columns($pdo)
{
    $have = array();
    foreach ($pdo->query('SHOW COLUMNS FROM kraft_monthly_sales') as $col) {
        $have[$col['Field']] = true;
    }
    if (!isset($have['purchase_amount'])) {
        $pdo->exec('ALTER TABLE kraft_monthly_sales ADD COLUMN purchase_amount DECIMAL(18,2) NULL AFTER weight_kg');
    }
    if (!isset($have['purchase_weight'])) {
        $pdo->exec('ALTER TABLE kraft_monthly_sales ADD COLUMN purchase_weight DECIMAL(18,2) NULL AFTER purchase_amount');
    }
}

function sync_ensure_seen_table($pdo)
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS kraft_sales_seen (
            source_id BIGINT NOT NULL,
            PRIMARY KEY (source_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
}

function sync_remember_sales_ids($pdo, $ids)
{
    if (!$ids) {
        return 0;
    }
    $stored = 0;
    $chunk = array();
    foreach ($ids as $id) {
        $chunk[] = (int) $id;
        if (count($chunk) === 400) {
            $stored += sync_insert_seen_ids($pdo, $chunk);
            $chunk = array();
        }
    }
    if ($chunk) {
        $stored += sync_insert_seen_ids($pdo, $chunk);
    }
    return $stored;
}

function sync_insert_seen_ids($pdo, $ids)
{
    $values = array();
    $params = array();
    foreach ($ids as $id) {
        $values[] = '(?)';
        $params[] = (int) $id;
    }
    $sql = 'INSERT IGNORE INTO kraft_sales_seen (source_id) VALUES ' . implode(',', $values);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}
