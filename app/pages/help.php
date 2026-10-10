<?php
$pageTitle = 'Ayuda';
$navActive = 'help';
if (!current_user()) {
    $layout = 'plain';
}
?>
<div class="page-title"><h1>Manual del juego</h1></div>

<div class="box"><div class="box-head">Lo básico</div><div class="box-body">
    <p>Eres el mánager de un club de fútbol. Cada semana se juega una jornada de liga (y, si quieres, un amistoso), tus jugadores entrenan y el club cobra y paga. No hace falta estar conectado cuando se juegan los partidos: sólo tienes que dejar preparada la alineación.</p>
    <p>Cada temporada dura <?= Config::int('game', 'season_weeks', 16) ?> semanas: 14 jornadas en series de 8 equipos (todos contra todos, ida y vuelta) y semanas de descanso al final.</p>
    <table class="table compact">
        <tr><th>Evento</th><th>Cuándo</th></tr>
        <tr><td>Amistosos</td><td><?= e(ucfirst(weekday_name(Config::int('game', 'friendly_weekday', 3)))) ?> <?= e((string)Config::get('game', 'friendly_time')) ?></td></tr>
        <tr><td>Entrenamiento</td><td><?= e(ucfirst(weekday_name(Config::int('game', 'training_weekday', 4)))) ?> <?= e((string)Config::get('game', 'training_time')) ?></td></tr>
        <tr><td>Economía</td><td><?= e(ucfirst(weekday_name(Config::int('game', 'economy_weekday', 5)))) ?> <?= e((string)Config::get('game', 'economy_time')) ?></td></tr>
        <tr><td>Liga</td><td><?= e(ucfirst(weekday_name(Config::int('game', 'league_weekday', 6)))) ?> <?= e((string)Config::get('game', 'league_time')) ?></td></tr>
    </table>
</div></div>

<div class="grid-2">
<div class="box"><div class="box-head">Escala de habilidades</div>
    <table class="table compact">
        <?php foreach (SKILL_LEVELS as $i => $n): ?><tr><td class="num" style="width:40px"><?= $i ?></td><td><?= e($n) ?></td></tr><?php endforeach; ?>
    </table>
</div>
<div class="stack">
<div class="box"><div class="box-head">Habilidades</div><div class="box-body">
    <ul>
        <li><strong>Portería:</strong> la del portero.</li>
        <li><strong>Defensa:</strong> centrales y laterales; también aporta en el medio.</li>
        <li><strong>Jugadas:</strong> crear juego en el medio campo, que decide quién tiene la posesión.</li>
        <li><strong>Lateral:</strong> regate y centro por banda. Es clave en los ataques por las bandas (extremos, laterales y delanteros escorados): el mejor de cada banda puede desbordar y poner centros peligrosos. También ayuda un poco a defender la banda.</li>
        <li><strong>Pases:</strong> ayuda a todos los ataques.</li>
        <li><strong>Anotación:</strong> definir las ocasiones, sobre todo por el centro.</li>
        <li><strong>Balón parado:</strong> faltas y córners.</li>
        <li><strong>Condición:</strong> la forma física; evita bajar el rendimiento en la segunda parte.</li>
        <li><strong>Forma y experiencia:</strong> multiplican el rendimiento de todas las demás.</li>
    </ul>
</div></div>
<div class="box"><div class="box-head">Especialidades</div><div class="box-body">
    <ul>
        <?php foreach (SPECIALTIES as $k => [$n, $i]): ?>
            <li><?= $i ?> <strong><?= e($n) ?></strong>: <?= e([
                'technical' => 'jugadas de calidad que crean ocasiones por el centro.',
                'quick' => 'desbordes por banda (extremos y delanteros).',
                'powerful' => 'se impone físicamente en el área (delanteros).',
                'unpredictable' => 'puede crear una ocasión... o regalarla al rival.',
                'head' => 'peligroso en centros y balón parado.',
                'resilient' => 'sus lesiones son más leves.',
            ][$k]) ?></li>
        <?php endforeach; ?>
    </ul>
</div></div>
</div>
</div>
<br>
<div class="box"><div class="box-head">Partidos</div><div class="box-body">
    <p>Cada equipo obtiene siete valoraciones: medio campo, defensa (derecha, central, izquierda) y ataque (derecho, central, izquierdo). El medio campo decide cuántas ocasiones tiene cada equipo; cada ocasión se resuelve enfrentando el ataque de un sector con la defensa del sector rival opuesto (tu ataque derecho contra su defensa izquierda).</p>
    <p><strong>Tácticas:</strong> Presión (menos ocasiones para ambos), Contraataque (ocasiones extra tras fallos rivales si defiendes bien), Ataque por el centro / por las bandas (cambia la distribución de los ataques), Tiros lejanos.</p>
    <p><strong>Actitud:</strong> en un partido «PIC» juegas peor en el medio pero sube el espíritu; en un «MOTS» juegas mejor pero baja el espíritu. Jugar en casa da ventaja en el medio campo.</p>
    <p>Dos amarillas acumuladas en liga suponen un partido de sanción; una roja, también.</p>
</div></div>
<div class="box"><div class="box-head">Alineación</div><div class="box-body">
    <p>Arrastra a tus jugadores desde la plantilla hasta el campo o el banquillo con el ratón. Suelta un jugador sobre otro para intercambiarlos, y arrástralo de vuelta a la lista (o haz doble clic) para retirarlo. Si lo prefieres, haz clic en un jugador y después en la posición. Al pasar el ratón por encima de un jugador verás su ficha: habilidades, forma, condición, especialidad, lesiones y en qué posición rinde mejor. Mientras arrastras, las posiciones se colorean (verde, amarillo o rojo) según lo bien que rendiría en cada una comparado con el mejor de tu plantilla.</p>
</div></div>
<div class="box"><div class="box-head">Copa</div><div class="box-body">
    <p>Cada país disputa su <strong>Copa</strong>: participan todos los equipos de todas las divisiones a eliminación directa y a partido único. Se juega en la fecha de los amistosos (una ronda por semana). Si el número de equipos no es potencia de 2, hay una ronda previa entre los equipos de las divisiones más bajas. Juega en casa el equipo de la división inferior y, si hay empate, se decide por penaltis.</p>
    <p>Mientras tu equipo siga vivo en la Copa <strong>no puede jugar amistosos</strong>. Cada ronda superada da una prima de <?= e(money(Config::int('cup', 'round_prize', 8000))) ?>; el campeón gana <?= e(money(Config::int('cup', 'winner_prize', 150000))) ?> y el trofeo de la Copa para su vitrina.</p>
</div></div>
<div class="box"><div class="box-head">Estadio y público</div><div class="box-body">
    <p>La asistencia a cada partido depende de tus <strong>socios</strong>, del ánimo de la afición, de la <strong>confianza</strong> en el equipo, de tu división, del tirón del rival, de la importancia del partido (las rondas finales de copa y el final de liga atraen más público; los amistosos, menos) y del clima. La crónica de cada partido indica cuántos aficionados entraron y qué porcentaje del aforo se ocupó.</p>
    <p>Si se agotan las entradas recibirás un mensaje sugiriéndote <strong>ampliar el estadio</strong> (con una estimación de los aficionados que se quedaron fuera); si durante varios partidos seguidos no llegas a la mitad del aforo, te aconsejaremos <strong>reducirlo</strong> para ahorrar mantenimiento.</p>
</div></div>
<div class="box"><div class="box-head">Entrenamiento</div><div class="box-body">
    <p>Una vez por semana tus jugadores mejoran la habilidad que elijas, pero sólo si han jugado en la posición adecuada (por ejemplo, Anotación sólo la entrenan los delanteros). Los jugadores jóvenes y con nivel bajo mejoran más rápido. A partir de los 29 años las habilidades empiezan a bajar poco a poco. Un buen entrenador y sus ayudantes aceleran el proceso.</p>
    <p>El entrenador y cada miembro del personal tienen su <strong>perfil</strong> (nombre, edad, nacionalidad, carácter e imagen). El carácter del entrenador influye en el juego: el <em>exigente</em> entrena más pero baja el espíritu, el <em>motivador</em> lo sube, el <em>paternal</em> hace crecer más a los jóvenes, el <em>táctico</em> mejora el medio campo, el <em>carismático</em> anima a la afición…</p>
</div></div>
<div class="box"><div class="box-head">Naciones y Mundial</div><div class="box-body">
    <p>Cada país tiene su propia liga, con la misma estructura de series de 8 equipos, ascensos y descensos. Al registrarte puedes elegir en qué país empieza tu club.</p>
    <p>Cada nación tiene una <strong>selección nacional</strong> que convoca automáticamente a los jugadores de su nacionalidad con mayor TSI en cada posición (2 porteros, 3 centrales, 3 laterales, 4 mediocampistas, 3 extremos y 3 delanteros), estén en el club que estén. Los lesionados no son convocados.</p>
    <p>Las selecciones disputan el <strong>Mundial</strong>: 32 selecciones en 8 grupos de 4, octavos de final, cuartos, semifinales, tercer puesto y final, una jornada por semana en campo neutral. Las eliminatorias empatadas se deciden por penaltis. Los clubes reciben una prima por cada partido que jueguen sus internacionales, y los jugadores ganan mucha experiencia… aunque también se pueden lesionar.</p>
</div></div>
<div class="box"><div class="box-head">Patrocinio</div><div class="box-body">
    <p>Antes de cada temporada recibes <strong>3 ofertas</strong> de empresas que quieren patrocinar a tu club. El dinero depende de la división en la que juegues (y las empresas más prestigiosas sólo se fijan en las divisiones altas):</p>
    <ul>
        <li><strong>Contrato fijo:</strong> el pago semanal más alto y una prima por firmar. Dura una temporada.</li>
        <li><strong>Por objetivos:</strong> menos fijo, pero una prima por cada victoria en liga y un gran premio si ganas tu serie.</li>
        <li><strong>Largo plazo:</strong> 2 o 3 temporadas cobrando lo mismo aunque desciendas, con una buena prima de firma.</li>
    </ul>
    <p>Tienes hasta la primera actualización económica de la temporada para elegir; si no, se firma automáticamente el contrato fijo. El patrocinador paga cada semana en la actualización económica. Además, la publicidad del estadio aporta un ingreso según tus socios.</p>
</div></div>
<div class="box"><div class="box-head">Economía y mercado</div><div class="box-body">
    <p>Ingresas dinero por taquilla (partidos en casa), tu patrocinador principal, la publicidad (según tus socios y su ánimo) y premios de fin de temporada. Pagas salarios, personal, mantenimiento del estadio y cantera cada semana.</p>
    <p>En el mercado de fichajes los jugadores se venden en subasta durante <?= Config::int('game', 'transfer_hours', 72) ?> horas. Cada puja debe superar a la anterior en un <?= Config::int('game', 'min_bid_increment', 5) ?>% como mínimo, y si alguien puja en los últimos minutos el cierre se alarga. El dinero de las pujas que vas ganando queda reservado.</p>
</div></div>
