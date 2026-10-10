<?php
$pageTitle = t('Ayuda');
$navActive = 'help';
if (!current_user()) {
    $layout = 'plain';
}
?>
<div class="page-title"><h1><?= e(t('Manual del juego')) ?></h1><?php if (current_user()): ?><a class="btn btn-accent" href="<?= e(url('support')) ?>">🆘 <?= e(t('¿Tienes un problema? Contacta con soporte')) ?></a><?php endif; ?></div>

<div class="box"><div class="box-head"><?= e(t('Lo básico')) ?></div><div class="box-body">
    <p><?= e(t('Eres el mánager de un club de fútbol. Cada semana se juega una jornada de liga (y, si quieres, un amistoso), tus jugadores entrenan y el club cobra y paga. No hace falta estar conectado cuando se juegan los partidos: sólo tienes que dejar preparada la alineación.')) ?></p>
    <p><?= e(t('Cada temporada dura {weeks} semanas: 14 jornadas en divisiones de 8 equipos (todos contra todos, ida y vuelta) y semanas de descanso al final.', ['weeks' => Config::int('game', 'season_weeks', 16)])) ?></p>
    <table class="table compact">
        <tr><th><?= e(t('Evento')) ?></th><th><?= e(t('Cuándo')) ?></th></tr>
        <tr><td><?= e(t('Amistosos')) ?></td><td><?= e(ucfirst(weekday_name(Config::int('game', 'friendly_weekday', 3)))) ?> <?= e((string)Config::get('game', 'friendly_time')) ?></td></tr>
        <tr><td><?= e(t('Entrenamiento')) ?></td><td><?= e(ucfirst(weekday_name(Config::int('game', 'training_weekday', 4)))) ?> <?= e((string)Config::get('game', 'training_time')) ?></td></tr>
        <tr><td><?= e(t('Economía')) ?></td><td><?= e(ucfirst(weekday_name(Config::int('game', 'economy_weekday', 5)))) ?> <?= e((string)Config::get('game', 'economy_time')) ?></td></tr>
        <tr><td><?= e(t('Liga')) ?></td><td><?= e(ucfirst(weekday_name(Config::int('game', 'league_weekday', 6)))) ?> <?= e((string)Config::get('game', 'league_time')) ?></td></tr>
    </table>
</div></div>

<div class="grid-2">
<div class="box"><div class="box-head"><?= e(t('Escala de habilidades')) ?></div>
    <table class="table compact">
        <?php foreach (SKILL_LEVELS as $i => $n): ?><tr><td class="num" style="width:40px"><?= $i ?></td><td><?= e(t($n)) ?></td></tr><?php endforeach; ?>
    </table>
</div>
<div class="stack">
<div class="box"><div class="box-head"><?= e(t('Habilidades')) ?></div><div class="box-body">
    <ul>
        <li><?= t('<strong>Portería:</strong> la del portero.') ?></li>
        <li><?= t('<strong>Defensa:</strong> centrales y laterales; también aporta en el medio.') ?></li>
        <li><?= t('<strong>Jugadas:</strong> crear juego en el medio campo, que decide quién tiene la posesión.') ?></li>
        <li><?= t('<strong>Lateral:</strong> regate y centro por banda. Es clave en los ataques por las bandas (extremos, laterales y delanteros escorados): el mejor de cada banda puede desbordar y poner centros peligrosos. También ayuda un poco a defender la banda.') ?></li>
        <li><?= t('<strong>Pases:</strong> ayuda a todos los ataques.') ?></li>
        <li><?= t('<strong>Anotación:</strong> definir las ocasiones, sobre todo por el centro.') ?></li>
        <li><?= t('<strong>Balón parado:</strong> faltas y córners.') ?></li>
        <li><?= t('<strong>Condición:</strong> la forma física; evita bajar el rendimiento en la segunda parte.') ?></li>
        <li><?= t('<strong>Forma y experiencia:</strong> multiplican el rendimiento de todas las demás.') ?></li>
    </ul>
</div></div>
<div class="box"><div class="box-head"><?= e(t('Especialidades')) ?></div><div class="box-body">
    <ul>
        <?php foreach (SPECIALTIES as $k => [$n, $i]): ?>
            <li><?= $i ?> <strong><?= e(specialty_name($k)) ?></strong>: <?= e(t([
                'technical' => 'jugadas de calidad que crean ocasiones por el centro.',
                'quick' => 'desbordes por banda (extremos y delanteros).',
                'powerful' => 'se impone físicamente en el área (delanteros).',
                'unpredictable' => 'puede crear una ocasión... o regalarla al rival.',
                'head' => 'peligroso en centros y balón parado.',
                'resilient' => 'sus lesiones son más leves.',
            ][$k])) ?></li>
        <?php endforeach; ?>
    </ul>
</div></div>
</div>
</div>
<br>
<div class="box"><div class="box-head"><?= e(t('Partidos')) ?></div><div class="box-body">
    <p><?= e(t('Cada equipo obtiene siete valoraciones: medio campo, defensa (derecha, central, izquierda) y ataque (derecho, central, izquierdo). El medio campo decide cuántas ocasiones tiene cada equipo; cada ocasión se resuelve enfrentando el ataque de un sector con la defensa del sector rival opuesto (tu ataque derecho contra su defensa izquierda).')) ?></p>
    <p><?= t('<strong>Tácticas:</strong> Presión (menos ocasiones para ambos), Contraataque (ocasiones extra tras fallos rivales si defiendes bien), Ataque por el centro / por las bandas (cambia la distribución de los ataques), Tiros lejanos.') ?></p>
    <p><?= t('<strong>Actitud:</strong> en un partido «PIC» juegas peor en el medio pero sube el espíritu; en un «MOTS» juegas mejor pero baja el espíritu. Jugar en casa da ventaja en el medio campo.') ?></p>
    <p><?= e(t('Dos amarillas acumuladas en liga suponen un partido de sanción; una roja, también.')) ?></p>
</div></div>
<div class="box"><div class="box-head"><?= e(t('Alineación')) ?></div><div class="box-body">
    <p><?= e(t('Arrastra a tus jugadores desde la plantilla hasta el campo o el banquillo con el ratón. Suelta un jugador sobre otro para intercambiarlos, y arrástralo de vuelta a la lista (o haz doble clic) para retirarlo. Si lo prefieres, haz clic en un jugador y después en la posición. Al pasar el ratón por encima de un jugador verás su ficha: habilidades, forma, condición, especialidad, lesiones y en qué posición rinde mejor. Mientras arrastras, las posiciones se colorean (verde, amarillo o rojo) según lo bien que rendiría en cada una comparado con el mejor de tu plantilla.')) ?></p>
</div></div>
<div class="box"><div class="box-head"><?= e(t('Previa de los partidos')) ?></div><div class="box-body">
    <p><?= e(t('Antes de cada partido puedes abrir su previa: verás el índice de rendimiento estimado de los dos equipos (sus siete valoraciones por sector y un índice global de 0 a 100), un resumen comparado de ambos clubes, el pronóstico con las probabilidades de victoria, empate y derrota y el marcador más probable, y lo que opinan los socios de cada club. Los socios son más optimistas cuanto mejor es el ánimo de la afición y la confianza del equipo.')) ?></p>
</div></div>
<div class="box"><div class="box-head"><?= e(t('Club: uniforme y escudo')) ?></div><div class="box-body">
    <p><?= e(t('En la sección «Club» tienes el resumen general de tu club y puedes elegir entre 1000 uniformes y 1000 escudos, o subir tus propias imágenes (PNG con fondo transparente recomendado: 300×345 píxeles para el uniforme y 256×256 para el escudo). El escudo aparece junto al nombre de tu equipo en las previas, los partidos y la página pública del club.')) ?></p>
</div></div>
<div class="box"><div class="box-head"><?= e(t('Copa')) ?></div><div class="box-body">
    <p><?= t('Cada país disputa su <strong>Copa</strong>: participan todos los equipos de todas las divisiones a eliminación directa y a partido único. Se juega en la fecha de los amistosos (una ronda por semana). Si el número de equipos no es potencia de 2, hay una ronda previa entre los equipos de las divisiones más bajas. Juega en casa el equipo de la división inferior y, si hay empate, se decide por penaltis.') ?></p>
    <p><?= t('Mientras tu equipo siga vivo en la Copa <strong>no puede jugar amistosos</strong>. Cada ronda superada da una prima de {round}; el campeón gana {winner} y el trofeo de la Copa para su vitrina.', ['round' => e(money(Config::int('cup', 'round_prize', 8000))), 'winner' => e(money(Config::int('cup', 'winner_prize', 150000)))]) ?></p>
</div></div>
<div class="box"><div class="box-head"><?= e(t('Estadio y público')) ?></div><div class="box-body">
    <p><?= t('La asistencia a cada partido depende de tus <strong>socios</strong>, del ánimo de la afición, de la <strong>confianza</strong> en el equipo, de tu división, del tirón del rival, de la importancia del partido (las rondas finales de copa y el final de liga atraen más público; los amistosos, menos) y del clima. La crónica de cada partido indica cuántos aficionados entraron y qué porcentaje del aforo se ocupó.') ?></p>
    <p><?= t('Si se agotan las entradas recibirás un mensaje sugiriéndote <strong>ampliar el estadio</strong> (con una estimación de los aficionados que se quedaron fuera); si durante varios partidos seguidos no llegas a la mitad del aforo, te aconsejaremos <strong>reducirlo</strong> para ahorrar mantenimiento.') ?></p>
</div></div>
<div class="box"><div class="box-head"><?= e(t('Entrenamiento')) ?></div><div class="box-body">
    <p><?= e(t('Una vez por semana tus jugadores mejoran la habilidad que elijas, pero sólo si han jugado en la posición adecuada (por ejemplo, Anotación sólo la entrenan los delanteros). Los jugadores jóvenes y con nivel bajo mejoran más rápido. A partir de los 29 años las habilidades empiezan a bajar poco a poco. Un buen entrenador y sus ayudantes aceleran el proceso.')) ?></p>
    <p><?= t('El entrenador y cada miembro del personal tienen su <strong>perfil</strong> (nombre, edad, nacionalidad, carácter e imagen). El carácter del entrenador influye en el juego: el <em>exigente</em> entrena más pero baja el espíritu, el <em>motivador</em> lo sube, el <em>paternal</em> hace crecer más a los jóvenes, el <em>táctico</em> mejora el medio campo, el <em>carismático</em> anima a la afición…') ?></p>
</div></div>
<div class="box"><div class="box-head"><?= e(t('Naciones y Mundial')) ?></div><div class="box-body">
    <p><?= e(t('Cada país tiene su propia liga, con la misma estructura de divisiones de 8 equipos, ascensos y descensos. Al registrarte puedes elegir en qué país empieza tu club.')) ?></p>
    <p><?= t('Cada nación tiene una <strong>selección nacional</strong> que convoca automáticamente a los jugadores de su nacionalidad con mayor TSI en cada posición (2 porteros, 3 centrales, 3 laterales, 4 mediocampistas, 3 extremos y 3 delanteros), estén en el club que estén. Los lesionados no son convocados.') ?></p>
    <p><?= t('Las selecciones disputan el <strong>Mundial</strong>: 32 selecciones en 8 grupos de 4, octavos de final, cuartos, semifinales, tercer puesto y final, una jornada por semana en campo neutral. Las eliminatorias empatadas se deciden por penaltis. Los clubes reciben una prima por cada partido que jueguen sus internacionales, y los jugadores ganan mucha experiencia… aunque también se pueden lesionar.') ?></p>
</div></div>
<div class="box"><div class="box-head"><?= e(t('Patrocinio')) ?></div><div class="box-body">
    <p><?= t('Antes de cada temporada recibes <strong>3 ofertas</strong> de empresas que quieren patrocinar a tu club. El dinero depende de la división en la que juegues (y las empresas más prestigiosas sólo se fijan en las divisiones altas):') ?></p>
    <ul>
        <li><?= t('<strong>Contrato fijo:</strong> el pago semanal más alto y una prima por firmar. Dura una temporada.') ?></li>
        <li><?= t('<strong>Por objetivos:</strong> menos fijo, pero una prima por cada victoria en liga y un gran premio si ganas tu división.') ?></li>
        <li><?= t('<strong>Largo plazo:</strong> 2 o 3 temporadas cobrando lo mismo aunque desciendas, con una buena prima de firma.') ?></li>
    </ul>
    <p><?= e(t('Tienes hasta la primera actualización económica de la temporada para elegir; si no, se firma automáticamente el contrato fijo. El patrocinador paga cada semana en la actualización económica. Además, la publicidad del estadio aporta un ingreso según tus socios.')) ?></p>
</div></div>
<div class="box"><div class="box-head"><?= e(t('Ayuda, soporte y normas')) ?></div><div class="box-body">
    <p><?= e(t('Si encuentras un error en el juego, tienes un problema con tu cuenta o quieres denunciar a otro mánager (insultos, mensajes molestos, multicuentas, trampas o aprovechamiento de errores), abre un ticket en «Ayuda y soporte». La administración te responderá por ese mismo ticket y recibirás un aviso en tu buzón. Las denuncias son confidenciales.')) ?></p>
    <p><?= e(t('No está permitido tener más de una cuenta, traspasar dinero o jugadores entre cuentas a precios ficticios, aprovechar errores del juego ni acosar a otros usuarios. La administración puede enviar avisos, suspender temporalmente o expulsar del juego a quien incumpla las normas.')) ?></p>
</div></div>
<div class="box"><div class="box-head"><?= e(t('Economía y mercado')) ?></div><div class="box-body">
    <p><?= e(t('Ingresas dinero por taquilla (partidos en casa), tu patrocinador principal, la publicidad (según tus socios y su ánimo) y premios de fin de temporada. Pagas salarios, personal, mantenimiento del estadio y cantera cada semana.')) ?></p>
    <p><?= e(t('En el mercado de fichajes los jugadores se venden en subasta durante {hours} horas. Cada puja debe superar a la anterior en un {pct}% como mínimo, y si alguien puja en los últimos minutos el cierre se alarga. El dinero de las pujas que vas ganando queda reservado.', ['hours' => Config::int('game', 'transfer_hours', 72), 'pct' => Config::int('game', 'min_bid_increment', 5)])) ?></p>
    <p><?= e(t('En «Transferencias» → «Historial de transferencias» tienes todas tus compras y ventas con su fecha, el equipo de procedencia y de destino, el precio y el TSI del jugador en ese momento, además del total gastado, el total ingresado, la diferencia, el número de traspasos y sus promedios.')) ?></p>
</div></div>
