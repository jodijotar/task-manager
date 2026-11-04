<?php
// JWT HS256 sem dependências
function b64u(string $d): string { return rtrim(strtr(base64_encode($d), '+/', '-_'), '='); }
function b64u_dec(string $d): string { return base64_decode(strtr($d, '-_', '+/')); }

function jwt_sign(string $data): string {
    return b64u(hash_hmac('sha256', $data, env('JWT_SECRET', 'dev'), true));
}

function jwt_encode(array $payload): string {
    $h = b64u(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $p = b64u(json_encode($payload));
    return "$h.$p." . jwt_sign("$h.$p");
}

function jwt_decode(string $token): ?array {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;
    [$h, $p, $s] = $parts;
    if (!hash_equals(jwt_sign("$h.$p"), $s)) return null;
    $data = json_decode(b64u_dec($p), true);
    if (!is_array($data) || ($data['exp'] ?? 0) < time()) return null;
    return $data;
}
