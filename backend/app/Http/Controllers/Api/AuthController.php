<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UsuarioResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $usuario = User::query()->where('email', $request->string('email')->toString())->first();

        // Se compara un hash aunque el usuario no exista, para no revelar por tiempo de
        // respuesta qué correos están registrados. Inexistente, clave errónea o inactivo
        // devuelven el mismo error.
        $hash = $usuario !== null ? $usuario->password : Hash::make('usuario-inexistente');
        $claveValida = Hash::check($request->string('password')->toString(), $hash);

        if ($usuario === null || ! $claveValida || ! $usuario->activo) {
            return response()->json([
                'message' => 'Credenciales inválidas.',
                'codigo' => 'CREDENCIALES_INVALIDAS',
            ], 401);
        }

        $minutos = config('sanctum.expiration');
        $expira = is_numeric($minutos) ? now()->addMinutes((int) $minutos) : null;
        $token = $usuario->createToken($request->string('dispositivo', 'api')->toString(), ['*'], $expira);

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $expira?->toIso8601String(),
            'usuario' => UsuarioResource::make($usuario),
        ]);
    }

    public function me(Request $request): UsuarioResource
    {
        return UsuarioResource::make($request->user());
    }

    public function logout(Request $request): Response
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->noContent();
    }
}
