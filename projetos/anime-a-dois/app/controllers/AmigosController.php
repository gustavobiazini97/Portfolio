<?php
// Amigos: a lista, o link de convite (para quem ainda não tem conta), o pedido pelo utilizador exato
// (para quem já tem) e aceitar, recusar ou desfazer. Os amigos veem o perfil e a biblioteca uns dos outros.

class AmigosController extends Controller
{
    // GET: amigos, pedidos recebidos/enviados e o link de convite em circulação
    public function index(): void
    {
        $user    = $this->exigirLogin();
        $convite = Amizade::conviteAtivo($user);

        $this->render('amigos/index', [
            'titulo'    => 'Amigos',
            'user'      => $user,
            'amigos'    => Amizade::amigosDe($user),
            'recebidos' => Amizade::pedidosRecebidos($user),
            'enviados'  => Amizade::pedidosEnviados($user),
            // Link absoluto para partilhar; null enquanto não geraste nenhum (ou se expirou)
            'link'      => $convite ? url_absoluto() . url('auth', 'registo', ['convite' => $convite->codigo]) : null,
            'validade'  => Amizade::VALIDADE_DIAS,
        ]);
    }

    // POST: gera um link novo (o anterior, se ainda não foi usado, deixa de servir)
    public function criarConvite(): void
    {
        $this->exigirPost();
        $user = $this->exigirLogin();

        Amizade::criarConvite($user);
        $this->flash('sucesso', 'Link criado. Já podes enviá-lo.');
        $this->redirect('amigos');
    }

    // POST: pedido de amizade pelo utilizador exato
    public function pedir(): void
    {
        $this->exigirPost();
        $user = $this->exigirLogin();

        try {
            $resultado = Amizade::pedir($user, $_POST['username'] ?? '', $para);
            Notificador::amigos($user, $para, $resultado === 'amigos' ? 'aceite' : 'pedido');   // avisa a outra pessoa
            $this->flash('sucesso', $resultado === 'amigos' ? 'Ele já te tinha pedido: agora são amigos.' : 'Pedido enviado.');
        } catch (InvalidArgumentException $e) {
            $this->flash('erro', $e->getMessage());
        }
        $this->redirect('amigos');
    }

    // POST: id = quem te enviou o pedido
    public function aceitar(): void
    {
        $this->exigirPost();
        $user  = $this->exigirLogin();
        $outro = User::find((int) ($_POST['id'] ?? 0));

        try {
            if ($outro === null) {
                throw new InvalidArgumentException('Esse pedido já não existe.');
            }
            Amizade::aceitar($user, $outro);
            Notificador::amigos($user, $outro, 'aceite');   // avisa quem tinha pedido
            $this->flash('sucesso', 'Agora tu e ' . $outro->nome . ' são amigos.');
        } catch (InvalidArgumentException $e) {
            $this->flash('erro', $e->getMessage());
        }
        $this->redirect('amigos');
    }

    // POST: id = quem enviou o pedido (recusar) ou a quem o enviaste (cancelar)
    public function recusar(): void
    {
        $this->exigirPost();
        $user  = $this->exigirLogin();
        $outro = User::find((int) ($_POST['id'] ?? 0));

        if ($outro !== null) {
            Amizade::recusar($user, $outro);
        }
        $this->flash('info', 'Pedido retirado.');
        $this->redirect('amigos');
    }

    // POST: id = o amigo a tirar (nos dois sentidos)
    public function remover(): void
    {
        $this->exigirPost();
        $user  = $this->exigirLogin();
        $outro = User::find((int) ($_POST['id'] ?? 0));

        if ($outro !== null) {
            Amizade::remover($user, $outro);
            $this->flash('info', $outro->nome . ' já não é teu amigo.');
        }
        $this->redirect('amigos');
    }
}
