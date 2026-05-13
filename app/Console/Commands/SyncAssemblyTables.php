<?php

namespace App\Console\Commands;

use App\Models\Assembly;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncAssemblyTables extends Command
{
    protected $signature = 'assemblies:sync-tables';
    protected $description = 'Detect and sync MySQL voter table names with assembly records in MongoDB';

    public function handle(): int
    {
        $this->info('Scanning MySQL voter database for assembly tables...');

        try {
            $tables = DB::connection('mysql_voters')
                ->select('SHOW TABLES');
        } catch (\Exception $e) {
            $this->error('Cannot connect to MySQL voter database: ' . $e->getMessage());
            return 1;
        }

        $dbName = config('database.connections.mysql_voters.database');
        $columnKey = "Tables_in_{$dbName}";

        $tablNames = [];
        foreach ($tables as $table) {
            $name = $table->$columnKey ?? null;
            if ($name) {
                $tablNames[] = $name;
            }
        }

        $this->info("Found " . count($tablNames) . " tables in MySQL.");

        $matched = 0;
        $assemblies = Assembly::all();

        foreach ($assemblies as $assembly) {
            $acNo = $assembly->ac_no;

            // Try common naming patterns
            $patterns = [
                "ac_{$acNo}",
                "AC_{$acNo}",
                "assembly_{$acNo}",
                "voters_{$acNo}",
                "{$acNo}",
                strtolower(str_replace(' ', '_', $assembly->constituency)),
                "ac" . str_pad($acNo, 3, '0', STR_PAD_LEFT),
            ];

            $found = false;
            foreach ($patterns as $pattern) {
                if (in_array($pattern, $tablNames)) {
                    $assembly->update(['table_name' => $pattern, 'has_mobile_data' => true]);
                    $matched++;
                    $found = true;
                    $this->line("  AC {$acNo} ({$assembly->constituency}) => {$pattern}");
                    break;
                }
            }

            if (!$found) {
                // Fuzzy match: check if any table contains the AC number
                foreach ($tablNames as $tbl) {
                    if (preg_match("/(\b|_){$acNo}(\b|_|$)/", $tbl)) {
                        $assembly->update(['table_name' => $tbl, 'has_mobile_data' => true]);
                        $matched++;
                        $this->line("  AC {$acNo} ({$assembly->constituency}) => {$tbl} (fuzzy)");
                        $found = true;
                        break;
                    }
                }
            }

            if (!$found) {
                $assembly->update(['has_mobile_data' => false]);
                $this->warn("  AC {$acNo} ({$assembly->constituency}) => NO TABLE FOUND");
            }
        }

        $this->info("Matched {$matched} / {$assemblies->count()} assemblies to MySQL tables.");
        return 0;
    }
}
