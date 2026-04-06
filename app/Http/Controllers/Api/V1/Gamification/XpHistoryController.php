<?php

namespace App\Http\Controllers\Api\V1\Gamification;

use App\Http\Controllers\Controller;
use App\Http\Resources\Gamification\XpTransactionResource;
use App\Models\XpTransaction;
use Illuminate\Http\Request;

class XpHistoryController extends Controller
{
    public function index(Request $request)
    {
        $transactions = XpTransaction::where('user_uuid', $request->user()->uuid)
            ->orderByDesc('created_at')
            ->paginate(30);

        return XpTransactionResource::collection($transactions);
    }
}
