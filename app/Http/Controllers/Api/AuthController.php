<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hospital;
use App\Support\SessionUser;
use App\Support\UuidBin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => 'required|string|max:100',
            'password' => 'required|string|max:255',
        ]);

        $this->ensureAdminUser();

        $user = DB::table('users')
            ->where('username', $validated['username'])
            ->where('status', 'active')
            ->first();

        if (! $user || ! Hash::check($validated['password'], $user->password_hash)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid username or password',
            ], 422);
        }

        $request->session()->regenerate();
        $request->session()->put('auth_user_id', UuidBin::from($user->id));
        $request->session()->put('auth_username', $user->username);

        DB::table('users')->where('id', $user->id)->update(['last_login_at' => now()]);

        return response()->json([
            'success' => true,
            'data' => SessionUser::profile(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->session()->forget(['auth_user_id', 'auth_username']);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['success' => true]);
    }

    public function me(): JsonResponse
    {
        $profile = SessionUser::profile();
        if (! $profile) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        return response()->json(['success' => true, 'data' => $profile]);
    }

    private function ensureAdminUser(): void
    {
        if (DB::table('users')->where('username', 'admin')->exists()) {
            return;
        }

        $hospital = Hospital::query()->first();
        if (! $hospital) {
            return;
        }

        DB::table('users')->insert([
            'id' => UuidBin::generate(),
            'hospital_id' => $hospital->getRawOriginal('id'),
            'username' => 'admin',
            'email' => null,
            'password_hash' => Hash::make('admin'),
            'first_name' => 'Admin',
            'last_name' => 'User',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
