// Configuração da app.

// Endereço da API. Por defeito é o servidor do alwaysdata; para testar com o PHP local:
//   flutter run --dart-define=API_URL=http://10.0.2.2:8000/api.php
// (10.0.2.2 é o "localhost" do PC visto de dentro do emulador Android)
const String apiUrl = String.fromEnvironment(
  'API_URL',
  defaultValue: 'https://animeadois.alwaysdata.net/api.php',
);

// Nome que aparece no topo e no login
const String appNome = 'Anime a Dois';

// Link curto para instalar a app (o .htaccess do site redireciona para o APK mais recente).
// Vai nos convites partilhados em Amigos.
const String linkApp = 'https://animeadois.alwaysdata.net/app';
