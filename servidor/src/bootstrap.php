<?php
/**
 * Inicialização comum do servidor: configuração, banco SQLite, sessão e helpers.
 *
 * Estrutura de dados (pasta DATA_DIR, fora do DocumentRoot):
 *   banco.sqlite
 *   uploads/{cliente_id}/{yyyy}/{mm}/{id}_signature.png (+ _original.png)
 */
declare(strict_types=1);

const APP_NOME   = 'Assinatura de Entrega — Servidor';
const APP_VERSAO = '1.0.0';

define('BASE_DIR', dirname(__DIR__));
define('DATA_DIR', getenv('DATA_DIR') ?: BASE_DIR . '/data');
define('UPLOAD_DIR', DATA_DIR . '/uploads');
define('DB_PATH', DATA_DIR . '/banco.sqlite');

date_default_timezone_set(getenv('TZ') ?: 'America/Sao_Paulo');

// ---------------------------------------------------------------- banco

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    if (!is_dir(DATA_DIR)) {
        mkdir(DATA_DIR, 0775, true);
    }
    $pdo = new PDO('sqlite:' . DB_PATH, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA busy_timeout = 5000');
    migrar($pdo);
    return $pdo;
}

function migrar(PDO $pdo): void
{
    $pdo->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS clientes (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            nome       TEXT    NOT NULL,
            token      TEXT    NOT NULL UNIQUE,
            ativo      INTEGER NOT NULL DEFAULT 1,
            criado_em  TEXT    NOT NULL DEFAULT (datetime('now'))
        );
        CREATE TABLE IF NOT EXISTS assinaturas (
            id              TEXT    PRIMARY KEY,          -- id gerado pelo app
            cliente_id      INTEGER NOT NULL REFERENCES clientes(id),
            pedido          TEXT    NOT NULL DEFAULT '',
            destinatario    TEXT    NOT NULL DEFAULT '',
            observacoes     TEXT    NOT NULL DEFAULT '',
            dispositivo     TEXT    NOT NULL DEFAULT '',
            capturado_em    TEXT    NOT NULL,             -- ISO-8601 UTC enviado pelo app
            recebido_em     TEXT    NOT NULL DEFAULT (datetime('now')),
            arquivo_assin   TEXT,                         -- caminho relativo a UPLOAD_DIR
            arquivo_orig    TEXT,
            ip_origem       TEXT
        );
        CREATE INDEX IF NOT EXISTS ix_assin_pedido    ON assinaturas(pedido);
        CREATE INDEX IF NOT EXISTS ix_assin_capturado ON assinaturas(capturado_em);
        CREATE INDEX IF NOT EXISTS ix_assin_cliente   ON assinaturas(cliente_id);
    SQL);
}

// ---------------------------------------------------------------- helpers

/** Escapa para HTML. */
function h(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Responde JSON e encerra. */
function json_out(array $dados, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Converte ISO-8601 UTC em data/hora local legível. */
function fmt_data(?string $iso): string
{
    if (!$iso) {
        return '';
    }
    try {
        $d = new DateTimeImmutable($iso, new DateTimeZone('UTC'));
        return $d->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('d/m/Y H:i:s');
    } catch (Throwable) {
        return $iso;
    }
}

function gerar_token(): string
{
    return bin2hex(random_bytes(24)); // 48 caracteres hex
}

// ---------------------------------------------------------------- API: autenticação por token de cliente

/** Lê o Bearer token do request (Authorization ou X-Auth-Token). */
function bearer_token(): ?string
{
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/^Bearer\s+(\S+)$/i', $auth, $m)) {
        return $m[1];
    }
    $alt = $_SERVER['HTTP_X_AUTH_TOKEN'] ?? '';
    return $alt !== '' ? $alt : null;
}

/** Exige token válido de um cliente ativo; retorna o cliente ou responde 401. */
function exigir_cliente(): array
{
    $token = bearer_token();
    if ($token === null) {
        json_out(['ok' => false, 'error' => 'token ausente (Authorization: Bearer ...)'], 401);
    }
    $st = db()->prepare('SELECT * FROM clientes WHERE token = ? LIMIT 1');
    $st->execute([$token]);
    $cliente = $st->fetch();
    if (!$cliente) {
        json_out(['ok' => false, 'error' => 'token inválido'], 401);
    }
    if ((int)$cliente['ativo'] !== 1) {
        json_out(['ok' => false, 'error' => 'cliente desativado'], 403);
    }
    return $cliente;
}

// ---------------------------------------------------------------- Painel: sessão de administrador

function admin_usuario(): string
{
    return getenv('ADMIN_USUARIO') ?: 'admin';
}

function admin_senha(): string
{
    return getenv('ADMIN_SENHA') ?: 'admin';
}

function sessao_iniciar(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_name('assinatura_sess');
        session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
        session_start();
    }
}

function logado(): bool
{
    sessao_iniciar();
    return !empty($_SESSION['admin']);
}

function exigir_login(): void
{
    if (!logado()) {
        header('Location: login.php');
        exit;
    }
}

function csrf_token(): string
{
    sessao_iniciar();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_validar(): void
{
    sessao_iniciar();
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(400);
        exit('Requisição inválida (CSRF).');
    }
}

// ---------------------------------------------------------------- layout do painel

function layout_inicio(string $titulo): void
{
    $t = h($titulo);
    echo <<<HTML
    <!doctype html>
    <html lang="pt-BR">
    <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{$t} — Assinaturas de Entrega</title>
    <link rel="stylesheet" href="assets/style.css">
    </head>
    <body>
    <header class="topo">
      <a class="marca" href="index.php">✍️ Assinaturas de Entrega</a>
      <nav>
        <a href="index.php">Assinaturas</a>
        <a href="clientes.php">Clientes</a>
        <a href="logout.php">Sair</a>
      </nav>
    </header>
    <main class="conteudo">
    <h1>{$t}</h1>
    HTML;
}

function layout_fim(): void
{
    $v = APP_VERSAO;
    echo <<<HTML
    </main>
    <footer class="rodape">Servidor de Assinaturas de Entrega v{$v}</footer>
    </body></html>
    HTML;
}
