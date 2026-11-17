<?php

namespace App\Http\Controllers;

use App\Services\Reports\SpendReport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminReportController extends Controller
{
    public function __invoke(Request $request, SpendReport $report): Response
    {
        abort_unless($request->user()?->is_admin, 403);

        return Inertia::render('Admin/Report', $report->build());
    }
}
