<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProvisioningProviderAccount;
use App\Services\Provisioning\ProviderAccountTester;
use Illuminate\Http\RedirectResponse;

class ProvisioningProviderAccountTestController extends Controller
{
    public function __invoke(ProvisioningProviderAccount $provisioningProviderAccount, ProviderAccountTester $tester): RedirectResponse
    {
        $tester->test($provisioningProviderAccount);

        return redirect('/admin/provisioning-provider-accounts')->with('status', 'Provider account tested.');
    }
}
