<?php

namespace App\Console\Commands;

use App\Models\Assembly;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncAssemblyVoterCounts extends Command
{
    protected $signature = 'assemblies:sync-counts';
    protected $description = 'Sync total voter counts from MySQL tables into MongoDB assemblies';

    public function handle()
    {
        $assemblies = Assembly::all();
        $this->info("Syncing voter counts for {$assemblies->count()} assemblies...");

        $bar = $this->output->createProgressBar($assemblies->count());

        foreach ($assemblies as $assembly) {
            $tableName = $assembly->table_name;
            if (!$tableName) {
                $bar->advance();
                continue;
            }

            try {
                $totalVoters = DB::connection('mysql_voters')
                    ->table($tableName)
                    ->count();

                // Try to detect mobile column and count
                $columns = DB::connection('mysql_voters')
                    ->getSchemaBuilder()
                    ->getColumnListing($tableName);

                $mobileColumn = null;
                foreach (['mobile', 'phone', 'mobile_no', 'phone_no', 'mob', 'contact', 'mobile_number', 'phone_number'] as $pattern) {
                    foreach ($columns as $col) {
                        if (strtolower($col) === strtolower($pattern)) {
                            $mobileColumn = $col;
                            break 2;
                        }
                    }
                }

                if (!$mobileColumn) {
                    foreach ($columns as $col) {
                        if (preg_match('/mob|phone|contact/i', $col)) {
                            $mobileColumn = $col;
                            break;
                        }
                    }
                }

                $withMobile = 0;
                if ($mobileColumn) {
                    $withMobile = DB::connection('mysql_voters')
                        ->table($tableName)
                        ->whereNotNull($mobileColumn)
                        ->where($mobileColumn, '!=', '')
                        ->count();
                }

                $assembly->update([
                    'total_voters' => $totalVoters,
                    'has_mobile_data' => $withMobile > 0,
                ]);

            } catch (\Exception $e) {
                $this->warn("Failed for AC {$assembly->ac_no} ({$tableName}): {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Voter counts synced successfully!');

        // Clear the cached assemblies
        \Illuminate\Support\Facades\Cache::forget('all_assemblies');

        return 0;
    }
}
