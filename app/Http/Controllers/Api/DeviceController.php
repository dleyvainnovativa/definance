<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LoginDeviceResource;
use App\Models\LoginDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The user's login devices (sessions recorded at each Firebase→session exchange).
 * Per-user isolation is automatic: LoginDevice uses the global UserScope, so
 * both the listing and route-model binding only ever see the user's own rows.
 */
class DeviceController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $devices = LoginDevice::query()
            ->orderByDesc('last_login_at')
            ->orderByDesc('id')
            ->get();

        return LoginDeviceResource::collection($devices);
    }

    /** Revoke (remove) a recorded device — a foreign id simply 404s via the scope. */
    public function destroy(LoginDevice $device): JsonResponse
    {
        $device->delete();

        return response()->json(status: 204);
    }
}
