# GoalTrick v1.4 — juego de gestión de fútbol (estilo Hattrick)

Juego web multijugador de gestión de un club de fútbol, hecho en **PHP 8 + MySQL**, sin dependencias externas.

## Funcionalidades

- **Usuarios**: registro (con pregunta anti‑bots), inicio de sesión, «recordarme», bloqueo por intentos fallidos, cambio de contraseña/correo, recuperación de contraseña (por correo o enlace generado por el administrador), abandonar equipo, roles de administrador.
- **Equipos**: al registrarte recibes un equipo de la división más baja con 18 jugadores, estadio, personal y dinero.
- **Ligas**: pirámide de divisiones (1 serie en la I, 4 en la II, 16 en la III…) de 8 equipos, 14 jornadas a doble vuelta, ascensos/descensos y premios por posición. Los equipos sin dueño los controla la máquina.
- **Jugadores**: 7 habilidades (0‑20, de «inexistente» a «divino»), resistencia, forma, experiencia, liderazgo, fidelidad, especialidades, TSI, salario, lesiones, tarjetas, edad (1 año = 112 días), retiradas.
- **Motor de partidos**: 7 valoraciones por sector, posesión por medio campo, ocasiones por bandas/centro, balón parado, tiros lejanos, contraataques, 6 tácticas, actitud PIC/Normal/MOTS, órdenes individuales, eventos por especialidad, tarjetas, lesiones con sustituciones, cansancio, crónica minuto a minuto y valoración en estrellas.
- **Entrenamiento** semanal según la posición jugada, intensidad, parte de resistencia, nivel del entrenador y ayudantes; declive por edad.
- **Mercado de fichajes**: subastas con plazo, incremento mínimo, prórroga por pujas de última hora, dinero reservado, filtros de búsqueda, historial. Los bots también compran y venden.
- **Economía**: taquilla, patrocinadores, salarios, personal, mantenimiento y ampliación del estadio, cantera, premios. Libro de movimientos y resumen semanal.
- **Naciones** (v1.1): 16 países (Panamá, Argentina, Brasil, Colombia, Costa Rica, España, México, Uruguay, Chile, Perú, Italia, Portugal, Francia, Alemania, Inglaterra y Países Bajos), cada uno con su propia liga en el mismo formato de series, equipos con nombres locales y jugadores con nombres de su idioma. Al registrarse se elige el país.
- **Selecciones nacionales** (v1.1): convocan automáticamente a los jugadores disponibles de su nacionalidad con mayor TSI en cada posición (2 porteros, 3 centrales, 3 laterales, 4 medios, 3 extremos y 3 delanteros), jueguen en el club que jueguen.
- **Mundial de Fútbol** (v1.1): 16 selecciones, 4 grupos de 4, cuartos, semifinales, tercer puesto y final, en campo neutral y con penaltis en las eliminatorias. Partidos internacionales, goles con la selección, primas para los clubes y palmarés.
- **Vitrina de trofeos** (v1.2): en el resumen del club y en su página pública, con trofeos ilustrados para los campeones de la I, II y III división.
- **Imágenes de perfil**: 400 avatares caricaturescos distintos de futbolistas (`assets/img/avatars/`), uno por jugador.
- **Habilidades con los nombres de Hattrick** (v1.2): Condición (resistencia), Jugadas (antes Construcción), Lateral (con más peso en el motor: el mejor de cada banda desborda y centra), Pases, Anotación, Defensa, Portería y Balón parado.
- **Administración** (v1.2): editar cualquier equipo (dinero, personal, entrenamiento, cantera, estadio) y sus jugadores (habilidades, edad, lesiones, imagen, traspasar a otro equipo, crear o quitar jugadores), y reiniciar por completo la partida conservando o no las cuentas.
- **Patrocinio** (v1.3): 400 empresas ficticias de 25 rubros con su logo (`sql/companies.sql`, `assets/img/sponsors/`). Antes de cada temporada cada club recibe 3 ofertas según su división (contrato fijo, por objetivos o largo plazo), con pago semanal, prima de firma, primas por victoria o por título. Si el mánager no elige a tiempo se firma el contrato fijo; los bots eligen solos.
- **32 naciones** (v1.4): se suman Ecuador, Paraguay, Venezuela, Bolivia, Honduras, Guatemala, Estados Unidos, Canadá, Bélgica, Suiza, Croacia, Dinamarca, Japón, Corea del Sur, Marruecos y Nigeria, cada una con su liga, nombres locales y selección. El Mundial pasa a 32 selecciones (8 grupos de 4 y octavos de final).
- **Copa de liga** (v1.4): en cada país, todos los equipos de todas sus divisiones a eliminación directa (ronda previa si hace falta, penaltis, juega en casa el de división inferior). Se juega en la fecha de los amistosos; mientras un equipo siga vivo no puede jugar amistosos. Primas por ronda, premio al campeón y subcampeón, trofeo ilustrado en la vitrina, página «Copa» con rondas, goleadores y palmarés.
- **Entradas dinámicas** (v1.4): la asistencia depende de socios, ánimo, confianza, división, rival, importancia del partido y clima; aparece en la crónica. Mensajes automáticos al mánager cuando se llena el estadio (sugiere ampliarlo) o cuando queda por debajo del 50 % varios partidos (sugiere reducirlo).
- **Perfil del personal** (v1.4): el entrenador y cada empleado tienen nombre, edad, nacionalidad, carácter (8 tipos con efectos del entrenador en entrenamiento, espíritu, confianza, afición o táctica) e imagen (120 caras, `assets/img/staff/`). Candidatos nuevos cada semana.
- **Alineación con arrastrar y soltar** (v1.4): tarjetas con la cara y el nombre de cada jugador sobre el campo, intercambio, banquillo, clic + clic como alternativa, colores según el rendimiento en cada posición y ficha emergente con todas sus estadísticas.
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

## Actualizar desde una versión anterior (1.0 – 1.3)

1. Copia los archivos nuevos encima de los antiguos **conservando tu `config.ini`** (o copia en él los datos de tu base de datos). Las opciones nuevas (`other_country_divisions` y la sección `[worldcup]`) tienen valores por defecto, así que no es obligatorio añadirlas.
2. Abre la web: se te redirigirá a `upgrade.php`. Pulsa **Actualizar ahora**. (Por consola: `php cron/run.php --upgrade`.)
3. El actualizador aplica todos los cambios pendientes en orden. De 1.3 a 1.4 crea las ligas de las 16 naciones nuevas (jugando las jornadas ya disputadas), da un perfil a todo el personal existente, envía ofertas de patrocinio a los clubes nuevos y sortea la Copa de cada país (puede tardar unos minutos). De 1.2 a 1.3 amplía las caras de jugador a 400, carga las empresas y envía las ofertas de patrocinio de la temporada en curso. De 1.1 a 1.2 añade la imagen de perfil de los jugadores. De 1.0 a 1.1 además añade las columnas y tablas nuevas, renombra Futbolandia a Panamá, crea las ligas de los demás países (jugando las jornadas que ya se hubieran disputado esta temporada), crea las selecciones y programa el Mundial. No se borra nada de tu partida.

`sql/upgrade_v1.1.sql` … `sql/upgrade_v1.4.sql` contienen los mismos cambios de tablas por si prefieres aplicarlos a mano en phpMyAdmin (después abre igualmente `upgrade.php`).

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
sql/companies.sql    Las 400 empresas patrocinadoras
assets/img/          Trofeos (trophies/), avatares de jugadores (avatars/) y logos de empresas (sponsors/), en SVG
cron/run.php         Tareas programadas por consola
```

## Seguridad

Contraseñas con `password_hash`, consultas preparadas (PDO), tokens CSRF en todos los formularios, cookies `HttpOnly`/`SameSite`, tokens de «recordarme» y de recuperación guardados como hash, bloqueo por fuerza bruta, `.htaccess` que impide acceder a `config.ini`, `app/`, `sql/` y `cron/`. En producción pon `debug = false` y cambia `cron_key`.
