<?php

namespace App\Http\Controllers;

use App\Http\Resources\AuthUserResource;
use App\Models\User;
use App\Services\ConnectWiseService;
use App\Traits\ModelCamelCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    use ModelCamelCase;
    /**
     * @throws ValidationException
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'deviceName' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }



        return $user->createToken($request->deviceName)->plainTextToken;
    }

    public function user(Request $request)
    {
        return new AuthUserResource($request->user());
    }

    public function reports(Request $request, ConnectWiseService $connectWiseService)
    {
        $request->validate([
            'type' => ['nullable', 'string']
        ]);

        $type = $request->get('type');

        if (!$type) {
            return $connectWiseService->getAllUserReports();
        }

        return $connectWiseService->getUserReport($type);
    }

    public function update(Request $request, ConnectWiseService $connectWiseService)
    {
        $request->validate([
            'reportMode' => ['nullable', 'boolean']
        ]);

        $me = $request->user();

        $reportMode = $request->get('reportMode');

        if (!is_null($reportMode)) {

            if (!$reportMode) {
                $connectWiseService->clearUserReports();
            }

            $me->fill([
                'reportMode' => $reportMode
            ])->save();
        }

        return new AuthUserResource($me);
    }
}
