<?php

namespace App\Http\Controllers;

use App\Models\Assembly;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class VoterController extends Controller
{
    public function index(Request $request)
    {
        $assemblies = Assembly::orderBy('ac_no', 'asc')->get();

        // Group assemblies by zone > district for filter dropdown
        $grouped = [];
        foreach ($assemblies as $a) {
            $grouped[$a->zone][$a->district][] = $a;
        }

        $selectedAc = $request->get('assembly');
        $search = $request->get('search');
        $perPage = (int) $request->get('per_page', 50);
        $page = (int) $request->get('page', 1);

        $voters = collect();
        $total = 0;
        $assembly = null;
        $columns = [];

        if ($selectedAc) {
            $assembly = Assembly::where('ac_no', (int) $selectedAc)->first();

            if ($assembly && $assembly->table_name) {
                try {
                    $tableName = $assembly->table_name;

                    // Check if table exists
                    $tableExists = DB::connection('mysql_voters')
                        ->getSchemaBuilder()
                        ->hasTable($tableName);

                    if ($tableExists) {
                        // Get column list for display
                        $columns = DB::connection('mysql_voters')
                            ->getSchemaBuilder()
                            ->getColumnListing($tableName);

                        $query = DB::connection('mysql_voters')->table($tableName);

                        // Apply search filter
                        if ($search) {
                            $query->where(function ($q) use ($search, $columns) {
                                foreach ($columns as $col) {
                                    $q->orWhere($col, 'LIKE', "%{$search}%");
                                }
                            });
                        }

                        $total = $query->count();
                        $offset = ($page - 1) * $perPage;

                        $voters = $query->orderBy('ID', 'asc')
                            ->offset($offset)
                            ->limit($perPage)
                            ->get();
                    }
                } catch (\Exception $e) {
                    session()->flash('error', 'Error reading table: ' . $e->getMessage());
                }
            }
        }

        // Summary stats
        $totalAssemblies = $assemblies->count();
        $totalVotersAllStr = Cache::remember('total_voters_all', 3600, function () {
            try {
                $row = DB::connection('mysql_voters')
                    ->table('tbl_assembly_consitituency')
                    ->selectRaw('SUM(total_voters) as total')
                    ->first();
                return $row->total ?? 0;
            } catch (\Exception $e) {
                return 0;
            }
        });

        return view('voters.index', compact(
            'assemblies', 'grouped', 'selectedAc', 'search', 'voters',
            'total', 'assembly', 'columns', 'perPage', 'page',
            'totalAssemblies', 'totalVotersAllStr'
        ));
    }

    /**
     * API: Get assembly stats for AJAX calls
     */
    public function assemblyStats(Request $request)
    {
        $acNo = (int) $request->get('ac_no');
        $assembly = Assembly::where('ac_no', $acNo)->first();

        if (!$assembly || !$assembly->table_name) {
            return response()->json(['error' => 'Assembly not found'], 404);
        }

        try {
            $tableName = $assembly->table_name;

            $total = DB::connection('mysql_voters')->table($tableName)->count();
            $withMobile = DB::connection('mysql_voters')->table($tableName)
                ->whereNotNull('MOBILE_NUMBER')
                ->where('MOBILE_NUMBER', '!=', '')
                ->count();

            $genderStats = DB::connection('mysql_voters')->table($tableName)
                ->selectRaw("GENDER, COUNT(*) as count")
                ->groupBy('GENDER')
                ->pluck('count', 'GENDER')
                ->toArray();

            return response()->json([
                'ac_no' => $acNo,
                'constituency' => $assembly->constituency,
                'total_voters' => $total,
                'with_mobile' => $withMobile,
                'without_mobile' => $total - $withMobile,
                'gender_stats' => $genderStats,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
