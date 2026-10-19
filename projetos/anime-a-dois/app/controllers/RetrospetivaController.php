<?php
// Retrospetivas no site (como na app): a lista de meses e anos com episódios e, para cada um,
// os ecrãs tipo stories com os teus números e um cartão final que se partilha como imagem.
// Os números vêm do Model Resumo (o mesmo da API).

class RetrospetivaController extends Controller
{
    // GET: escolher que retrospetiva ver (meses mais recentes primeiro, e os anos)
    public function index(): void
    {
        $user = $this->exigirLogin();

        $this->render('retrospetiva/index', [
            'titulo'   => 'Retrospetivas',
            'user'     => $user,
            'periodos' => Resumo::periodos($user),
        ]);
    }

    // GET ?ano=2026[&mes=10]: os stories desse período
    public function ver(): void
    {
        $user = $this->exigirLogin();
        $ano  = (int) ($_GET['ano'] ?? 0);
        $mes  = isset($_GET['mes']) ? (int) $_GET['mes'] : null;

        // O Model recusa períodos que não existem (ano fora de 2000–2100, mês fora de 1–12)
        try {
            $resumo = new Resumo($user, $ano, $mes);
        } catch (InvalidArgumentException) {
            http_response_code(404);
            exit('Página não encontrada.');
        }

        $this->render('retrospetiva/ver', [
            'titulo'     => 'Retrospetiva · ' . $resumo->nome(),
            'classeBody' => 'pagina-stories',   // ecrã inteiro, sem margens (app.css)
            'user'       => $user,
            'r'          => $resumo->calcular(),
            'anual'      => $mes === null,
        ]);
    }
}
