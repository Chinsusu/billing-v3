<?php

namespace App\Http\Controllers;

use App\Services\Finance\WalletService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WalletController extends Controller
{
    public function __invoke(Request $request, WalletService $walletService): View
    {
        $wallet = $walletService->walletFor($request->user());

        return view('wallet.show', [
            'wallet' => $wallet,
            'ledgerEntries' => $wallet->ledgerEntries()->latest()->limit(20)->get(),
        ]);
    }
}
