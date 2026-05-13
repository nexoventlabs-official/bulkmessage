<?php

namespace App\Services;

use App\Models\Assembly;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class VoterService
{
    /**
     * Get all assemblies from MongoDB (seeded from Excel)
     */
    public function getAllAssemblies(): array
    {
        return Cache::remember('all_assemblies', 3600, function () {
            return Assembly::orderBy('ac_no', 'asc')->get()->toArray();
        });
    }

    /**
     * Get assemblies grouped by zone and district
     */
    public function getAssembliesGrouped(): array
    {
        $assemblies = $this->getAllAssemblies();
        $grouped = [];

        foreach ($assemblies as $assembly) {
            $zone = $assembly['zone'] ?? 'Unknown';
            $district = $assembly['district'] ?? 'Unknown';
            $grouped[$zone][$district][] = $assembly;
        }

        return $grouped;
    }

    /**
     * Get mobile numbers from a specific assembly table in MySQL
     * Uses cursor/chunking for memory efficiency with large datasets
     */
    public function getVoterMobilesFromAssembly(int $acNo, int $offset = 0, int $limit = 500): array
    {
        $assembly = Assembly::where('ac_no', $acNo)->first();
        if (!$assembly || !$assembly->table_name) {
            return ['data' => [], 'total' => 0, 'has_more' => false];
        }

        $tableName = $assembly->table_name;

        try {
            // Try multiple possible mobile column names
            $columns = $this->detectMobileColumn($tableName);

            if (!$columns) {
                Log::warning("No mobile column found in table: {$tableName}");
                return ['data' => [], 'total' => 0, 'has_more' => false];
            }

            $mobileColumn = $columns['mobile'];
            $idColumn = $columns['id'] ?? 'id';

            // Get total count with mobile numbers
            $total = DB::connection('mysql_voters')
                ->table($tableName)
                ->whereNotNull($mobileColumn)
                ->where($mobileColumn, '!=', '')
                ->count();

            // Get batch of mobile numbers
            $voters = DB::connection('mysql_voters')
                ->table($tableName)
                ->select([$idColumn . ' as voter_id', $mobileColumn . ' as mobile'])
                ->whereNotNull($mobileColumn)
                ->where($mobileColumn, '!=', '')
                ->orderBy($idColumn)
                ->offset($offset)
                ->limit($limit)
                ->get()
                ->toArray();

            return [
                'data' => $voters,
                'total' => $total,
                'has_more' => ($offset + $limit) < $total,
                'next_offset' => $offset + $limit,
            ];
        } catch (\Exception $e) {
            Log::error("Failed to read voters from {$tableName}: {$e->getMessage()}");
            return ['data' => [], 'total' => 0, 'has_more' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Count total voters with mobile numbers across multiple assemblies
     */
    public function countVotersWithMobile(array $acNumbers): array
    {
        $result = ['total_voters' => 0, 'total_with_mobile' => 0, 'assembly_counts' => []];

        foreach ($acNumbers as $acNo) {
            $cacheKey = "voter_count_ac_{$acNo}";
            $counts = Cache::remember($cacheKey, 1800, function () use ($acNo) {
                $assembly = Assembly::where('ac_no', $acNo)->first();
                if (!$assembly || !$assembly->table_name) {
                    return ['total' => 0, 'with_mobile' => 0];
                }

                try {
                    $tableName = $assembly->table_name;
                    $columns = $this->detectMobileColumn($tableName);

                    if (!$columns) return ['total' => 0, 'with_mobile' => 0];

                    $total = DB::connection('mysql_voters')
                        ->table($tableName)->count();

                    $withMobile = DB::connection('mysql_voters')
                        ->table($tableName)
                        ->whereNotNull($columns['mobile'])
                        ->where($columns['mobile'], '!=', '')
                        ->count();

                    return ['total' => $total, 'with_mobile' => $withMobile];
                } catch (\Exception $e) {
                    Log::error("Count failed for AC {$acNo}: {$e->getMessage()}");
                    return ['total' => 0, 'with_mobile' => 0];
                }
            });

            $result['total_voters'] += $counts['total'];
            $result['total_with_mobile'] += $counts['with_mobile'];
            $result['assembly_counts'][$acNo] = $counts;
        }

        return $result;
    }

    /**
     * Detect mobile column name from table schema
     */
    protected function detectMobileColumn(string $tableName): ?array
    {
        $cacheKey = "table_columns_{$tableName}";

        return Cache::remember($cacheKey, 86400, function () use ($tableName) {
            try {
                $columns = DB::connection('mysql_voters')
                    ->getSchemaBuilder()
                    ->getColumnListing($tableName);

                $mobileColumn = null;
                $idColumn = null;

                // Search for mobile column
                $mobilePatterns = ['mobile', 'phone', 'mobile_no', 'phone_no', 'mob', 'contact', 'mobile_number', 'phone_number', 'MOBILE_NO', 'PHONE_NO', 'MOB_NO'];
                foreach ($mobilePatterns as $pattern) {
                    foreach ($columns as $col) {
                        if (strtolower($col) === strtolower($pattern)) {
                            $mobileColumn = $col;
                            break 2;
                        }
                    }
                }

                // Fuzzy match if exact not found
                if (!$mobileColumn) {
                    foreach ($columns as $col) {
                        if (preg_match('/mob|phone|contact/i', $col)) {
                            $mobileColumn = $col;
                            break;
                        }
                    }
                }

                // Search for ID column
                $idPatterns = ['id', 'voter_id', 'sl_no', 'serial_no', 'sr_no', 'ID', 'VOTER_ID', 'SL_NO'];
                foreach ($idPatterns as $pattern) {
                    foreach ($columns as $col) {
                        if (strtolower($col) === strtolower($pattern)) {
                            $idColumn = $col;
                            break 2;
                        }
                    }
                }

                if (!$idColumn) {
                    $idColumn = $columns[0] ?? 'id';
                }

                return $mobileColumn ? ['mobile' => $mobileColumn, 'id' => $idColumn] : null;
            } catch (\Exception $e) {
                Log::error("Column detection failed for {$tableName}: {$e->getMessage()}");
                return null;
            }
        });
    }

    /**
     * Get list of tables available in MySQL voter database
     */
    public function getMysqlTables(): array
    {
        return Cache::remember('mysql_voter_tables', 3600, function () {
            return DB::connection('mysql_voters')
                ->getDoctrineSchemaManager()
                ->listTableNames();
        });
    }
}
