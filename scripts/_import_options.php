<?php
function flag01($text, $yesText)
{
    $text = trim((string) $text);
    if ($text === '') {
        return null;
    }
    if ($text === '1' || $text === $yesText) {
        return 1;
    }
    if ($text === '0' || $text === 'ไม่ได้เลือก') {
        return 0;
    }
    return null;
}

$pdo = new PDO('mysql:host=127.0.0.1;dbname=report_kraft_liner;charset=utf8mb4', 'root', '', array(
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
));
$pdo->exec('DROP TABLE IF EXISTS kraft_customer_options');
$pdo->exec(
    'CREATE TABLE kraft_customer_options (
        id INT NOT NULL AUTO_INCREMENT,
        customer_code VARCHAR(30) NULL,
        customer_name VARCHAR(255) NOT NULL,
        use_pallet TINYINT NULL,
        pallet_paper TINYINT NULL,
        rope TINYINT NULL,
        film TINYINT NULL,
        loaded_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY idx_customer_code (customer_code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
);

$raw = file_get_contents(dirname(__FILE__) . '/_options.tsv');
$raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
$lines = preg_split('/\r\n|\n|\r/', $raw);

$insert = $pdo->prepare(
    'INSERT INTO kraft_customer_options
     (customer_code, customer_name, use_pallet, pallet_paper, rope, film, loaded_at)
     VALUES (?, ?, ?, ?, ?, ?, NOW())'
);

$seen = array();
$n = 0;
$use1 = 0;
$paper1 = 0;
$rope1 = 0;
$film1 = 0;
$unknown = 0;
foreach ($lines as $line) {
    if ($line === '') {
        continue;
    }
    $parts = explode("\t", $line);
    $code = isset($parts[0]) ? trim($parts[0]) : '';
    $name = isset($parts[1]) ? trim($parts[1]) : '';
    if ($code === '') {
        $unknown++;
    }
    $use = flag01(isset($parts[2]) ? $parts[2] : '', 'ใช้รองเลท');
    $paper = flag01(isset($parts[3]) ? $parts[3] : '', '1');
    $rope = flag01(isset($parts[4]) ? $parts[4] : '', '1');
    $film = flag01(isset($parts[5]) ? $parts[5] : '', '1');
    $insert->execute(array($code === '' ? null : $code, $name, $use, $paper, $rope, $film));
    if ($code !== '') {
        $seen[$code] = true;
    }
    $n++;
    if ($use === 1) $use1++;
    if ($paper === 1) $paper1++;
    if ($rope === 1) $rope1++;
    if ($film === 1) $film1++;
}

echo 'stored=' . $n . PHP_EOL;
echo 'blank_code_skipped=' . $unknown . PHP_EOL;
echo 'use_pallet_1=' . $use1 . PHP_EOL;
echo 'pallet_paper_1=' . $paper1 . PHP_EOL;
echo 'rope_1=' . $rope1 . PHP_EOL;
echo 'film_1=' . $film1 . PHP_EOL;

$ones = $pdo->query(
    "SELECT customer_code, customer_name, rope, film
     FROM kraft_customer_options
     WHERE rope = 1 OR film = 1
     ORDER BY customer_code"
)->fetchAll(PDO::FETCH_ASSOC);
foreach ($ones as $row) {
    echo 'special=' . $row['customer_code'] . "\t" . $row['rope'] . "\t" . $row['film'] . "\t" . $row['customer_name'] . PHP_EOL;
}
$om = $pdo->query("SELECT customer_code, use_pallet, pallet_paper, rope, film FROM kraft_customer_options WHERE customer_code = 'CTR-00009'")->fetch(PDO::FETCH_ASSOC);
echo 'ctr00009=';
echo $om ? json_encode($om, JSON_UNESCAPED_UNICODE) : 'missing';
echo PHP_EOL;
