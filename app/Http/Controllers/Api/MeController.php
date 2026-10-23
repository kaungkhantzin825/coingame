<?php

namespace App\Http\Controllers\Api;

use App\Support\Cfg;
use Illuminate\Http\Request;

class MeController extends ApiController
{
    /** GET /api/me */
    public function show(Request $request)
    {
        $user = $this->user($request);

        return $this->ok([
            'user' => $this->profile($user),
            'config' => $this->clientConfig(),
        ]);
    }

    /** GET /api/config — public remote config / kill switch. */
    public function config(Request $request)
    {
        return $this->ok(['config' => Cfg::clientBundle()]);
    }
}
