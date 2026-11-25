<?php
// O bicho do casal no site: a página grande (GET) e o guardar (POST, chamado pelo JavaScript da página).
// A fase, o humor e o "de costas" são calculados no servidor (Model Mascote); aqui só se mostra e se guarda
// o que a pessoa escolhe (espécie, nome e cor). O mascote é sempre o do par de quem tem a sessão iniciada:
// nenhum pedido recebe o id de um mascote, por isso não há como mexer no de outro casal.

class MascoteController extends Controller
{
    // GET ?c=mascote — a página grande (sem par ainda não há bicho: mostra só o aviso)
    public function index(): void
    {
        $user = $this->exigirLogin();
        $this->render('mascote/index', [
            'titulo'     => 'O vosso bicho',
            'user'       => $user,
            'mascote'    => Mascote::estado($user),   // null sem par
            'classeBody' => 'pagina-mascote',
        ]);
    }

    // POST ?c=mascote&a=guardar (campos: _csrf e, opcionalmente, especie, nome, cor) → JSON
    public function guardar(): void
    {
        $this->exigirPost();                  // método POST + token CSRF (senão 419/redirect)
        $user = $this->exigirLogin();         // sem sessão → 401 em JSON

        // Só os campos conhecidos (lista permitida); o resto do POST é ignorado
        $campos = array_intersect_key($_POST, array_flip(['especie', 'nome', 'cor']));

        try {
            Mascote::guardar($user, $campos);   // valida tudo antes de escrever
        } catch (InvalidArgumentException $ex) {
            $this->json(['ok' => false, 'mensagem' => $ex->getMessage()], 422);
        }

        $estado = Mascote::estado($user);
        if ($estado === null) {               // sem par (guardar já recusa, mas não custa garantir)
            $this->json(['ok' => false, 'mensagem' => 'Ainda não tens par, por isso ainda não há mascote.'], 422);
        }
        $this->json(['ok' => true, 'mensagem' => 'Guardado.', 'mascote' => $estado]);
    }
}
