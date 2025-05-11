<?php

namespace App\Http\Controllers;
use App\Models\User;
use App\Models\RefreshToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;

class AuthController extends Controller
{
    /**
     * Daftar semua user (hanya admin)
     */
    public function index()
    {
        // Hanya admin yang bisa lihat semua user
        if (!auth()->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        return response()->json(User::latest()->get());
    }

    /**
     * Register untuk user umum (public)
     */
    public function register(Request $request)
    {
        $fields = $request->validate([
            'name' => 'required|string',
            'email' => 'required|string|unique:users,email',
            'password' => 'required|string|min:6',
        ]);

        $user = User::create([
            'name' => $fields['name'],
            'email' => $fields['email'],
            'password' => bcrypt($fields['password']),
            'role' => User::ROLE_USER,
        ]);

        $token = $user->createToken('access_token')->plainTextToken;
        
        $refreshToken = $user->createRefreshToken();

        $response = [
            'user' => $user,
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => config('sanctum.expiration') * 60, 
            'refresh_token' => $refreshToken->token,
        ];

        return response($response, 201);
    }

    /**
     * Register relawan (hanya admin)
     */
    public function registerRelawan(Request $request)
    {
        if (!auth()->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $fields = $request->validate([
            'name' => 'required|string',
            'email' => 'required|string|unique:users,email',
            'password' => 'required|string|min:6',
            'nik' => 'required|string|size:16|unique:users,nik',
            'no_telp' => 'required|string|max:15',
        ]);

        $user = User::create([
            'name' => $fields['name'],
            'email' => $fields['email'],
            'password' => bcrypt($fields['password']),
            'role' => User::ROLE_RELAWAN,
            'nik' => $fields['nik'],
            'no_telp' => $fields['no_telp'],
        ]);

        return response()->json([
            'message' => 'Relawan registered successfully',
            'user' => $user
        ], 201);
    }

    /**
     * Register admin (hanya admin)
     */
    public function registerAdmin(Request $request)
    {
        if (!auth()->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $fields = $request->validate([
            'name' => 'required|string',
            'email' => 'required|string|unique:users,email',
            'password' => 'required|string|min:6',
        ]);

        $user = User::create([
            'name' => $fields['name'],
            'email' => $fields['email'],
            'password' => bcrypt($fields['password']),
            'role' => User::ROLE_ADMIN,
        ]);

        return response()->json([
            'message' => 'Admin registered successfully',
            'user' => $user
        ], 201);
    }

    /**
     * Login untuk semua tipe user
     */
    public function login(Request $request)
    {
        $fields = $request->validate([
            'email' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $fields['email'])->first();

        if(!$user || !Hash::check($fields['password'], $user->password)){
            return response([
                'message' => 'Bad Credentials',
            ], 401);
        }

        $user->tokens()->delete();
        
        $token = $user->createToken('access_token')->plainTextToken;
        
        $refreshToken = $user->createRefreshToken();

        $response = [
            'user' => $user,
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => config('sanctum.expiration') * 60, // dalam detik
            'refresh_token' => $refreshToken->token,
        ];

        return response($response, 200);
    }

    /**
     * Refresh token handler
     */
    public function refreshToken(Request $request)
    {
        $request->validate([
            'refresh_token' => 'required|string',
        ]);

        $refreshToken = RefreshToken::where('token', $request->refresh_token)->first();

        if (!$refreshToken) {
            return response()->json(['message' => 'Invalid refresh token'], 401);
        }

        if ($refreshToken->isExpired()) {
            $refreshToken->delete();
            return response()->json(['message' => 'Refresh token expired'], 401);
        }

        $user = $refreshToken->user;

        $user->tokens()->delete();
        
        $token = $user->createToken('access_token')->plainTextToken;
        
        $newRefreshToken = $user->createRefreshToken();

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => config('sanctum.expiration') * 60,
            'refresh_token' => $newRefreshToken->token,
        ]);
    }

    /**
     * Logout user
     */
    public function logout(Request $request)
    {
        auth()->user()->currentAccessToken()->delete();
        
        auth()->user()->refreshTokens()->delete();
        
        return response()->json([
            'message' => 'Logged out successfully'
        ]);
    }

    /**
     * Lihat detail user
     */
    public function show($id)
    {
        $user = User::find($id);
        
        if(!$user) {
            return response([
                'message' => 'User not found'
            ], 404);
        }
        
        if (!auth()->user()->isAdmin() && auth()->id() !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        return response()->json($user);
    }

    /**
     * Update user
     */
    public function update(Request $request, $id)
    {
        $user = User::find($id);
        
        if(!$user) {
            return response([
                'message' => 'User not found'
            ], 404);
        }
        
        if (!auth()->user()->isAdmin() && auth()->id() !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($user->isRelawan()) {
            $fields = $request->validate([
                'name' => 'sometimes|string',
                'email' => 'sometimes|string|unique:users,email,'.$id,
                'password' => 'sometimes|string|min:6',
                'nik' => 'sometimes|string|size:16|unique:users,nik,'.$id,
                'no_telp' => 'sometimes|string|max:15',
            ]);
        } else {
            $fields = $request->validate([
                'name' => 'sometimes|string',
                'email' => 'sometimes|string|unique:users,email,'.$id,
                'password' => 'sometimes|string|min:6',
            ]);
        }

        if (isset($request->role) && auth()->user()->isAdmin()) {
            $user->role = $request->role;
        }

        if(isset($fields['name'])) {
            $user->name = $fields['name'];
        }
        
        if(isset($fields['email'])) {
            $user->email = $fields['email'];
        }
        
        if(isset($fields['password'])) {
            $user->password = bcrypt($fields['password']);
        }
        
        if ($user->isRelawan()) {
            if(isset($fields['nik'])) {
                $user->nik = $fields['nik'];
            }
            
            if(isset($fields['no_telp'])) {
                $user->no_telp = $fields['no_telp'];
            }
        }
        
        $user->save();
        
        return response()->json([
            'message' => 'User updated successfully',
            'user' => $user
        ]);
    }

    /**
     * Delete user
     */
    public function destroy($id)
    {
        $user = User::find($id);
        
        if(!$user) {
            return response([
                'message' => 'User not found'
            ], 404);
        }
        
        if (!auth()->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        $user->delete();
        
        return response()->json([
            'message' => 'User deleted successfully'
        ]);
    }

    /**
     * Get user profile
     */
    public function profile()
    {
        return response()->json(auth()->user());
    }

    /**
     * Get all relawan (untuk admin)
     */
    public function getAllRelawan()
    {
        if (!auth()->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        $relawan = User::where('role', User::ROLE_RELAWAN)->latest()->get();
        
        return response()->json($relawan);
    }

    /**
     * Get all regular users (untuk admin)
     */
    public function getAllUsers()
    {
        if (!auth()->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        $users = User::where('role', User::ROLE_USER)->latest()->get();
        
        return response()->json($users);
    }
}