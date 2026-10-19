// Sininho (🔔): o que aconteceu à tua volta nos últimos 30 dias (GET /notificacoes):
// episódios e comentários de quem vê séries contigo, convites de série e pedidos de amizade.
// O número na bolinha são as que chegaram depois da última vez que abriste a lista
// (guardado no telemóvel). Tocar numa linha leva ao sítio certo.
// O ícone de filtro escolhe o que aparece (séries juntos, outras do par, dos amigos, comentários,
// convites e pedidos); a escolha fica no telemóvel e a bolinha conta só o que está ligado.

import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../api/api.dart';
import '../api/modelos.dart';
import '../estado/auto_atualizar.dart';
import '../estado/sessao.dart';
import '../tema/paleta.dart';
import '../widgets/comum.dart';
import 'pessoa.dart';
import 'serie.dart';

// Uma linha do sininho, como a API a envia
class Notificacao {
  final String id;
  final String tipo; // ep | com | convite | amigo
  final DateTime em;
  final Utilizador quem;
  final String texto; // "Andreia viu os episódios 12 a 14"
  final String detalhe; // nome da série, pedaço do comentário…
  final String? serie; // slug (null nos convites e pedidos)
  final int? episodio;
  final bool juntos; // episódios: série que vês com esta pessoa?
  final String? relacao; // episódios: par | amigo

  Notificacao.deJson(Map<String, dynamic> j)
      : id = j['id'] as String? ?? '',
        tipo = j['tipo'] as String? ?? '',
        em = DateTime.tryParse(j['em'] as String? ?? '') ?? DateTime.fromMillisecondsSinceEpoch(0),
        quem = Utilizador.deJson(j['quem'] as Map<String, dynamic>),
        texto = j['texto'] as String? ?? '',
        detalhe = j['detalhe'] as String? ?? '',
        serie = j['serie'] as String?,
        episodio = (j['episodio'] as num?)?.toInt(),
        juntos = j['juntos'] != false,
        relacao = j['relacao'] as String?;

  // Emoji pequeno ao lado da hora, para se perceber o tipo de relance
  String get emoji => switch (tipo) {
        'ep' => '▶️',
        'com' => '💬',
        'convite' => '🎬',
        'amigo' => '👋',
        _ => '🔔',
      };
}

// O que aparece no 🔔 (guardado no telemóvel)
class FiltrosSino {
  bool juntos = true; // episódios das séries que vês juntos
  bool outrasPar = true; // outras séries do teu par
  bool outrasAmigos = false; // outras séries dos amigos
  bool comentarios = true;
  bool convites = true; // convites de série e pedidos de amizade

  static const _chave = 'sino_filtros';

  static Future<FiltrosSino> ler() async {
    final f = FiltrosSino();
    final prefs = await SharedPreferences.getInstance();
    final v = prefs.getStringList(_chave);
    if (v != null && v.length == 5) {
      f.juntos = v[0] == '1';
      f.outrasPar = v[1] == '1';
      f.outrasAmigos = v[2] == '1';
      f.comentarios = v[3] == '1';
      f.convites = v[4] == '1';
    }
    return f;
  }

  Future<void> guardar() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setStringList(_chave, [juntos, outrasPar, outrasAmigos, comentarios, convites].map((b) => b ? '1' : '0').toList());
  }

  // Esta notificação passa nos filtros?
  bool deixa(Notificacao n) => switch (n.tipo) {
        'ep' => n.juntos ? juntos : (n.relacao == 'par' ? outrasPar : outrasAmigos),
        'com' => comentarios,
        'convite' || 'amigo' => convites,
        _ => true,
      };
}

// Estado do sininho partilhado pela app: a lista e quantas há por ver
class Sino {
  Sino._();

  static final naoVistas = ValueNotifier<int>(0); // a bolinha do Início ouve isto

  // Chave por pessoa (se outra conta entrar no mesmo telemóvel, começa do zero)
  static String get _chave => 'sino_visto_em_${Sessao.instancia.eu?.id ?? 0}';

  // Última vez que abriste a lista (null = nunca)
  static Future<DateTime?> vistoEm() async {
    final prefs = await SharedPreferences.getInstance();
    return DateTime.tryParse(prefs.getString(_chave) ?? '');
  }

  // Vai buscar a lista (já filtrada) e atualiza o número da bolinha
  static Future<List<Notificacao>> carregar() async {
    final d = await Api.instancia.get('/notificacoes');
    final filtros = await FiltrosSino.ler();
    final lista = ((d['notificacoes'] as List?) ?? [])
        .map((n) => Notificacao.deJson(n as Map<String, dynamic>))
        .where(filtros.deixa)
        .toList();
    final visto = await vistoEm();
    naoVistas.value = lista.where((n) => visto == null || n.em.isAfter(visto)).length;
    return lista;
  }

  // Abriste a lista: tudo o que lá está passa a visto
  static Future<void> marcarVistas(List<Notificacao> lista) async {
    if (lista.isNotEmpty) {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(_chave, lista.first.em.toIso8601String()); // a mais recente
    }
    naoVistas.value = 0;
  }
}

class EcraNotificacoes extends StatefulWidget {
  const EcraNotificacoes({super.key});

  @override
  State<EcraNotificacoes> createState() => _EcraNotificacoesState();
}

class _EcraNotificacoesState extends State<EcraNotificacoes> with AutoAtualizar {
  List<Notificacao>? _lista;
  DateTime? _vistoAntes; // o que é mais recente do que isto aparece destacado como "novo"
  bool _leuVistoAntes = false; // lido só uma vez: os destaques mantêm-se enquanto estás aqui
  String? _erro;

  @override
  void initState() {
    super.initState();
    _carregar();
    iniciarAutoAtualizar(); // com a lista aberta, as novas aparecem sozinhas
  }

  Future<void> _carregar() async {
    try {
      if (!_leuVistoAntes) {
        _vistoAntes = await Sino.vistoEm();
        _leuVistoAntes = true;
      }
      final lista = await Sino.carregar();
      if (!mounted) return;
      setState(() {
        _lista = lista;
        _erro = null;
      });
      await Sino.marcarVistas(lista);
    } on ApiErro catch (e) {
      if (mounted && _lista == null) setState(() => _erro = e.mensagem);
    }
  }

  @override
  Future<void> atualizarEmSilencio() => _carregar();

  // Folha com os interruptores; cada mudança guarda e volta a carregar a lista
  Future<void> _filtros() async {
    final f = await FiltrosSino.ler();
    if (!mounted) return;
    await showModalBottomSheet<void>(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, mudar) {
          Widget linha(String texto, bool valor, void Function(bool) definir) => SwitchListTile(
                title: Text(texto),
                value: valor,
                onChanged: (v) {
                  mudar(() => definir(v));
                  f.guardar().then((_) => _carregar());
                },
              );
          return SafeArea(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Padding(
                  padding: const EdgeInsets.fromLTRB(20, 4, 20, 8),
                  child: Text('O que mostrar',
                      style: Theme.of(ctx).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800)),
                ),
                linha('Séries que vês juntos', f.juntos, (v) => f.juntos = v),
                linha('Outras séries do teu par', f.outrasPar, (v) => f.outrasPar = v),
                linha('Outras séries dos amigos', f.outrasAmigos, (v) => f.outrasAmigos = v),
                linha('Comentários', f.comentarios, (v) => f.comentarios = v),
                linha('Convites e pedidos de amizade', f.convites, (v) => f.convites = v),
                const SizedBox(height: 12),
              ],
            ),
          );
        },
      ),
    );
  }

  // Leva ao sítio de cada notificação
  void _abrir(Notificacao n) {
    if (n.tipo == 'ep' && !n.juntos) {
      // Série que não vês com ela: abre o perfil dela (lá dá para te juntares)
      Navigator.of(context).push(MaterialPageRoute(builder: (_) => EcraPessoa(id: n.quem.id)));
    } else if (n.serie != null) {
      // Episódio ou comentário: a série, já no episódio certo
      Navigator.of(context).push(MaterialPageRoute(builder: (_) => EcraSerie(slug: n.serie!, episodio: n.episodio)));
    } else if (n.tipo == 'amigo') {
      // Pedido de amizade: volta e abre o separador Amigos
      Navigator.of(context).pop();
      separadorAtual.value = 2;
    } else {
      // Convite de série: aceita-se no Início, que está logo atrás
      Navigator.of(context).pop();
    }
  }

  @override
  Widget build(BuildContext context) {
    final p = Paleta.de(context);
    return Scaffold(
      body: Fundo(
        child: SafeArea(
          child: Column(
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(4, 6, 8, 0),
                child: Row(
                  children: [
                    const BackButton(),
                    Expanded(
                      child: Text('Notificações',
                          style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800)),
                    ),
                    // Escolher o que aparece
                    IconButton(
                      tooltip: 'O que mostrar',
                      icon: const Icon(Icons.tune_rounded),
                      onPressed: _filtros,
                    ),
                  ],
                ),
              ),
              Expanded(
                child: _lista == null
                    ? (_erro == null ? const Carregando() : ErroComRetry(mensagem: _erro!, aoTentar: _carregar))
                    : RefreshIndicator(onRefresh: _carregar, child: _corpo(p)),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _corpo(Paleta p) {
    final lista = _lista!;
    if (lista.isEmpty) {
      // ListView para o "puxar para atualizar" funcionar mesmo vazio
      return ListView(
        padding: const EdgeInsets.all(32),
        children: [
          const SizedBox(height: 60),
          const Center(child: Text('🔔', style: TextStyle(fontSize: 44))),
          const SizedBox(height: 12),
          Center(
            child: Text('Nada de novo nos últimos 30 dias.', textAlign: TextAlign.center, style: TextStyle(color: p.suave)),
          ),
        ],
      );
    }
    return ListView.separated(
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 32),
      itemCount: lista.length,
      separatorBuilder: (_, __) => const SizedBox(height: 10),
      itemBuilder: (_, i) => _linha(p, lista[i]),
    );
  }

  // Uma notificação: foto, texto, detalhe e quando; as novas com bolinha e tom do par
  Widget _linha(Paleta p, Notificacao n) {
    final textos = Theme.of(context).textTheme;
    final nova = _vistoAntes == null || n.em.isAfter(_vistoAntes!);
    return Vidro(
      padding: const EdgeInsets.all(12),
      raio: 22,
      tinta: nova ? LinearGradient(colors: [p.par.withValues(alpha: .16), p.par.withValues(alpha: .06)]) : null,
      onTap: () => _abrir(n),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Avatar(user: n.quem, cor: p.par, tamanho: 40),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(n.texto, style: textos.bodyMedium?.copyWith(fontWeight: FontWeight.w700)),
                if (n.detalhe.isNotEmpty) ...[
                  const SizedBox(height: 2),
                  Text(n.detalhe,
                      maxLines: 2, overflow: TextOverflow.ellipsis, style: textos.bodySmall?.copyWith(color: p.suave)),
                ],
                const SizedBox(height: 4),
                Text('${n.emoji}  ${diaDoRegisto(n.em)}', style: textos.labelSmall?.copyWith(color: p.suave)),
              ],
            ),
          ),
          // Bolinha das novas
          if (nova)
            Padding(
              padding: const EdgeInsets.only(left: 8, top: 4),
              child: Container(
                width: 9,
                height: 9,
                decoration: BoxDecoration(color: p.parTxt, shape: BoxShape.circle),
              ),
            ),
        ],
      ),
    );
  }
}
