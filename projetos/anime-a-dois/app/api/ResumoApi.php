<?php
// API: retrospetivas (mensal e anual, tipo "Wrapped") e medalhas. A lógica vive nos Models Resumo e Conquistas.

class ResumoApi extends ApiController
{
    // GET /resumos — meses e anos com episódios (para escolher qual ver)
    public function periodos(): void
    {
        $user = $this->exigirToken();
        $this->json(['ok' => true] + Resumo::periodos($user));
    }

    // GET /resumos/{ano} — retrospetiva do ano
    public function ano(string $ano): void
    {
        $user = $this->exigirToken();
        $this->responder(new Resumo($user, (int) $ano));
    }

    // GET /resumos/{ano}/{mes} — retrospetiva do mês
    public function mes(string $ano, string $mes): void
    {
        $user = $this->exigirToken();
        $this->responder(new Resumo($user, (int) $ano, (int) $mes));
    }

    // GET /medalhas — todas as medalhas, com o progresso de cada uma
    public function medalhas(): void
    {
        $user = $this->exigirToken();
        $lista = (new Conquistas($user))->lista();
        $this->json([
            'ok'       => true,
            'obtidas'  => count(array_filter($lista, fn ($m) => $m['obtida'])),
            'total'    => count($lista),
            'medalhas' => $lista,
        ]);
    }

    // Converte os Models (séries, par) do resumo para o formato público da API
    private function responder(Resumo $r): never
    {
        $d = $r->calcular();
        $d['top'] = array_map(fn ($t) => ['serie' => $this->serieBase($t['serie']), 'episodios' => $t['episodios']], $d['top']);
        $d['acabadas'] = array_map(fn ($s) => $this->serieBase($s), $d['acabadas']);
        if ($d['par'] !== null) {
            $d['par']['user'] = $this->userJson($d['par']['user']);
        }
        $this->json(['ok' => true, 'resumo' => $d]);
    }
}
