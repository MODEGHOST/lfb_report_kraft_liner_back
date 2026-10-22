<?php
$local = new PDO(
    'mysql:host=127.0.0.1;dbname=report_kraft_liner;charset=utf8mb4',
    'root',
    '',
    array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
);
$gap = round((28400.40 / 1183.31) - (21236.00 / 1295.00), 2);
$stmt = $local->prepare(
    'UPDATE kraft_monthly_sales SET gap = ?, synced_at = NOW()
     WHERE year = 2026 AND month = 7 AND customer_code = \'CTD-00446\' AND gap IS NULL'
);
$stmt->execute(array($gap));
echo "gap={$gap} updated=" . $stmt->rowCount() . "\n";
