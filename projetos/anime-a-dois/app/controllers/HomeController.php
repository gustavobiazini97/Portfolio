<?php
// Dashboard. Por agora mostra a ligação entre as duas contas;
// o "último visto" e o Vs chegam no passo 4.

class HomeController extends Controller
{
    // GET: dashboard (página privada)
    public function index(): void
    {
        $user = $this->exigirLogin();

        $this->render('home/index', [
            'titulo'   => 'Início',
            'user'     => $user,
            'parceiro' => $user->parceiro(),   // null enquanto a outra conta não existir
        ]);
    }
}
