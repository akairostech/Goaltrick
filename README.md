# GoalTrick v1.10.3 — juego de gestión de fútbol (estilo Hattrick)

Juego web multijugador de gestión de un club de fútbol, hecho en **PHP 8 + MySQL**, sin dependencias externas.

## Funcionalidades

- **Usuarios**: registro (con pregunta anti‑bots), inicio de sesión, «recordarme», bloqueo por intentos fallidos, cambio de contraseña/correo, recuperación de contraseña (por correo o enlace generado por el administrador), abandonar equipo, roles de administrador.
- **Equipos**: al registrarte recibes un equipo de la división más baja con 18 jugadores, estadio, personal y dinero.
- **Ligas**: pirámide de divisiones (1 en el nivel I, 4 en el II, 16 en el III…) de 8 equipos, 14 jornadas a doble vuelta, ascensos/descensos y premios por posición. Los equipos sin dueño los controla la máquina.
- **Jugadores**: 7 habilidades (0‑20, de «inexistente» a «divino»), resistencia, forma, experiencia, liderazgo, fidelidad, especialidades, TSI, salario, lesiones, tarjetas, edad (1 año = 112 días), retiradas.
- **Motor de partidos**: 7 valoraciones por sector, posesión por medio campo, ocasiones por bandas/centro, balón parado, tiros lejanos, contraataques, 6 tácticas, actitud PIC/Normal/MOTS, órdenes individuales, eventos por especialidad, tarjetas, lesiones con sustituciones, cansancio, crónica minuto a minuto y valoración en estrellas.
- **Entrenamiento** semanal según la posición jugada, intensidad, parte de resistencia, nivel del entrenador y ayudantes; declive por edad.
- **Mercado de fichajes**: subastas con plazo, incremento mínimo, prórroga por pujas de última hora, dinero reservado, filtros de búsqueda, historial. Los bots también compran y venden.
- **Economía**: taquilla, patrocinadores, salarios, personal, mantenimiento y ampliación del estadio, cantera, premios. Libro de movimientos y resumen semanal.
- **Naciones** (v1.1): 16 países (Panamá, Argentina, Brasil, Colombia, Costa Rica, España, México, Uruguay, Chile, Perú, Italia, Portugal, Francia, Alemania, Inglaterra y Países Bajos), cada uno con su propia liga en el mismo formato de series, equipos con nombres locales y jugadores con nombres de su idioma. Al registrarse se elige el país.
- **Selecciones nacionales** (v1.1): convocan automáticamente a los jugadores disponibles de su nacionalidad con mayor TSI en cada posición (2 porteros, 3 centrales, 3 laterales, 4 medios, 3 extremos y 3 delanteros), jueguen en el club que jueguen.
- **Mundial de Fútbol** (v1.1): 16 selecciones, 4 grupos de 4, cuartos, semifinales, tercer puesto y final, en campo neutral y con penaltis en las eliminatorias. Partidos internacionales, goles con la selección, primas para los clubes y palmarés.
- **Vitrina de trofeos** (v1.2): en el resumen del club y en su página pública, con trofeos ilustrados para los campeones de la I, II y III división.
- **Imágenes de perfil**: 5000 avatares caricaturescos distintos de futbolistas (`assets/img/avatars/`) y otros 5000 del personal técnico (`assets/img/staff/`).
- **Habilidades con los nombres de Hattrick** (v1.2): Condición (resistencia), Jugadas (antes Construcción), Lateral (con más peso en el motor: el mejor de cada banda desborda y centra), Pases, Anotación, Defensa, Portería y Balón parado.
- **Administración** (v1.2): editar cualquier equipo (dinero, personal, entrenamiento, cantera, estadio) y sus jugadores (habilidades, edad, lesiones, imagen, traspasar a otro equipo, crear o quitar jugadores), y reiniciar por completo la partida conservando o no las cuentas.
- **Patrocinio** (v1.3): 400 empresas ficticias de 25 rubros con su logo (`sql/companies.sql`, `assets/img/sponsors/`). Antes de cada temporada cada club recibe 3 ofertas según su división (contrato fijo, por objetivos o largo plazo), con pago semanal, prima de firma, primas por victoria o por título. Si el mánager no elige a tiempo se firma el contrato fijo; los bots eligen solos.
- **32 naciones** (v1.4): se suman Ecuador, Paraguay, Venezuela, Bolivia, Honduras, Guatemala, Estados Unidos, Canadá, Bélgica, Suiza, Croacia, Dinamarca, Japón, Corea del Sur, Marruecos y Nigeria, cada una con su liga, nombres locales y selección. El Mundial pasa a 32 selecciones (8 grupos de 4 y octavos de final).
- **Copa de liga** (v1.4): en cada país, todos los equipos de todas sus divisiones a eliminación directa (ronda previa si hace falta, penaltis, juega en casa el de división inferior). Se juega en la fecha de los amistosos; mientras un equipo siga vivo no puede jugar amistosos. Primas por ronda, premio al campeón y subcampeón, trofeo ilustrado en la vitrina, página «Copa» con rondas, goleadores y palmarés.
- **Entradas dinámicas** (v1.4): la asistencia depende de socios, ánimo, confianza, división, rival, importancia del partido y clima; aparece en la crónica. Mensajes automáticos al mánager cuando se llena el estadio (sugiere ampliarlo) o cuando queda por debajo del 50 % varios partidos (sugiere reducirlo).
- **Perfil del personal** (v1.4): el entrenador y cada empleado tienen nombre, edad, nacionalidad, carácter (8 tipos con efectos del entrenador en entrenamiento, espíritu, confianza, afición o táctica) e imagen (120 caras, `assets/img/staff/`). Candidatos nuevos cada semana.
- **Alineación con arrastrar y soltar** (v1.4): tarjetas con la cara y el nombre de cada jugador sobre el campo, intercambio, banquillo, clic + clic como alternativa, colores según el rendimiento en cada posición y ficha emergente con todas sus estadísticas.
- **Idiomas** (v1.5): toda la web (menús, páginas, crónicas, noticias, mensajes del sistema, movimientos de finanzas) en **español** e **inglés**, con selector de idioma en la barra superior. El idioma se guarda en el perfil del usuario (y en una cookie para visitantes). Los textos que se guardan en la base de datos se almacenan como plantilla, así que cada usuario los lee en su idioma.
- **Estadio ilustrado** (v1.5): dibujo caricaturesco generado al vuelo con seis modelos según la capacidad (campo de barrio, estadio municipal, moderno, gran estadio, de élite y coloso) y el tipo de localidades: gradas de pie en los fondos, asientos de colores, tribunas con techo y palcos VIP; además focos, marcador y grúas mientras hay obras. Vista previa de la ampliación antes de encargarla y miniatura en la página pública del club.
- **Alineaciones guardadas** (v1.5): alineación **predeterminada**, que se usa automáticamente en los partidos para los que no envías alineación, y hasta **3 alineaciones guardadas** con nombre para cargarlas en el editor con un clic.
- **Sección Club** (v1.6): resumen general del club (escudo, uniforme, datos, plantilla, temporada, historial y vitrina) y elección entre **1000 uniformes** y **1000 escudos** caricaturescos generados en SVG (`art.php`), con galería filtrable por diseño, forma, símbolo y color. Cada club puede **subir su propio uniforme o escudo** (PNG/JPG/GIF/WEBP, se guardan en `assets/uploads/`). El escudo aparece en la barra lateral, el resumen, la página pública del club, los partidos y el historial de traspasos.
- **Transferencias** (v1.6): sección propia en el menú con buscador, mis ventas y pujas, **historial de transferencias** (fecha, jugador, procedencia/destino, precio y TSI del jugador en ese momento) con total de compras, total de ventas, diferencia, número de traspasos y sus promedios, y los últimos traspasos del mundo.
- **Previa de los partidos** (v1.6): escudos junto al nombre de cada equipo, gráfico del índice de rendimiento estimado (las 7 valoraciones y un índice global), resumen comparado de los dos equipos, pronóstico (probabilidades y marcador más probable con un modelo de Poisson calibrado con el motor) y encuesta entre los socios de cada club.
- **v1.7**: «Club» reúne en una sola sección el resumen del club (noticias, próximos partidos, datos, plantilla, calendario, historial y vitrina), el uniforme y el escudo. En «Partidos» cada equipo lleva su escudo y hay botones de previa/crónica y de alineación: **cada partido puede tener su propia alineación enviada** (tabla `match_lineups`); si no se envía, se usa la predeterminada. Las «series» pasan a llamarse «divisiones».
- **Ayuda y soporte** (v1.8): los usuarios abren tickets por categorías (error del juego, reportar a un usuario, cuenta, mercado, sugerencia...), con enlace opcional y denuncias confidenciales; botones «Reportar» en los mensajes recibidos y en la página de cada club. La administración tiene su bandeja de tickets (filtros, asignación, estados, respuestas y notas internas) y el usuario recibe un aviso en su buzón al ser respondido.
- **Moderación** (v1.8): ficha de investigación de cada usuario con sus traspasos (y con qué mánagers), pujas, mensajes enviados y recibidos (aunque los haya borrado), finanzas, amistosos, partidos, noticias, tickets, denuncias recibidas, IP de acceso y otras cuentas desde las mismas IP. Sanciones con motivo: aviso, suspensión temporal (1 a 365 días, se levanta sola), expulsión permanente (opcionalmente quitándole el equipo) y readmisión, con historial. El usuario sancionado ve el motivo al intentar entrar.
- **Administración de equipos** (v1.8): cambiar el escudo y el uniforme de cualquier club (diseño nº 1-1000 con vista previa, subir una imagen o quitar una imagen inapropiada).
- **Caras con el uniforme del club** (v1.9): las 5000 caras de jugador se combinan con los 1000 uniformes (5 millones de imágenes posibles) generándose al vuelo con `face.php`, así cada jugador aparece con la camiseta (colores, diseño, mangas, cuello y número) del uniforme que ha elegido su club. Si el club ha subido un uniforme propio, la camiseta usa los colores del último diseño elegido de la galería. Los jugadores sin club muestran su imagen original.
- **Perfil público del mánager** (v1.10): `index.php?page=manager&id=N`, enlazado desde la ficha de cada equipo, el buscador, los conectados y los mensajes. Muestra cara de presidente, lema, país y ciudad, edad, club favorito, presentación, días en el cargo, balance de partidos, goles, mayor goleada, títulos, fichajes y ventas, vitrina de trofeos, últimos partidos e historial de temporadas. Cada mánager lo edita en «Mi perfil → Editar mi perfil» y elige su cara entre **1000 presidentes caricaturescos** (`assets/img/presidents/`) agrupados en 8 tonos de piel de 125 caras cada uno (Porcelana, Marfil, Miel, Arena, Canela, Avellana, Cacao y Ébano). Al pulsar el nombre de usuario en la barra superior se abre un menú con «Mi cuenta» y «Mi perfil».
- **v1.10.1**: banderas en imagen SVG (`assets/img/flags/`, se ven igual en Windows, donde los emojis de banderas no aparecen), miniaturas cuadradas de los mánagers, escudos en los rankings de «Mundo» y en los rivales sugeridos de «Amistosos», y el próximo partido de «Club» en una sola línea.
- **v1.10.2**: en «Partidos», columnas más juntas para que la tabla quepa sin barra de desplazamiento lateral (los nombres de equipo muy largos se recortan con «…» y se ven completos al pasar el ratón), y el resultado de los penales va debajo del marcador en letra más pequeña.
- **v1.10.3**: todos los emojis se muestran como imágenes (Twemoji, licencia CC-BY 4.0, en `assets/img/emoji/`, con el script `assets/js/emoji.js`), para que se vean igual en todos los sistemas, incluidos los emojis que escriban los usuarios; en «Partidos», los botones muestran solo el icono con un tooltip con su texto, y el resultado de los penales va entre paréntesis y en negrita.
- **Otros**: amistosos, cantera, personal, mensajes privados, anuncios, buscador de equipos, rankings, panel de administración (incluye «modo pruebas» para adelantar el calendario).

## Requisitos

- PHP 8.1 o superior con `pdo_mysql` y `mbstring` (XAMPP 8.1/8.2 sirve tal cual).
- MySQL 5.7+ / 8.x o MariaDB 10.3+.
- Apache (recomendado, incluye `.htaccess`) o el servidor integrado de PHP.

## Instalación con XAMPP (Windows)

1. Copia la carpeta del juego a `C:\xampp\htdocs\goaltrick`.
2. Inicia **Apache** y **MySQL** desde el panel de XAMPP.
3. Edita `config.ini` → sección `[database]` (por defecto: usuario `root` sin contraseña, base de datos `goaltrick`).
4. Abre `http://localhost/goaltrick/install.php`, rellena la cuenta de administrador y pulsa **Instalar**.
   El instalador crea la base de datos, ejecuta `sql/schema.sql` y `sql/seed.sql`, genera la liga y tu equipo.
5. Entra en `http://localhost/goaltrick/`.

### Instalación manual de la base de datos (opcional)
Si prefieres importar tú las tablas (por ejemplo con phpMyAdmin): crea la base de datos `goaltrick` con cotejamiento `utf8mb4_unicode_ci`, importa `sql/schema.sql` y después `sql/seed.sql`. Luego ejecuta igualmente `install.php` (detecta las tablas y genera el mundo y el administrador).

### Instalación por consola
```
php cron/run.php --install admin admin@correo.com MiContraseña "Mi Equipo FC"
```

## Actualizar desde una versión anterior (1.0 – 1.9)

1. Copia los archivos nuevos encima de los antiguos **conservando tu `config.ini`** (o copia en él los datos de tu base de datos). Las opciones nuevas (`other_country_divisions` y la sección `[worldcup]`) tienen valores por defecto, así que no es obligatorio añadirlas.
2. Abre la web: se te redirigirá a `upgrade.php`. Pulsa **Actualizar ahora**. (Por consola: `php cron/run.php --upgrade`.)
3. El actualizador aplica todos los cambios pendientes en orden. De 1.9 a 1.10 añade los datos del perfil público a los usuarios (su país parte del país de su club). De 1.7 a 1.8 crea las tablas de tickets, sanciones y accesos. De 1.6 a 1.7 crea la tabla de alineaciones por partido y conserva la que ya hubieras enviado. De 1.5 a 1.6 añade el uniforme y el escudo de cada club y guarda el TSI de los jugadores en cada traspaso (para los traspasos antiguos usa su TSI actual). De 1.4 a 1.5 añade el idioma de cada usuario, las alineaciones guardadas y amplía algunas columnas de texto; además vuelve a sortear el Mundial de la temporada en curso con las 32 selecciones (si ya había empezado, se anulan sus partidos y empieza de nuevo; si ya no cabe en el calendario, el de la temporada siguiente tendrá las 32). De 1.3 a 1.4 crea las ligas de las 16 naciones nuevas (jugando las jornadas ya disputadas), da un perfil a todo el personal existente, envía ofertas de patrocinio a los clubes nuevos y sortea la Copa de cada país (puede tardar unos minutos). De 1.2 a 1.3 amplía las caras de jugador a 400, carga las empresas y envía las ofertas de patrocinio de la temporada en curso. De 1.1 a 1.2 añade la imagen de perfil de los jugadores. De 1.0 a 1.1 además añade las columnas y tablas nuevas, renombra Futbolandia a Panamá, crea las ligas de los demás países (jugando las jornadas que ya se hubieran disputado esta temporada), crea las selecciones y programa el Mundial. No se borra nada de tu partida.

`sql/upgrade_v1.1.sql` … `sql/upgrade_v1.10.sql` contienen los mismos cambios de tablas por si prefieres aplicarlos a mano en phpMyAdmin (después abre igualmente `upgrade.php`).

## Tareas programadas (partidos, entrenamiento, economía)

El calendario se guarda en la tabla `scheduled_events`. Hay dos formas de ejecutarlo:

- **Automático por visitas** (`auto_update = true` en `config.ini`): cada vez que alguien visita la web se ejecuta lo pendiente (como máximo una vez cada 30 s). Ideal para pruebas o servidores pequeños.
- **Programador de tareas / cron** (recomendado en producción, pon `auto_update = false`):
  - Windows: Programador de tareas → nueva tarea cada 5 minutos que ejecute
    `C:\xampp\php\php.exe "C:\xampp\htdocs\goaltrick\cron\run.php"`
  - Linux: `*/5 * * * * php /ruta/goaltrick/cron/run.php`
  - Por URL (servicios externos de cron): `index.php?page=cron&key=TU_CLAVE` (cambia `cron_key` en `config.ini`).

Comandos útiles:
```
php cron/run.php --status         # próximos eventos
php cron/run.php --force-next 4   # ejecuta ya los 4 siguientes eventos (pruebas)
```
Desde **Administración → Juego** también puedes forzar eventos o «Simular hasta la próxima jornada».

## Idiomas

Los textos se escriben en español en el código y pasan por `t('...')`. Cada idioma es un archivo `lang/<código>.php` con pares «texto en español» => «traducción»; si falta una traducción se muestra el texto en español.

- **Añadir un idioma** (por ejemplo francés): `php cron/lang.php --new fr Français 🇫🇷` crea `lang/fr.php` con todos los textos listos para traducir (también puedes copiar `lang/en.php` a mano y cambiar `@name` y `@flag`). Aparece solo en el selector.
- `php cron/lang.php` muestra cuánto está traducido cada idioma; `--missing en` lista lo que falta y `--sync en` añade los textos nuevos tras actualizar el juego.
- Idioma por defecto: `language` en la sección `[app]` de `config.ini` (`es` o `en`).

## Imágenes subidas

Los uniformes y escudos que suben los clubes se guardan en `assets/uploads/kits` y `assets/uploads/crests` (la carpeta debe tener permiso de escritura; en XAMPP ya lo tiene). Sólo se aceptan imágenes PNG, JPG, GIF o WEBP reales, con un tamaño máximo configurable (`upload_max_kb` en `[app]`), y el `.htaccess` de esa carpeta impide ejecutar código. Haz copia de esa carpeta junto con la base de datos.

## Configuración (`config.ini`)

| Sección | Qué controla |
|---|---|
| `[database]` | Servidor MySQL: host, puerto, nombre, usuario, contraseña |
| `[app]` | Nombre del sitio, URL base, zona horaria, moneda, modo debug, registro abierto |
| `[game]` | País principal, nº de divisiones de su liga y de las de los demás países (`other_country_divisions`), dinero inicial, días/horas de cada evento, velocidad de entrenamiento, duración de subastas, límites de plantilla, bots en el mercado |
| `[worldcup]` | Activar el Mundial, cada cuántas temporadas, semana de inicio, día y hora de los partidos, prima para los clubes |
| `[sponsors]` | Pago semanal base del patrocinador por división, días para elegir oferta, publicidad por socio |
| `[economy]` | Precios de entradas, mantenimiento y construcción del estadio, patrocinadores, salarios del personal, cantera, premios |
| `[mail]` | Envío de correos de recuperación |
| `[security]` | Intentos de login, tiempo de bloqueo, clave del cron por URL |

> Cambiar `divisions` u `other_country_divisions` sólo afecta a instalaciones nuevas (el mundo se genera al instalar).
>
> Con la configuración por defecto hay unos 770 equipos y 14.000 jugadores. Las actualizaciones semanales (entrenamiento, jornada de liga) tardan algunos segundos; en un servidor real es preferible usar el Programador de tareas (`auto_update = false`).

## Estructura

```
index.php            Controlador frontal (index.php?page=...)
install.php          Instalador web
upgrade.php          Actualizador de la base de datos entre versiones
config.ini           Configuración
app/bootstrap.php    Arranque y autoload
app/lib/             Config, DB (PDO), Auth, GameState, Installer, helpers
app/game/            Lógica del juego: PlayerFactory, Lineup, MatchEngine, Training,
                     Economy, LeagueService, TeamService, TransferService,
                     FriendlyService, BotAI, Scheduler
app/pages/           Páginas
app/views/layout.php Plantilla común
assets/              CSS y JavaScript
sql/schema.sql       Tablas
sql/seed.sql         Datos iniciales (países, estado del juego)
sql/upgrade_v1.1.sql Cambios de tablas de la v1.0 a la v1.1 (opcional, manual)
sql/upgrade_v1.2.sql Cambios de tablas de la v1.1 a la v1.2 (opcional, manual)
sql/upgrade_v1.3.sql Cambios de tablas de la v1.2 a la v1.3 (opcional, manual)
sql/upgrade_v1.4.sql … sql/upgrade_v1.10.sql  Cambios de tablas de cada versión (opcional, manual)
sql/companies.sql    Las 400 empresas patrocinadoras
assets/img/          Trofeos (trophies/), caras de jugadores (avatars/), del personal (staff/),
                     de presidentes para el perfil (presidents/) y logos de empresas (sponsors/), en SVG
cron/run.php         Tareas programadas por consola
```

## Seguridad

Contraseñas con `password_hash`, consultas preparadas (PDO), tokens CSRF en todos los formularios, cookies `HttpOnly`/`SameSite`, tokens de «recordarme» y de recuperación guardados como hash, bloqueo por fuerza bruta, `.htaccess` que impide acceder a `config.ini`, `app/`, `sql/` y `cron/`. En producción pon `debug = false` y cambia `cron_key`.
