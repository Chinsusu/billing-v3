<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceAutoRenewalAttempt;
use App\Models\User;
use App\Services\Services\ServiceAutoRenewalPolicy;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceRenewalReportController extends Controller
{
    public function __invoke(Request $request, ServiceAutoRenewalPolicy $renewalPolicy): View
    {
        $status = (string) $request->query('status', '');
        $productId = (string) $request->query('product_id', '');
        $customer = trim((string) $request->query('customer', ''));
        $recentSince = now()->subDays(7);

        $attemptsQuery = ServiceAutoRenewalAttempt::query()
            ->with(['service.product', 'service.user', 'user'])
            ->where('updated_at', '>=', $recentSince);

        if (in_array($status, ['processing', 'succeeded', 'failed', 'skipped'], true)) {
            $attemptsQuery->where('status', $status);
        }

        if ($productId !== '') {
            $attemptsQuery->whereHas('service', function ($query) use ($productId): void {
                $query->where('product_id', $productId);
            });
        }

        if ($customer !== '') {
            $attemptsQuery->where(function ($query) use ($customer): void {
                $query->whereHas('user', function ($query) use ($customer): void {
                    $query->where('email', 'like', "%{$customer}%");
                })->orWhereHas('service.user', function ($query) use ($customer): void {
                    $query->where('email', 'like', "%{$customer}%");
                });
            });
        }

        $attempts = $attemptsQuery
            ->latest('updated_at')
            ->paginate(50)
            ->withQueryString();

        $attempts->getCollection()->transform(function (ServiceAutoRenewalAttempt $attempt) use ($renewalPolicy): ServiceAutoRenewalAttempt {
            $policy = $attempt->service === null
                ? $renewalPolicy->forProduct(null)
                : $renewalPolicy->forService($attempt->service);

            $attempt->setAttribute('renewal_policy', $policy);
            $attempt->setAttribute('is_exhausted', $renewalPolicy->isExhausted($attempt, $policy));

            return $attempt;
        });

        $activeAutoRenewServices = Service::with('product')
            ->where('status', 'active')
            ->where('auto_renew_enabled', true)
            ->whereNotNull('expires_at')
            ->get();

        return view('admin.renewals.index', [
            'attempts' => $attempts,
            'products' => Product::orderBy('name')->get(['id', 'name', 'code']),
            'filters' => [
                'status' => $status,
                'product_id' => $productId,
                'customer' => $customer,
            ],
            'customerOptions' => User::role('customer')->orderBy('email')->limit(100)->pluck('email')->all(),
            'autoRenewEnabledCount' => $activeAutoRenewServices->count(),
            'dueWithinPolicyCount' => $activeAutoRenewServices
                ->filter(fn (Service $service): bool => $renewalPolicy->isDue($service))
                ->count(),
            'failedAttemptsLast7Days' => ServiceAutoRenewalAttempt::where('status', 'failed')
                ->where('updated_at', '>=', now()->subDays(7))
                ->count(),
            'exhaustedAttemptCount' => ServiceAutoRenewalAttempt::where('status', 'failed')
                ->whereNull('next_attempt_at')
                ->whereHas('service', function ($query): void {
                    $query->where('status', 'active')
                        ->whereColumn('service_auto_renewal_attempts.expires_at', 'services.expires_at');
                })
                ->count(),
        ]);
    }
}
