<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Ops\OpsHealthSnapshot;
use Illuminate\View\View;

class OpsHealthController extends Controller
{
    public function __invoke(OpsHealthSnapshot $snapshot): View
    {
        return view('admin.ops-health.index', $snapshot->data());
    }
}
