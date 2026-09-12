<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AccountResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AccountController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless(
            $request->user()->tokenCan('accounts:read'),
            403
        );

        $accounts = $request->user()
            ->accounts()
            ->orderBy('id')
            ->get();

        return AccountResource::collection($accounts);
    }
}