<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UserRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $roles (cargos separados por pipe, ex: admin|manager)
     * @param  string|null  $redirectTo (nome da rota para redirecionar opcionalmente)
     */
    public function handle(Request $request, Closure $next, string $roles, ?string $redirectTo = null): Response
    {
        $user = $request->user();

        // 1. Verifica se há um usuário autenticado
        if (!$user) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Unauthenticated.'], 401)
                : redirect()->route('login');
        }

        // 2. Prepara o array de roles permitidas
        $allowedRoles = explode('|', $roles);

        // 3. Obtém a role do usuário (trata caso esteja usando o Enum UserRole no model)
        $userRole = $user->role->value ?? $user->role;

        // 4. Se o usuário NÃO tiver permissão
        if (!in_array($userRole, $allowedRoles)) {
            
            // Retorno para rotas de API
            if ($request->expectsJson()) {
                // Retorna 404 para ocultar a existência do endpoint
                return response()->json(['message' => 'Not Found.'], 404);
            }

            // Retorno para rotas Web com redirecionamento opcional definido
            if ($redirectTo) {
                return redirect()->route($redirectTo);
            }

            // Retorno padrão para Web: Aborta com 404 (acionará a tela customizada que você vai criar)
            abort(404);
        }

        return $next($request);
    }
}
