<?php

namespace Burningyolo\LaravelHttpMonitor\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;

class CommandsController extends Controller
{
    public function index()
    {
        return view('http-monitor::commands.index');
    }

    public function execute(Request $request)
    {
        $request->validate([
            'command' => 'required|string|in:cleanup,clear,prune,stats,send-stats',
            'days' => 'nullable|integer|min:1|max:365',
            'type' => 'nullable|string|in:all,inbound,outbound,ips',
            'status' => 'nullable|integer',
            'dry_run' => 'nullable|in:on,1,true',
            'orphaned_ips' => 'nullable|in:on,1,true',
            'force' => 'nullable|boolean',
        ]);

        $command = $request->input('command');
        $output = '';

        try {
            switch ($command) {
                case 'cleanup':
                    $params = ['--days' => $request->input('days', 30)];

                    if ($request->input('type')) {
                        $params['--type'] = $request->input('type');
                    }

                    if ($request->input('status')) {
                        $params['--status'] = $request->input('status');
                    }

                    if ($request->boolean('dry_run')) {
                        $params['--dry-run'] = true;
                    }

                    if ($request->boolean('orphaned_ips')) {
                        $params['--orphaned-ips'] = true;
                    }

                    Artisan::call('request-tracker:cleanup', $params);
                    break;

                case 'clear':
                    $params = [
                        '--type' => $request->input('type', 'all'),
                        '--force' => true,
                    ];

                    Artisan::call('request-tracker:clear', $params);
                    break;

                case 'prune':
                    Artisan::call('request-tracker:prune', ['--force' => true]);
                    break;

                case 'stats':
                    $params = ['--days' => $request->input('days', 7)];
                    Artisan::call('request-tracker:stats', $params);
                    break;

                case 'send-stats':
                    Artisan::call('request-tracker:send-stats');
                    break;
            }

            $output = Artisan::output();

            return response()->json([
                'success' => true,
                'output' => $output,
                'message' => 'Command executed successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error executing command: '.$e->getMessage(),
            ], 500);
        }
    }
}
