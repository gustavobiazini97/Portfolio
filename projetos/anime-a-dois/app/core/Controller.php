<?php
// Base de todos os controllers: views, redirects, mensagens flash, CSRF e autenticação.

abstract class Controller
{
    // Mostra uma view dentro do layout (header + view + footer)
    protected function render(string $view, array $dados = []): void
    {
        // As variáveis do array ficam disponíveis na view ($user, $erro, ...)
        extract($dados);
        $flash   = $this->lerFlash();          // mensagem da página anterior (PRG)
        $antigo  = $this->lerAntigo();         // valores do formulário que falhou
        $appNome = Database::config()['app_nome'] ?? 'Anime a Dois';
        $titulo  = $titulo ?? $appNome;

        $base = __DIR__ . '/../views/';
        require $base . 'layout/header.php';
        require $base . $view . '.php';
        require $base . 'layout/footer.php';
    }

    // Redireciona para outra ação e termina o pedido (o "Redirect" do Post/Redirect/Get)
    protected function redirect(string $c, string $a = 'index', array $params = [], string $ancora = ''): never
    {
        header('Location: ' . url($c, $a, $params) . ($ancora !== '' ? '#' . $ancora : ''));
        exit;
    }

    // Guarda uma mensagem para mostrar no próximo pedido; $tipo: sucesso | erro | info
    protected function flash(string $tipo, string $mensagem): void
    {
        $_SESSION['_flash'] = ['tipo' => $tipo, 'mensagem' => $mensagem];
    }

    // Lê e apaga a mensagem flash (só aparece uma vez)
    private function lerFlash(): ?array
    {
        $flash = $_SESSION['_flash'] ?? null;
        unset($_SESSION['_flash']);
        return $flash;
    }

    // Guarda o que foi escrito no formulário, para não ter de escrever tudo outra vez
    protected function guardarAntigo(array $campos): void
    {
        $_SESSION['_antigo'] = $campos;
    }

    // Lê e apaga os valores antigos do formulário
    private function lerAntigo(): array
    {
        $antigo = $_SESSION['_antigo'] ?? [];
        unset($_SESSION['_antigo']);
        return $antigo;
    }

    // Só aceita POST com o token CSRF certo; qualquer outra coisa volta ao início
    protected function exigirPost(): void
    {
        $token = $_POST['_csrf'] ?? '';
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !hash_equals($_SESSION['_csrf'] ?? '', $token)) {
            $this->flash('erro', 'Pedido inválido. Tenta outra vez.');
            $this->redirect('auth', 'login');
        }
    }

    // Utilizador com sessão iniciada, ou null
    protected function utilizador(): ?User
    {
        $id = $_SESSION['user_id'] ?? null;
        return $id === null ? null : User::find($id);
    }

    // Páginas privadas: sem sessão (ou conta apagada) vai para o login
    protected function exigirLogin(): User
    {
        $user = $this->utilizador();
        if ($user === null) {                  // null guard depois do find()
            unset($_SESSION['user_id']);
            $this->redirect('auth', 'login');   // sem aviso: na primeira visita seria só ruído
        }
        return $user;
    }
}
