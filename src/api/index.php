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
        resp(['token' => $token, 'usuario' => ['id' => (int)$u['id'], 'nome' => $u['nome'], 'sobrenome' => $u['sobrenome'], 'admin' => (bool)$u['is_admin'], 'foto' => $u['foto'] ?? null]]);
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

            // Busca por título/descrição. OBS: o termo é concatenado diretamente na query
            // (o desenvolvedor assumiu que, como a rota já é "filtrada" por usuario_id,
            // o campo de busca não precisava de tratamento especial) -> SQL Injection.
            $busca = trim($_GET['busca'] ?? '');
            $sql = "SELECT * FROM tarefas WHERE usuario_id=$uid";
            if ($busca !== '') {
                $sql .= " AND (titulo LIKE '%$busca%' OR descricao LIKE '%$busca%')";
            }
            $sql .= " ORDER BY (status='concluida'), data_conclusao ASC, id DESC";
            $st = db()->query($sql);
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

    // ---------- POST /api/perfil/foto (upload de foto de perfil) ----------
    if ($seg[0] === 'perfil' && ($seg[1] ?? '') === 'foto' && $method === 'POST') {
        $uid = (int)auth()['sub'];

        if (empty($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
            resp(['erro' => 'Nenhum arquivo enviado.'], 422);
        }
        $f = $_FILES['foto'];

        // Validação "defensiva": confia no Content-Type informado pelo próprio cliente
        // no multipart/form-data, em vez de inspecionar o conteúdo real do arquivo
        // (ex.: finfo_file/getimagesize). Esse cabeçalho é texto livre controlado por
        // quem faz a requisição e pode ser forjado via curl/Burp/Postman mesmo que o
        // front-end só permita selecionar imagens -> Upload irrestrito de arquivo.
        $tiposPermitidos = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($f['type'], $tiposPermitidos, true)) {
            resp(['erro' => 'Tipo de arquivo não permitido. Envie uma imagem (jpg, png ou gif).'], 422);
        }
        if ($f['size'] > 2 * 1024 * 1024) {
            resp(['erro' => 'Arquivo muito grande (máx. 2MB).'], 422);
        }

        // A extensão final do arquivo salvo vem do nome original enviado pelo cliente,
        // sem validar se ela realmente corresponde ao conteúdo do arquivo. Como a pasta
        // de uploads fica dentro do webroot servido pelo Apache/mod_php, um arquivo
        // "foto.php" (disfarçado de imagem só no Content-Type) é salvo com extensão
        // .php e passa a ser executado pelo servidor quando acessado via navegador.
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        $nomeArquivo = 'user_' . $uid . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
        $dirUploads = __DIR__ . '/../uploads';
        $destino = $dirUploads . '/' . $nomeArquivo;

        // A pasta de uploads é montada via bind mount (./src:/var/www/html no
        // docker-compose.yml). Se ela ainda não existir no host, ou existir mas
        // pertencer a um usuário diferente do processo do Apache (www-data) sem
        // permissão de escrita para "outros", o move_uploaded_file falha. Em vez
        // de um erro genérico, deixamos a causa explícita para facilitar o debug.
        if (!is_dir($dirUploads)) {
            @mkdir($dirUploads, 0775, true);
        }
        if (!is_dir($dirUploads) || !is_writable($dirUploads)) {
            resp(['erro' => 'Pasta de uploads inexistente ou sem permissão de escrita no servidor (verifique "src/uploads" no host).'], 500);
        }
        if (!move_uploaded_file($f['tmp_name'], $destino)) {
            resp(['erro' => 'Falha ao salvar o arquivo (move_uploaded_file). Verifique permissões da pasta "src/uploads".'], 500);
        }

        $caminho = 'uploads/' . $nomeArquivo;
        try {
            db()->prepare('UPDATE usuarios SET foto = ? WHERE id = ?')->execute([$caminho, $uid]);
        } catch (PDOException $e) {
            // Causa comum em laboratório: o volume do MySQL já existia antes da
            // coluna "foto" ser adicionada ao schema, então db/init.sql nunca
            // rodou de novo (só executa na criação do volume). Nesse caso o
            // UPDATE falha com "Unknown column 'foto'". Avisamos explicitamente
            // em vez de deixar cair no erro genérico 500 do catch global.
            @unlink($destino);
            resp(['erro' => 'Falha ao atualizar o perfil no banco. Se a coluna "foto" não existir na tabela "usuarios", recrie o volume do banco (docker compose down -v && docker compose up --build) ou rode: ALTER TABLE usuarios ADD COLUMN foto VARCHAR(255) NULL;'], 500);
        }
        resp(['ok' => true, 'foto' => $caminho], 201);
    }

    resp(['erro' => 'Rota não encontrada.'], 404);
} catch (Throwable $e) {
    error_log($e->getMessage());
    resp(['erro' => 'Erro interno do servidor.'], 500);
}
