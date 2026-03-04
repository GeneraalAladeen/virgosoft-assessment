<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserProfileResource;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request): UserProfileResource
    {
        $user = $request->user()->load('assets');

        return new UserProfileResource($user);
    }
}
