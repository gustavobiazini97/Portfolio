// Modelos: o JSON da API convertido em classes Dart (com os mesmos nomes do PHP).
// Cada pessoa tem a sua biblioteca; cada série pode ser vista com uma ou mais pessoas (companheiros).

import 'package:flutter/painting.dart';

// Números do JSON podem chegar como int ou double; isto aceita os dois
int _int(dynamic v) => v == null ? 0 : (v as num).toInt();

// Lista do JSON (ou nada) → lista de mapas
List<Map<String, dynamic>> _lista(dynamic v) =>
    v == null ? [] : (v as List).map((e) => e as Map<String, dynamic>).toList();

// "#F7C59F" → Color
Color corDeHex(String? hex) {
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

  // Para campos opcionais do JSON (ex.: par ainda sem conta)
  static Utilizador? talvez(dynamic j) =>
      j == null ? null : Utilizador.deJson(j as Map<String, dynamic>);

  static List<Utilizador> lista(dynamic j) => _lista(j).map(Utilizador.deJson).toList();
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
}

// Dados fixos de uma série (iguais para toda a gente)
class Serie {
  final String slug;
  final String nome;
  final String nomeCurto;
  final String? anos;
  final int totalEpisodios;
  final String? capa; // URL da capa (AniList/MyAnimeList) ou null
  final String? tipo; // TV, Movie, OVA…
  final bool emEmissao;
  final int totalFillers; // só vem na página da série
  final Color cor; // acento da série

  const Serie({
    required this.slug,
    required this.nome,
    required this.nomeCurto,
    this.anos,
    required this.totalEpisodios,
    this.capa,
    this.tipo,
    this.emEmissao = false,
    this.totalFillers = 0,
    required this.cor,
  });

  factory Serie.deJson(Map<String, dynamic> j) => Serie(
        slug: j['slug'] as String,
        nome: j['nome'] as String,
        nomeCurto: j['nomeCurto'] as String? ?? j['nome'] as String,
        anos: j['anos'] as String?,
        totalEpisodios: _int(j['totalEpisodios']),
        capa: j['capa'] as String?,
        tipo: j['tipo'] as String?,
        emEmissao: j['emEmissao'] == true,
        totalFillers: _int(j['totalFillers']),
        cor: corDeHex(j['cor'] as String?),
      );
}

// Um companheiro numa série: quem vê contigo e a percentagem dele
class Companheiro {
  final Utilizador user;
  final int pct;

  const Companheiro(this.user, this.pct);

  factory Companheiro.deJson(Map<String, dynamic> j) =>
      Companheiro(Utilizador.deJson(j['user'] as Map<String, dynamic>), _int(j['pct']));
}

// Um cartão da fila de capas (a tua biblioteca, a do par ou a de um amigo)
class ItemBiblioteca {
  final Serie serie;
  final String estado; // a_ver | pausa | acabado
  final String estadoTexto; // "A ver", "Em pausa", "Acabado"
  final bool conjunta; // vista com alguém
  final int pct; // a percentagem do dono da biblioteca
  final List<Companheiro> companheiros;
  final bool naTua; // (perfil de outra pessoa) já está na tua biblioteca?
  final bool vesCom; // (perfil de outra pessoa) já a vês com ela?

  const ItemBiblioteca({
    required this.serie,
    required this.estado,
    required this.estadoTexto,
    required this.conjunta,
    required this.pct,
    required this.companheiros,
    this.naTua = false,
    this.vesCom = false,
  });

  factory ItemBiblioteca.deJson(Map<String, dynamic> j) => ItemBiblioteca(
        serie: Serie.deJson(j['serie'] as Map<String, dynamic>),
        estado: j['estado'] as String? ?? 'a_ver',
        estadoTexto: j['estadoTexto'] as String? ?? '',
        conjunta: j['conjunta'] == true,
        pct: _int(j['pct']),
        companheiros: _lista(j['companheiros']).map(Companheiro.deJson).toList(),
        naTua: j['naTua'] == true,
        vesCom: j['vesCom'] == true,
      );

  static List<ItemBiblioteca> lista(dynamic j) => _lista(j).map(ItemBiblioteca.deJson).toList();
}

// Convite "Quero ver contigo" (recebido ou enviado)
class ConviteSerie {
  final Serie serie;
  final bool recebido;
  final Utilizador de;
  final Utilizador para;

  const ConviteSerie({required this.serie, required this.recebido, required this.de, required this.para});

  factory ConviteSerie.deJson(Map<String, dynamic> j) => ConviteSerie(
        serie: Serie.deJson(j['serie'] as Map<String, dynamic>),
        recebido: j['recebido'] == true,
        de: Utilizador.deJson(j['de'] as Map<String, dynamic>),
        para: Utilizador.deJson(j['para'] as Map<String, dynamic>),
      );

  // A outra pessoa do convite (quem convidou, se recebido; quem foi convidado, se enviado)
  Utilizador get outro => recebido ? de : para;
}

// Último episódio que alguém viu (cartão do Início e do perfil de amigo)
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

// Um cartão da pista de episódios. tu/com/coment mudam na app sem recarregar tudo.
class Episodio {
  final int id;
  final int numero;
  final String? titulo;
  final bool filler;
  final bool recap;
  bool tu; // tu já viste
  final List<bool> com; // cada companheiro já viu? (pela ordem dos companheiros)
  int coment; // número de comentários (teus e dos companheiros)

  Episodio({
    required this.id,
    required this.numero,
    this.titulo,
    this.filler = false,
    this.recap = false,
    this.tu = false,
    this.com = const [],
    this.coment = 0,
  });

  factory Episodio.deJson(Map<String, dynamic> j) => Episodio(
        id: _int(j['id']),
        numero: _int(j['numero']),
        titulo: j['titulo'] as String?,
        filler: j['filler'] == true || j['filler'] == 1,
        recap: j['recap'] == true || j['recap'] == 1,
        tu: j['tu'] == true,
        com: j['com'] == null ? const [] : (j['com'] as List).map((v) => v == true).toList(),
        coment: _int(j['coment']),
      );

  // Algum companheiro já viu?
  bool get algumCom => com.any((v) => v);
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

// Uma semana do gráfico do ritmo (níveis 0–10 = altura relativa das barras)
class Semana {
  final String rotulo;
  final int tu, nivelTu;
  final List<int> com, nivelCom; // um valor por companheiro

  Semana.deJson(Map<String, dynamic> j)
      : rotulo = j['rotulo'] as String? ?? '',
        tu = _int(j['tu']),
        nivelTu = _int(j['nivelTu']),
        com = j['com'] == null ? const [] : (j['com'] as List).map(_int).toList(),
        nivelCom = j['nivelCom'] == null ? const [] : (j['nivelCom'] as List).map(_int).toList();
}

// Um arco: intervalo de episódios e o progresso de cada pessoa
class Arco {
  final String nome;
  final int de, ate, total, pctTu;
  final List<int> pctCom;
  final bool filler;
  final String selo; // 'os-dois' | 'tu' | 'par' | ''

  Arco.deJson(Map<String, dynamic> j)
      : nome = j['nome'] as String,
        de = _int(j['de']),
        ate = _int(j['ate']),
        total = _int(j['total']),
        pctTu = _int(j['pctTu']),
        pctCom = j['pctCom'] == null ? const [] : (j['pctCom'] as List).map(_int).toList(),
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
  final String? recordeQuem; // 'tu' ou o nome do companheiro

  Estatisticas.deJson(Map<String, dynamic> j)
      : pessoas = _lista(j['pessoas']).map(PessoaEst.deJson).toList(),
        semanas = _lista(j['semanas']).map(Semana.deJson).toList(),
        previsao = j['previsao'] as String?,
        arcos = _lista(j['arcos']).map(Arco.deJson).toList(),
        comentarios = _int(j['comentarios']),
        recordeN = j['recorde'] == null ? null : _int(j['recorde']['n']),
        recordeQuem = j['recorde'] == null ? null : j['recorde']['quem'] as String?;
}

// ---------- Amigos ----------

// Link de convite para quem ainda não tem conta
class ConviteAmigo {
  final String link;
  final String validoAte; // ISO 8601

  const ConviteAmigo(this.link, this.validoAte);

  static ConviteAmigo? talvez(dynamic j) {
    if (j == null) return null;
    final m = j as Map<String, dynamic>;
    return ConviteAmigo(m['link'] as String, m['validoAte'] as String? ?? '');
  }
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
