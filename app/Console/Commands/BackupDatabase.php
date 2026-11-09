<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Dump SQL compresso del database in storage/app/backups.
 * Nessuna dipendenza da mysqldump: usa la connessione di Laravel.
 */
class BackupDatabase extends Command
{
    protected $signature = 'backup:database {--keep=14 : Quanti dump conservare}';

    protected $description = 'Creates a compressed SQL dump of the database and removes the oldest ones';

    private const CHUNK = 500;

    public function handle(): int
    {
        $dir = storage_path('app/backups');
        File::ensureDirectoryExists($dir, 0750);

        $name = 'db-'.now()->format('Ymd-His').'.sql.gz';
        $path = $dir.'/'.$name;
        $tmp = $path.'.part';

        $gz = gzopen($tmp, 'wb6');

        if ($gz === false) {
            $this->error('Unable to create '.$tmp);

            return self::FAILURE;
        }

        try {
            $pdo = DB::connection()->getPdo();
            gzwrite($gz, "-- Dump of ".config('database.connections.'.config('database.default').'.database')." at ".now()->toDateTimeString()."\nSET FOREIGN_KEY_CHECKS=0;\nSET NAMES utf8mb4;\n\n");

            $tables = DB::select('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"');

            foreach ($tables as $row) {
                $table = (string) array_values((array) $row)[0];
                $quoted = '`'.str_replace('`', '``', $table).'`';

                $create = (array) DB::selectOne('SHOW CREATE TABLE '.$quoted);
                gzwrite($gz, "DROP TABLE IF EXISTS $quoted;\n".array_values($create)[1].";\n\n");

                DB::table($table)->orderByRaw('1')->chunk(self::CHUNK, function ($rows) use ($gz, $quoted, $pdo): void {
                    $values = [];

                    foreach ($rows as $r) {
                        $values[] = '('.implode(',', array_map(
                            fn ($v) => $v === null ? 'NULL' : (is_int($v) || is_float($v) ? (string) $v : $pdo->quote((string) $v)),
                            array_values((array) $r)
                        )).')';
                    }

                    gzwrite($gz, "INSERT INTO $quoted VALUES\n".implode(",\n", $values).";\n");
                });

                gzwrite($gz, "\n");
            }

            gzwrite($gz, "SET FOREIGN_KEY_CHECKS=1;\n-- End\n");
            gzclose($gz);
        } catch (\Throwable $e) {
            @gzclose($gz);
            @unlink($tmp);
            report($e);
            $this->error('Backup fallito: '.$e->getMessage());

            return self::FAILURE;
        }

        rename($tmp, $path);
        @chmod($path, 0640);

        $old = collect(File::glob($dir.'/db-*.sql.gz'))->sort()->values();
        $excess = $old->count() - max(1, (int) $this->option('keep'));

        if ($excess > 0) {
            $old->take($excess)->each(fn ($f) => @unlink($f));
        }

        $this->info('Backup created: '.$name.' ('.number_format(filesize($path) / 1024, 1).' KB)');

        return self::SUCCESS;
    }
}
