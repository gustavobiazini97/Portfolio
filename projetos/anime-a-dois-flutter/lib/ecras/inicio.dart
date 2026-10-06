// Início: o último episódio que o par viu e um cartão por série com as barras dos dois (o Vs).

import 'package:flutter/material.dart';

import '../api/api.dart';
import '../api/modelos.dart';
import '../config.dart';
import '../estado/sessao.dart';
import '../tema/paleta.dart';
import '../widgets/comum.dart';
import 'estatisticas.dart';
import 'perfil.dart';
import 'serie.dart';

class EcraInicio extends StatefulWidget {
  const EcraInicio({super.key});

  @override
  State<EcraInicio> createState() => _EcraInicioState();
}

class _EcraInicioState extends State<EcraInicio> {
  // Dados do GET /inicio
  Utilizador? _user;
  Utilizador? _par;
  Ultimo? _ultimoPar;
  String? _serieAtual;
  List<Serie> _series = [];

  bool _aCarregar = true;
  String? _erro;

  @override
  void initState() {
    super.initState();
    _carregar();
  }

  Future<void> _carregar() async {
    try {
      final d = await Api.instancia.get('/inicio');
      if (!mounted) return;
      setState(() {
        _user = Utilizador.deJson(d['user'] as Map<String, dynamic>);
        _par = Utilizador.talvez(d['parceiro']);
        _ultimoPar = Ultimo.talvez(d['ultimoPar']);
        _serieAtual = d['serieAtual'] as String?;
        _series = (d['series'] as List).map((s) => Serie.deJson(s as Map<String, dynamic>)).toList();
        _erro = null;
        _aCarregar = false;
      });
    } on ApiErro catch (e) {
      if (!mounted) return;
      setState(() {
        _erro = e.mensagem;
        _aCarregar = false;
      });
    }
  }

  // Abre um ecrã e, ao voltar, recarrega (pode ter havido episódios marcados ou nome mudado)
  Future<void> _abrir(Widget ecra) async {
    await Navigator.of(context).push(MaterialPageRoute(builder: (_) => ecra));
    if (mounted) _carregar();
  }

  @override
  Widget build(BuildContext context) {
    final p = Paleta.de(context);
    final escuro = Theme.of(context).brightness == Brightness.dark;

    return Scaffold(
      body: Fundo(
        child: SafeArea(
          child: Column(
            children: [
              // ---------- Barra de topo ----------
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 10, 10, 6),
                child: Row(
                  children: [
                    Image.asset('assets/icon/icon.png', width: 32, height: 32),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(appNome,
                          style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800)),
                    ),
                    IconButton(
                      tooltip: 'Estatísticas',
                      icon: const Icon(Icons.insights_rounded),
                      onPressed: _series.isEmpty
                          ? null
                          : () => _abrir(EcraEstatisticas(slug: _serieAtual ?? _series.first.slug)),
                    ),
                    IconButton(
                      tooltip: escuro ? 'Tema claro' : 'Tema escuro',
                      icon: Icon(escuro ? Icons.light_mode_rounded : Icons.dark_mode_rounded),
                      onPressed: Sessao.instancia.alternarTema,
                    ),
                    // O teu avatar abre o perfil
                    IconButton(
                      tooltip: 'Perfil',
                      onPressed: () => _abrir(const EcraPerfil()),
                      icon: Avatar(user: _user, cor: p.tu, tamanho: 32),
                    ),
                  ],
                ),
              ),

              // ---------- Conteúdo ----------
              Expanded(child: _conteudo(p)),
            ],
          ),
        ),
      ),
    );
  }

  Widget _conteudo(Paleta p) {
    if (_aCarregar) return const Carregando();
    if (_erro != null && _series.isEmpty) {
      return ErroComRetry(
        mensagem: _erro!,
        aoTentar: () {
          setState(() => _aCarregar = true);
          _carregar();
        },
      );
    }

    final textos = Theme.of(context).textTheme;

    // Puxar para baixo recarrega
    return RefreshIndicator(
      onRefresh: _carregar,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 6, 16, 28),
        children: [
          Text('Olá, ${_user?.nome ?? ''}', style: textos.titleLarge?.copyWith(fontWeight: FontWeight.w800)),
          const SizedBox(height: 14),

          // O que o par viu por último (abre a série nesse episódio)
          if (_ultimoPar != null && _par != null) ...[
            _cartaoUltimo(p, _par!, _ultimoPar!),
            const SizedBox(height: 14),
          ],

          // Ainda sem par: convite
          if (_par == null) ...[
            Vidro(
              child: Row(
                children: [
                  Icon(Icons.favorite_border_rounded, color: p.parTxt),
                  const SizedBox(width: 12),
                  const Expanded(
                    child: Text('O teu par ainda não tem conta. Quando criar, aparece aqui ao teu lado.'),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
          ],

          // Um cartão por série
          for (final s in _series) ...[
            _cartaoSerie(p, s),
            const SizedBox(height: 12),
          ],
        ],
      ),
    );
  }

  // "Andreia viu o episódio 12 · há 2 horas"
  Widget _cartaoUltimo(Paleta p, Utilizador par, Ultimo u) {
    final textos = Theme.of(context).textTheme;
    return Vidro(
      onTap: () => _abrir(EcraSerie(slug: u.serie, episodio: u.numero)),
      child: Row(
        children: [
          Avatar(user: par, cor: p.par, tamanho: 44),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('${par.nome} viu o episódio ${u.numero}',
                    style: textos.titleSmall?.copyWith(fontWeight: FontWeight.w800)),
                const SizedBox(height: 2),
                Text(
                  [u.serieNome, if (u.titulo != null) u.titulo!].join(' · '),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: textos.bodySmall?.copyWith(color: p.suave),
                ),
                if (u.quando.isNotEmpty)
                  Text(u.quando, style: textos.labelSmall?.copyWith(color: p.parTxt, fontWeight: FontWeight.w700)),
              ],
            ),
          ),
          Icon(Icons.chevron_right_rounded, color: p.suave),
        ],
      ),
    );
  }

  // Cartão de uma série: risca de cor, nome, as duas barras e a frase do Vs
  Widget _cartaoSerie(Paleta p, Serie s) {
    final textos = Theme.of(context).textTheme;
    return Vidro(
      onTap: () => _abrir(EcraSerie(slug: s.slug)),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Container(
                width: 10,
                height: 10,
                decoration: BoxDecoration(color: s.cor, shape: BoxShape.circle),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Text(s.nome, style: textos.titleMedium?.copyWith(fontWeight: FontWeight.w800)),
              ),
              Text(
                [if (s.anos != null) s.anos!, '${s.totalEpisodios} eps'].join(' · '),
                style: textos.labelSmall?.copyWith(color: p.suave),
              ),
            ],
          ),
          const SizedBox(height: 14),
          LinhaPessoa(nome: _user?.nome ?? 'Tu', progresso: s.tu, total: s.totalEpisodios, ehTu: true),
          if (_par != null && s.par != null) ...[
            const SizedBox(height: 10),
            LinhaPessoa(nome: _par!.nome, progresso: s.par!, total: s.totalEpisodios, ehTu: false),
          ],
          const SizedBox(height: 12),
          Text(s.resumo, style: textos.bodySmall?.copyWith(color: p.suave)),
        ],
      ),
    );
  }
}
