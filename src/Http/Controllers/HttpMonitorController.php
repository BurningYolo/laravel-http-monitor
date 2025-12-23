<?php

namespace Burningyolo\LaravelHttpMonitor\Http\Controllers;

use Burningyolo\LaravelHttpMonitor\Models\InboundRequest;
use Burningyolo\LaravelHttpMonitor\Models\OutboundRequest;
use Burningyolo\LaravelHttpMonitor\Models\TrackedIp;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class HttpMonitorController extends Controller
{
    public function index()
    {
        $totalInbound = InboundRequest::count();
        $totalOutbound = OutboundRequest::count();
        $totalRequests = $totalInbound + $totalOutbound;

        $uniqueIps = TrackedIp::count();

        $successfulOutbound = OutboundRequest::where('successful', true)->count();
        $failedOutbound = OutboundRequest::where('successful', false)->count();

        $recentInbound = InboundRequest::latest()->limit(5)->get();
        $recentOutbound = OutboundRequest::latest()->limit(5)->get();

        return view('http-monitor::index', compact(
            'totalRequests',
            'totalInbound',
            'totalOutbound',
            'uniqueIps',
            'successfulOutbound',
            'failedOutbound',
            'recentInbound',
            'recentOutbound'
        ));
    }

    // Inbound Requests
    public function inboundIndex(Request $request)
    {
        $query = InboundRequest::with('trackedIp')->latest();

        if ($request->filled('method')) {
            $query->where('method', $request->query('method'));
        }
        if ($request->filled('status')) {
            $query->where('status_code', $request->status);
        }

        $requests = $query->paginate(20);

        return view('http-monitor::inbound.index', compact('requests'));
    }

    public function inboundShow($id)
    {
        $request = InboundRequest::with('trackedIp')->findOrFail($id);

        return view('http-monitor::inbound.show', compact('request'));
    }

    public function inboundDestroy($id)
    {
        InboundRequest::findOrFail($id)->delete();

        return redirect()->route('http-monitor.inbound.index')->with('success', 'Request deleted');
    }

    // Outbound Requests
    public function outboundIndex(Request $request)
    {
        $query = OutboundRequest::with('trackedIp')->latest();

        if ($request->filled('method')) {
            $query->where('method', $request->query('method'));
        }
        if ($request->filled('successful')) {
            $query->where('successful', $request->successful === '1');
        }

        $requests = $query->paginate(20);

        return view('http-monitor::outbound.index', compact('requests'));
    }

    public function outboundShow($id)
    {
        $request = OutboundRequest::with('trackedIp')->findOrFail($id);

        return view('http-monitor::outbound.show', compact('request'));
    }

    public function outboundDestroy($id)
    {
        OutboundRequest::findOrFail($id)->delete();

        return redirect()->route('http-monitor.outbound.index')->with('success', 'Request deleted');
    }

    // Tracked IPs
    public function ipsIndex(Request $request)
    {
        $query = TrackedIp::latest('last_seen_at');

        if ($request->filled('has_geo')) {
            if ($request->has_geo === '1') {
                $query->whereNotNull('country_code');
            } else {
                $query->whereNull('country_code');
            }
        }

        $ips = $query->paginate(20);

        return view('http-monitor::ips.index', compact('ips'));
    }

    public function ipsShow($id)
    {
        $ip = TrackedIp::with(['inboundRequests' => fn ($q) => $q->latest()->limit(10),
            'outboundRequests' => fn ($q) => $q->latest()->limit(10)])
            ->findOrFail($id);

        return view('http-monitor::ips.show', compact('ip'));
    }

    public function ipsDestroy($id)
    {
        TrackedIp::findOrFail($id)->delete();

        return redirect()->route('http-monitor.ips.index')->with('success', 'IP deleted');
    }
}
