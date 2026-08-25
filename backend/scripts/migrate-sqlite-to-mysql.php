<?php

// Migração de dados SQLite -> MySQL.
//
// Uso:
//   php scripts/migrate-sqlite-to-mysql.php            # idempotente: pula tabelas já migradas
//   php scripts/migrate-sqlite-to-mysql.php --force    # trunca e recopia tabelas divergentes
//
// Conexões:
//   Origem  -> DB_SQLITE_PATH (padrão: database/database.sqlite)
//   Destino -> DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD
//
// Regras:
//   - O schema do destino deve existir (rode `php artisan migrate --force` primeiro).
//   - Sem --force: contagem igual na origem e no destino => SKIP (já migrada);
//     contagem divergente => ABORTA.
//   - Com --force: recopia TODAS as tabelas (trunca e reinsere), sem skip —
//     o conteúdo pode divergir mesmo com contagens iguais (ex.: re-seed).
//   - Ao final, confere a contagem de cada tabela e aborta em divergência.

$force = in_array('--force', $argv, true);

$sqlitePath = getenv('DB_SQLITE_PATH') ?: __DIR__ . '/../database/database.sqlite';
if (!is_file($sqlitePath)) {
    fwrite(STDERR, "ERRO: arquivo SQLite não encontrado em {$sqlitePath}\n");
    exit(1);
}

$sqlite = new PDO('sqlite:' . $sqlitePath);
$sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sqlite->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$mysqlDsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
    getenv('DB_HOST') ?: '127.0.0.1',
    getenv('DB_PORT') ?: '3306',
    getenv('DB_DATABASE') ?: 'interlinkedlog',
);
$mysql = new PDO($mysqlDsn, getenv('DB_USERNAME') ?: 'root', getenv('DB_PASSWORD') ?: '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4',
]);

$tables = [
    'companies',
    'users',
    'carriers',
    'freight_tables',
    'freight_table_routes',
    'freight_table_weight_ranges',
    'freight_table_fees',
    'subscriptions',
    'quotations',
    'quotation_results',
    'contracts',
    'tracking_events',
    'audit_logs',
    'system_logs',
];

$failed = false;

foreach ($tables as $table) {
    $sourceCount = (int) $sqlite->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
    $targetCount = (int) $mysql->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();

    if (!$force && $targetCount === $sourceCount && $sourceCount > 0) {
        echo "SKIP  {$table}: já migrada ({$targetCount} registros)\n";
        continue;
    }

    if ($targetCount > 0 && !$force) {
        fwrite(STDERR, "ABORTAR: {$table} tem {$targetCount} registros no MySQL e a origem tem {$sourceCount}. Use --force para truncar e recopiar.\n");
        $failed = true;
        continue;
    }

    if ($targetCount > 0) {
        $mysql->exec('SET FOREIGN_KEY_CHECKS = 0');
        $mysql->exec("TRUNCATE TABLE `{$table}`");
        $mysql->exec('SET FOREIGN_KEY_CHECKS = 1');
        echo "TRUNC {$table}: recopiando ({$sourceCount} registros)\n";
    }

    $columns = [];
    foreach ($sqlite->query("PRAGMA table_info({$table})") as $col) {
        $columns[] = $col['name'];
    }
    if ($columns === []) {
        fwrite(STDERR, "ABORTAR: não foi possível ler as colunas de {$table}\n");
        $failed = true;
        continue;
    }

    $colList = '`' . implode('`, `', $columns) . '`';
    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
    $insert = $mysql->prepare("INSERT INTO `{$table}` ({$colList}) VALUES ({$placeholders})");

    $rows = $sqlite->query("SELECT * FROM {$table}");
    $copied = 0;
    foreach ($rows as $row) {
        $insert->execute(array_values($row));
        $copied++;
    }

    $finalCount = (int) $mysql->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
    if ($finalCount !== $sourceCount) {
        fwrite(STDERR, "ABORTAR: {$table} — origem {$sourceCount}, destino após cópia {$finalCount}\n");
        $failed = true;
        continue;
    }

    echo "OK    {$table}: {$finalCount} registros\n";
}

if ($failed) {
    fwrite(STDERR, "\nMigração abortada com divergências — nada mais foi copiado.\n");
    exit(1);
}

echo "\nMigração concluída com sucesso.\n";