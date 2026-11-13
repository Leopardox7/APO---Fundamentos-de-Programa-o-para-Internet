<?php
declare(strict_types=1);
session_start();

/*
 * Configuração padrão do XAMPP:
 * Host: localhost | Banco: logicontrol | Usuário: root | Senha: em branco
 * Se você definiu uma senha para o MySQL, informe-a em DB_PASS.
 */
const DB_HOST = '127.0.0.1';
const DB_NAME = 'logicontrol';
const DB_USER = 'root';
const DB_PASS = '';

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
         PDO::ATTR_EMULATE_PREPARES => false]
    );
    return $pdo;
}
function e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function csrf(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function check_csrf(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(419); exit('Token de segurança inválido. Atualize a página e tente novamente.');
    }
}
function redirect(string $page): never {
    header('Location: index.php?page=' . urlencode($page)); exit;
}
function flash(string $message, string $type='success'): void { $_SESSION['flash'] = [$message, $type]; }
function take_flash(): ?array { $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f; }
