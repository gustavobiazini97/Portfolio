// Modelos: o JSON da API convertido em classes Dart (com os mesmos nomes do PHP).

import 'package:flutter/painting.dart';

// Números do JSON podem chegar como int ou double; isto aceita os dois
int _int(dynamic v) => v == null ? 0 : (v as num).toInt();

// "#F7C59F" → Color
Color _cor(String? hex) {
  final limpo = (hex ?? '#C9C2E0').replaceFirst('#', '');
  return Color(int.parse('FF$limpo', radix: 16));
}

// ---------- Pessoas ----------

class Utilizador {
  final int id;
  final String nome;
  final String username;
  final String inicial;
  final String? foto; // URL absoluto (precisa do token) ou null

  const Utilizador({
    required this.id,
    required this.nome,
    required this.username,
    required this.inicial,
    this.foto,
  });

  factory Utilizador.deJson(Map<String, dynamic> j) => Utilizador(
        id: _int(j['id']),
        nome: j['nome'] as String,
        username: j['username'] as String,
        inicial: j['inicial'] as String? ?? '?',
        foto: j['foto'] as String?,
      );

  // Para campos opcionais do JSON (ex.: parceiro ainda sem conta)
  static Utilizador? talvez(dynamic j) =>
      j == null ? null : Utilizador.deJson(j as Map<String, dynamic>);
}

// ---------- Séries ----------

// Progresso de uma pessoa numa série
class Progresso {
  final int vistos; // episódios marcados
  final int pct; // percentagem da série
  final int posicao; // episódio mais avançado (o que conta no Vs)

  const Progresso({this.vistos = 0, this.pct = 0, this.posicao = 0});

  factory Progresso.deJson(Map<String, dynamic> j) =>
      Progresso(vistos: _int(j['vistos']), pct: _int(j['pct']), posicao: _int(j['posicao']));

  static Progresso? talvez(dynamic j) =>
      j == null ? null : Progresso.deJson(j as Map<String, dynamic>);
}

class Serie {
  final String slug;
  final String nome;
  final String nomeCurto;
  final String? anos;
  final int totalEpisodios;
  final int totalFillers; // só vem no detalhe da série
  final Color cor; // acento da série
  final Progresso tu;
  final Progresso? par; // null enquanto o par não tem conta
  final String resumo; // frase do Vs

  const Serie({
    required this.slug,
    required this.nome,
    required this.nomeCurto,
    this.anos,
    required this.totalEpisodios,
    this.totalFillers = 0,
    required this.cor,
    required this.tu,
    this.par,
    required this.resumo,
  });

  factory Serie.deJson(Map<String, dynamic> j) => Serie(
        slug: j['slug'] as String,
        nome: j['nome'] as String,
        nomeCurto: j['nomeCurto'] as String? ?? j['nome'] as String,
        anos: j['anos'] as String?,
        totalEpisodios: _int(j['totalEpisodios']),
        totalFillers: _int(j['totalFillers']),
        cor: _cor(j['cor'] as String?),
        tu: Progresso.deJson(j['tu'] as Map<String, dynamic>),
        par: Progresso.talvez(j['par']),
        resumo: j['resumo'] as String? ?? '',
      );
}

// Último episódio que alguém viu (cartão do Início)
class Ultimo {
  final String serie; // slug
  final String serieNome;
  final int numero;
  final String? titulo;
  final bool filler;
  final String quando; // "há 2 horas"

  const Ultimo({
    required this.serie,
    required this.serieNome,
    required this.numero,
    this.titulo,
    this.filler = false,
    this.quando = '',
  });

  static Ultimo? talvez(dynamic j) {
    if (j == null) return null;
    final m = j as Map<String, dynamic>;
    return Ultimo(
      serie: m['serie'] as String,
      serieNome: m['serieNome'] as String,
      numero: _int(m['numero']),
      titulo: m['titulo'] as String?,
      filler: m['filler'] == true,
      quando: m['quando'] as String? ?? '',
    );
  }
}

// Um cartão da pista de episódios. tu/par/coment mudam na app sem recarregar tudo.
class Episodio {
  final int id;
  final int numero;
  final String? titulo;
  final bool filler;
  bool tu; // tu já viste
  bool par; // o teu par já viu
  int coment; // número de comentários

  Episodio({
    required this.id,
    required this.numero,
    this.titulo,
    this.filler = false,
    this.tu = false,
    this.par = false,
    this.coment = 0,
  });

  factory Episodio.deJson(Map<String, dynamic> j) => Episodio(
        id: _int(j['id']),
        numero: _int(j['numero']),
        titulo: j['titulo'] as String?,
        filler: j['filler'] == true || j['filler'] == 1,
        tu: j['tu'] == true,
        par: j['par'] == true,
        coment: _int(j['coment']),
      );
}

// ---------- Comentários ----------

class Comentario {
  final int id;
  final String texto;
  final Utilizador autor;
  final bool meu; // só os teus se podem apagar
  final String quando;

  const Comentario({
    required this.id,
    required this.texto,
    required this.autor,
    required this.meu,
    required this.quando,
  });

  factory Comentario.deJson(Map<String, dynamic> j) => Comentario(
        id: _int(j['id']),
        texto: j['texto'] as String,
        autor: Utilizador.deJson(j['autor'] as Map<String, dynamic>),
        meu: j['meu'] == true,
        quando: j['quando'] as String? ?? '',
      );
}

// ---------- Estatísticas ----------

// Cartão de uma pessoa: vistos, horas e fillers
class PessoaEst {
  final Utilizador user;
  final bool ehTu; // 'tu' ou 'par' (decide a cor)
  final int vistos;
  final String horas; // já formatado pela API ("4,6 h")
  final String fillers; // "3 fillers vistos"

  PessoaEst.deJson(Map<String, dynamic> j)
      : user = Utilizador.deJson(j['user'] as Map<String, dynamic>),
        ehTu = j['cor'] == 'tu',
        vistos = _int(j['vistos']),
        horas = j['horas'] as String? ?? '',
        fillers = j['fillers'] as String? ?? '';
}

// Uma semana do gráfico do ritmo (nível 0–10 = altura relativa da barra)
class Semana {
  final String rotulo;
  final int tu, par, nivelTu, nivelPar;

  Semana.deJson(Map<String, dynamic> j)
      : rotulo = j['rotulo'] as String? ?? '',
        tu = _int(j['tu']),
        par = _int(j['par']),
        nivelTu = _int(j['nivelTu']),
        nivelPar = _int(j['nivelPar']);
}

// Um arco: intervalo de episódios e progresso de cada um
class Arco {
  final String nome;
  final int de, ate, total, pctTu, pctPar;
  final bool filler;
  final String selo; // 'os-dois' | 'tu' | 'par' | ''

  Arco.deJson(Map<String, dynamic> j)
      : nome = j['nome'] as String,
        de = _int(j['de']),
        ate = _int(j['ate']),
        total = _int(j['total']),
        pctTu = _int(j['pctTu']),
        pctPar = _int(j['pctPar']),
        filler = j['filler'] == true,
        selo = j['selo'] as String? ?? '';
}

class Estatisticas {
  final List<PessoaEst> pessoas;
  final List<Semana> semanas;
  final String? previsao;
  final List<Arco> arcos;
  final int comentarios;
  final int? recordeN; // mais episódios marcados num só dia
  final String? recordeQuem; // 'tu' ou o nome do par

  Estatisticas.deJson(Map<String, dynamic> j)
      : pessoas = (j['pessoas'] as List).map((p) => PessoaEst.deJson(p as Map<String, dynamic>)).toList(),
        semanas = (j['semanas'] as List).map((s) => Semana.deJson(s as Map<String, dynamic>)).toList(),
        previsao = j['previsao'] as String?,
        arcos = (j['arcos'] as List).map((a) => Arco.deJson(a as Map<String, dynamic>)).toList(),
        comentarios = _int(j['comentarios']),
        recordeN = j['recorde'] == null ? null : _int(j['recorde']['n']),
        recordeQuem = j['recorde'] == null ? null : j['recorde']['quem'] as String?;
}

// ---------- Notificações ----------

class Preferencias {
  String email;
  bool epPush, epEmail, comPush, comEmail;

  Preferencias.deJson(Map<String, dynamic> j)
      : email = j['email'] as String? ?? '',
        epPush = j['ep_push'] == true,
        epEmail = j['ep_email'] == true,
        comPush = j['com_push'] == true,
        comEmail = j['com_email'] == true;

  // Formato que o PUT /perfil/notificacoes espera
  Map<String, dynamic> paraJson() => {
        'email': email,
        'ep_push': epPush,
        'ep_email': epEmail,
        'com_push': comPush,
        'com_email': comEmail,
      };
}
