<?php
require __DIR__ . '/config.php';
require __DIR__ . '/jwt.php';
header('Content-Type: application/json; charset=utf-8');

function resp($data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
function input(): array {
    $d = json_decode(file_get_contents('php://input'), true);
    return is_array($d) ? $d : [];
}
function token_payload(): ?array {
    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    return preg_match('/Bearer\s+(\S+)/', $h, $m) ? jwt_decode($m[1]) : null;
}
function auth(): array {
    $p = token_payload();
    if (!$p) resp(['erro' => 'Sessão inválida ou expirada.'], 401);
    return $p;
}
function data_valida($d): bool {
    $dt = DateTime::createFromFormat('Y-m-d', (string)$d);
    return $dt && $dt->format('Y-m-d') === $d;
}
function status_calc(string $status, string $prazo): string {
    if ($status === 'concluida') return 'concluida';
    return $prazo < date('Y-m-d') ? 'em_atraso' : 'em_aberto';
}
function buscar_tarefa(int $id, int $uid): array {
    $st = db()->prepare('SELECT * FROM tarefas WHERE id = ? AND usuario_id = ?');
    $st->execute([$id, $uid]);
    $t = $st->fetch();
    if (!$t) resp(['erro' => 'Atividade não encontrada.'], 404);
    return $t;
}
function validar_tarefa(array $in): array {
    $titulo = trim($in['titulo'] ?? '');
    $desc   = trim($in['descricao'] ?? '');
    $prazo  = $in['data_conclusao'] ?? '';
    $status = ($in['status'] ?? 'em_aberto') === 'concluida' ? 'concluida' : 'em_aberto';
    if ($titulo === '' || mb_strlen($titulo) > 150) resp(['erro' => 'Título obrigatório (máx. 150 caracteres).'], 422);
    if (!data_valida($prazo)) resp(['erro' => 'Data de conclusão inválida.'], 422);
    return [$titulo, $desc === '' ? null : $desc, $prazo, status_calc($status, $prazo)];
}

$method = $_SERVER['REQUEST_METHOD'];
$path   = trim(preg_replace('#^/api#', '', parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)), '/');
$seg    = explode('/', $path);

try {
    // ---------- POST /api/login ----------
    if ($seg[0] === 'login' && $method === 'POST') {
        $in = input();
        $st = db()->prepare('SELECT * FROM usuarios WHERE login = ?');
        $st->execute([trim($in['login'] ?? '')]);
        $u = $st->fetch();
        if (!$u || !password_verify($in['senha'] ?? '', $u['senha_hash'])) {
            resp(['erro' => 'Login ou senha inválidos.'], 401);
        }
        $token = jwt_encode(['sub' => (int)$u['id'], 'adm' => (bool)$u['is_admin'], 'exp' => time() + 7200]);
        resp(['token' => $token, 'usuario' => ['id' => (int)$u['id'], 'nome' => $u['nome'], 'sobrenome' => $u['sobrenome'], 'admin' => (bool)$u['is_admin']]]);
    }

    // ---------- POST /api/usuarios (somente com autorização de admin) ----------
    if ($seg[0] === 'usuarios' && $method === 'POST') {
        $in = input();
        $autorizado = false;
        $p = token_payload();
        if ($p && !empty($p['adm'])) {
            $autorizado = true;
        } else {
            $st = db()->prepare('SELECT senha_hash FROM usuarios WHERE login = ? AND is_admin = 1');
            $st->execute([trim($in['admin_login'] ?? '')]);
            $a = $st->fetch();
            $autorizado = $a && password_verify($in['admin_senha'] ?? '', $a['senha_hash']);
        }
        if (!$autorizado) resp(['erro' => 'Autorização de administrador inválida.'], 403);

        $nome = trim($in['nome'] ?? ''); $sobrenome = trim($in['sobrenome'] ?? '');
        $nasc = $in['data_nascimento'] ?? ''; $login = trim($in['login'] ?? ''); $senha = $in['senha'] ?? '';
        if ($nome === '' || $sobrenome === '') resp(['erro' => 'Nome e sobrenome são obrigatórios.'], 422);
        if (!data_valida($nasc)) resp(['erro' => 'Data de nascimento inválida.'], 422);
        if (mb_strlen($login) < 3) resp(['erro' => 'Login deve ter ao menos 3 caracteres.'], 422);
        if (mb_strlen($senha) < 6) resp(['erro' => 'Senha deve ter ao menos 6 caracteres.'], 422);

        try {
            $st = db()->prepare('INSERT INTO usuarios (nome, sobrenome, data_nascimento, login, senha_hash) VALUES (?,?,?,?,?)');
            $st->execute([$nome, $sobrenome, $nasc, $login, password_hash($senha, PASSWORD_BCRYPT)]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') resp(['erro' => 'Este login já está em uso.'], 409);
            throw $e;
        }
        resp(['ok' => true], 201);
    }

    // ---------- /api/tarefas ----------
    if ($seg[0] === 'tarefas') {
        $uid = (int)auth()['sub'];
        $id  = isset($seg[1]) ? (int)$seg[1] : 0;

        if ($method === 'GET' && !$id) {
            $st = db()->prepare("UPDATE tarefas SET status='em_atraso' WHERE usuario_id=? AND status='em_aberto' AND data_conclusao < ?");
            $st->execute([$uid, date('Y-m-d')]);
            $st = db()->prepare("SELECT * FROM tarefas WHERE usuario_id=? ORDER BY (status='concluida'), data_conclusao ASC, id DESC");
            $st->execute([$uid]);
            resp($st->fetchAll());
        }

        if ($method === 'POST' && !$id) {
            [$t, $d, $p, $s] = validar_tarefa(input());
            $st = db()->prepare('INSERT INTO tarefas (usuario_id, titulo, descricao, data_conclusao, status) VALUES (?,?,?,?,?)');
            $st->execute([$uid, $t, $d, $p, $s]);
            resp(['id' => (int)db()->lastInsertId()], 201);
        }

        if ($id && $method === 'PUT' && ($seg[2] ?? '') === 'concluir') {
            buscar_tarefa($id, $uid);
            db()->prepare("UPDATE tarefas SET status='concluida' WHERE id=? AND usuario_id=?")->execute([$id, $uid]);
            resp(['ok' => true]);
        }

        if ($id && $method === 'PUT') {
            buscar_tarefa($id, $uid);
            [$t, $d, $p, $s] = validar_tarefa(input());
            db()->prepare('UPDATE tarefas SET titulo=?, descricao=?, data_conclusao=?, status=? WHERE id=? AND usuario_id=?')
                ->execute([$t, $d, $p, $s, $id, $uid]);
            resp(['ok' => true]);
        }

        if ($id && $method === 'DELETE') {
            buscar_tarefa($id, $uid);
            db()->prepare('DELETE FROM tarefas WHERE id=? AND usuario_id=?')->execute([$id, $uid]);
            resp(['ok' => true]);
        }
    }

    resp(['erro' => 'Rota não encontrada.'], 404);
} catch (Throwable $e) {
    error_log($e->getMessage());
    resp(['erro' => 'Erro interno do servidor.'], 500);
}
