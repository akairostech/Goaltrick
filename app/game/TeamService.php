<?php
declare(strict_types=1);

/**
 * Creación de equipos, asignación a usuarios, cantera y personal.
 */
final class TeamService
{
    private const PREFIX = ['Atlético', 'Deportivo', 'Real', 'Club', 'Sporting', 'Unión', 'Racing', 'Independiente',
        'CD', 'CF', 'Juventud', 'Estrella', 'Olímpico', 'Academia', 'Defensores de', 'Nacional', 'Rayo', 'Cultural'];
    private const TOWN = ['San Lorenzo', 'Villaverde', 'Puerto Claro', 'Monteagudo', 'Valle Alto', 'Santa Rita', 'Río Seco',
        'Las Palmas', 'El Prado', 'Campo Real', 'Torreblanca', 'Villanueva', 'Peñalta', 'Mirasol', 'Los Robles',
        'Aguas Claras', 'Bellavista', 'Costa Azul', 'Sierra Norte', 'La Ribera', 'Puente Viejo', 'San Martín',
        'Altamira', 'Buenavista', 'El Molino', 'La Esperanza', 'Monte Verde', 'Nueva Aurora', 'Piedras Blancas',
        'Pueblo Nuevo', 'Rincón', 'San Felipe', 'Santa Clara', 'Valdemar', 'Villarreal del Sur', 'Los Andes',
        'Cerro Grande', 'Fuentesol', 'Laguna Azul', 'Marquesado', 'Palmira', 'Quintana', 'Robledal', 'Solano',
        'Tierra Alta', 'Valparaíso del Norte', 'Zarzal', 'Arenales', 'Bosque Bajo', 'Castellar', 'Doña Elvira',
        'El Faro', 'Granada Vieja', 'Hontanar', 'Isla Verde', 'Jardines', 'Los Olivos', 'Miraflores', 'Navarrete',
        'Ocaña Alta', 'Portillo', 'Riofrío', 'Salinas', 'Trinidad', 'Urbina', 'Vistalegre', 'Yerbabuena'];

    private const PANAMA_TOWNS = ['Colón', 'David', 'Chitré', 'Santiago', 'La Chorrera', 'Penonomé', 'Aguadulce', 'Las Tablas',
        'Boquete', 'Bocas del Toro', 'Arraiján', 'San Miguelito', 'Changuinola', 'Portobelo', 'Pedasí', 'Volcán', 'Antón', 'El Valle'];

    /** Prefijos y poblaciones por país (código ISO) para dar ambiente local a los nombres */
    private const LOCAL = [
        'AR' => [['Atlético', 'Club', 'Racing', 'Independiente', 'Deportivo', 'Sportivo'], ['Rosario', 'Córdoba', 'Mendoza', 'La Plata', 'Tucumán', 'Mar del Plata', 'Salta', 'Santa Fe', 'Bahía Blanca', 'Paraná', 'Quilmes', 'Lanús', 'Banfield', 'Tandil', 'Rafaela', 'San Luis', 'Neuquén', 'Posadas', 'Junín', 'Olavarría']],
        'BR' => [['Esporte Clube', 'Atlético', 'Grêmio', 'União', 'Sport', 'Clube Atlético'], ['Campinas', 'Santos', 'Recife', 'Fortaleza', 'Salvador', 'Curitiba', 'Goiânia', 'Belém', 'Manaus', 'Natal', 'Londrina', 'Joinville', 'Juiz de Fora', 'Ribeirão Preto', 'Sorocaba', 'Maceió', 'Niterói', 'Uberlândia', 'Pelotas', 'Bragança']],
        'CO' => [['Atlético', 'Deportivo', 'Independiente', 'Real', 'Unión', 'Club'], ['Medellín', 'Cali', 'Barranquilla', 'Cartagena', 'Bucaramanga', 'Pereira', 'Manizales', 'Ibagué', 'Pasto', 'Neiva', 'Tunja', 'Montería', 'Villavicencio', 'Armenia', 'Santa Marta', 'Popayán', 'Valledupar', 'Envigado', 'Sincelejo', 'Palmira']],
        'ES' => [['Real', 'Club Deportivo', 'Unión Deportiva', 'Atlético', 'Racing', 'Sporting'], ['Zaragoza', 'Valladolid', 'Oviedo', 'Burgos', 'Cádiz', 'Córdoba', 'Murcia', 'Elche', 'Huesca', 'Logroño', 'Albacete', 'Salamanca', 'León', 'Badajoz', 'Lugo', 'Almería', 'Castellón', 'Jaén', 'Cartagena', 'Ponferrada']],
        'MX' => [['Club', 'Deportivo', 'Atlético', 'Real', 'Unión', 'Club Social'], ['Guadalajara', 'Monterrey', 'Puebla', 'Toluca', 'León', 'Querétaro', 'Tijuana', 'Mérida', 'Veracruz', 'Morelia', 'Pachuca', 'Oaxaca', 'Durango', 'Zacatecas', 'Saltillo', 'Celaya', 'Irapuato', 'Cancún', 'Tampico', 'Culiacán']],
        'UY' => [['Club Atlético', 'Defensor', 'Racing', 'Liverpool', 'Deportivo', 'Club'], ['Salto', 'Paysandú', 'Rivera', 'Maldonado', 'Tacuarembó', 'Melo', 'Mercedes', 'Artigas', 'Minas', 'Florida', 'Durazno', 'Rocha', 'Colonia', 'Treinta y Tres', 'San José', 'Canelones', 'Fray Bentos', 'Carmelo', 'Young', 'Pando']],
        'CL' => [['Deportes', 'Club', 'Unión', 'Everton de', 'Rangers de', 'Deportivo'], ['Valparaíso', 'Concepción', 'Temuco', 'Antofagasta', 'Iquique', 'Rancagua', 'Talca', 'Chillán', 'Osorno', 'Puerto Montt', 'La Serena', 'Calama', 'Arica', 'Copiapó', 'Curicó', 'Valdivia', 'Coquimbo', 'Quillota', 'Linares', 'San Felipe']],
        'PE' => [['Club', 'Sport', 'Deportivo', 'Atlético', 'Unión', 'Juan Aurich de'], ['Arequipa', 'Trujillo', 'Chiclayo', 'Piura', 'Cusco', 'Huancayo', 'Iquitos', 'Tacna', 'Ica', 'Puno', 'Cajamarca', 'Ayacucho', 'Chimbote', 'Huánuco', 'Tumbes', 'Juliaca', 'Moquegua', 'Pucallpa', 'Sullana', 'Huaraz']],
        'IT' => [['AC', 'US', 'Sporting', 'Virtus', 'Atletico', 'Real'], ['Bergamo', 'Brescia', 'Parma', 'Modena', 'Bari', 'Palermo', 'Perugia', 'Cagliari', 'Verona', 'Lecce', 'Pisa', 'Ancona', 'Trieste', 'Padova', 'Vicenza', 'Como', 'Monza', 'Cremona', 'Salerno', 'Frosinone']],
        'PT' => [['Sporting', 'Clube Desportivo', 'Académico', 'União', 'Futebol Clube', 'Desportivo'], ['Braga', 'Coimbra', 'Aveiro', 'Faro', 'Setúbal', 'Leiria', 'Viseu', 'Guimarães', 'Funchal', 'Évora', 'Chaves', 'Famalicão', 'Barcelos', 'Portimão', 'Estoril', 'Tondela', 'Vizela', 'Arouca', 'Covilhã', 'Penafiel']],
        'FR' => [['FC', 'AS', 'Olympique', 'Stade', 'Racing', 'US'], ['Lyon', 'Nantes', 'Bordeaux', 'Lille', 'Rennes', 'Nice', 'Toulouse', 'Montpellier', 'Reims', 'Brest', 'Metz', 'Lens', 'Auxerre', 'Lorient', 'Angers', 'Caen', 'Nîmes', 'Dijon', 'Troyes', 'Amiens']],
        'DE' => [['FC', 'SV', 'VfB', 'TSV', 'Borussia', 'SC'], ['Bremen', 'Freiburg', 'Mainz', 'Bochum', 'Augsburg', 'Kiel', 'Rostock', 'Kassel', 'Paderborn', 'Bielefeld', 'Hannover', 'Nürnberg', 'Karlsruhe', 'Darmstadt', 'Regensburg', 'Ulm', 'Essen', 'Aachen', 'Münster', 'Jena']],
        'GB' => [['', '', '', '', '', ''], ['Bristol', 'Plymouth', 'Norwich', 'Ipswich', 'Derby', 'Stoke', 'Hull', 'Reading', 'Preston', 'Blackburn', 'Coventry', 'Luton', 'Swindon', 'Exeter', 'Oxford', 'Cambridge', 'Barnsley', 'Wigan', 'Bolton', 'Portsmouth']],
        'NL' => [['FC', 'SC', 'VV', 'Sparta', 'Go Ahead', 'RKC'], ['Utrecht', 'Groningen', 'Twente', 'Heerenveen', 'Zwolle', 'Breda', 'Tilburg', 'Arnhem', 'Nijmegen', 'Almere', 'Den Bosch', 'Venlo', 'Emmen', 'Leeuwarden', 'Helmond', 'Dordrecht', 'Haarlem', 'Maastricht', 'Deventer', 'Alkmaar']],
        'EC' => [['Club Deportivo', 'Liga Deportiva', 'Sociedad Deportiva', 'Club Sport', 'Deportivo', 'Independiente'], ['Guayaquil', 'Cuenca', 'Ambato', 'Manta', 'Loja', 'Riobamba', 'Portoviejo', 'Machala', 'Ibarra', 'Esmeraldas', 'Latacunga', 'Babahoyo', 'Quevedo', 'Milagro', 'Tulcán', 'Salinas', 'Santa Elena', 'Azogues', 'Otavalo', 'Puyo']],
        'PY' => [['Club', 'Sportivo', 'Deportivo', 'Atlético', '12 de Octubre de', 'General'], ['Asunción', 'Encarnación', 'Luque', 'Itauguá', 'Villarrica', 'Concepción', 'Pedro Juan Caballero', 'Ciudad del Este', 'Caaguazú', 'Coronel Oviedo', 'Pilar', 'San Lorenzo', 'Capiatá', 'Lambaré', 'Fernando de la Mora', 'Limpio', 'Itá', 'Caacupé', 'Paraguarí', 'Ybycuí']],
        'VE' => [['Deportivo', 'Atlético', 'Carabobo de', 'Estudiantes de', 'Monagas de', 'Club'], ['Maracaibo', 'Valencia', 'Barquisimeto', 'Maracay', 'Mérida', 'Barcelona', 'Puerto La Cruz', 'Cumaná', 'Maturín', 'San Cristóbal', 'Ciudad Guayana', 'Barinas', 'Coro', 'Los Teques', 'Guanare', 'Acarigua', 'El Tigre', 'Valera', 'Punto Fijo', 'Carúpano']],
        'BO' => [['Club', 'Deportivo', 'Real', 'Universitario de', 'Aurora de', 'Always Ready de'], ['La Paz', 'Santa Cruz', 'Cochabamba', 'Sucre', 'Oruro', 'Potosí', 'Tarija', 'Trinidad', 'Cobija', 'El Alto', 'Montero', 'Yacuiba', 'Riberalta', 'Quillacollo', 'Sacaba', 'Villazón', 'Camiri', 'Tupiza', 'Uyuni', 'Llallagua']],
        'HN' => [['Club Deportivo', 'Real', 'Olimpia de', 'Motagua de', 'Marathón de', 'Platense de'], ['Tegucigalpa', 'San Pedro Sula', 'La Ceiba', 'Choloma', 'El Progreso', 'Comayagua', 'Puerto Cortés', 'Danlí', 'Siguatepeque', 'Juticalpa', 'Tela', 'Choluteca', 'Santa Rosa', 'Tocoa', 'Olanchito', 'Villanueva', 'La Lima', 'Catacamas', 'Trujillo', 'Roatán']],
        'GT' => [['Club Social', 'Deportivo', 'Comunicaciones de', 'Municipal', 'Xelajú de', 'Club'], ['Guatemala', 'Quetzaltenango', 'Escuintla', 'Antigua', 'Cobán', 'Mazatenango', 'Huehuetenango', 'Chiquimula', 'Zacapa', 'Jutiapa', 'Retalhuleu', 'Chimaltenango', 'Puerto Barrios', 'Petén', 'Malacatán', 'Sololá', 'Totonicapán', 'Salamá', 'Jalapa', 'Coatepeque']],
        'US' => [['', '', '', '', '', ''], ['Denver', 'Austin', 'Portland', 'Nashville', 'Orlando', 'Columbus', 'Charlotte', 'Phoenix', 'Sacramento', 'Tampa', 'Pittsburgh', 'San Diego', 'Detroit', 'Memphis', 'Louisville', 'Tulsa', 'Albuquerque', 'Omaha', 'Raleigh', 'Boise']],
        'CA' => [['', '', '', '', '', ''], ['Calgary', 'Edmonton', 'Ottawa', 'Winnipeg', 'Halifax', 'Hamilton', 'Victoria', 'Regina', 'Saskatoon', 'Kingston', 'London', 'Quebec', 'Moncton', 'Kelowna', 'Barrie', 'Windsor', 'Guelph', 'Sudbury', 'Kitchener', 'Burnaby']],
        'BE' => [['KV', 'Royal', 'Sporting', 'KSC', 'RFC', 'Cercle'], ['Brujas', 'Gante', 'Amberes', 'Lieja', 'Charleroi', 'Lovaina', 'Malinas', 'Genk', 'Ostende', 'Namur', 'Mons', 'Kortrijk', 'Hasselt', 'Aalst', 'Waregem', 'Eupen', 'Lommel', 'Beveren', 'Tubize', 'Lokeren']],
        'CH' => [['FC', 'BSC', 'Grasshopper', 'FC Stade', 'Servette', 'Neuchâtel'], ['Basilea', 'Berna', 'Lucerna', 'Lugano', 'Sion', 'St. Gallen', 'Winterthur', 'Thun', 'Aarau', 'Schaffhausen', 'Vaduz', 'Bellinzona', 'Lausana', 'Wil', 'Chiasso', 'Biel', 'Kriens', 'Baden', 'Zug', 'Chur']],
        'HR' => [['NK', 'HNK', 'GNK', 'NK Hajduk', 'Dinamo', 'Slaven'], ['Split', 'Rijeka', 'Osijek', 'Zadar', 'Pula', 'Šibenik', 'Varaždin', 'Gorica', 'Karlovac', 'Dubrovnik', 'Slavonski Brod', 'Vinkovci', 'Koprivnica', 'Vukovar', 'Čakovec', 'Sisak', 'Bjelovar', 'Požega', 'Zaprešić', 'Solin']],
        'DK' => [['FC', 'AGF', 'Brøndby', 'IF', 'BK', 'Lyngby'], ['Aarhus', 'Odense', 'Aalborg', 'Esbjerg', 'Randers', 'Vejle', 'Horsens', 'Silkeborg', 'Viborg', 'Herning', 'Kolding', 'Hillerød', 'Næstved', 'Roskilde', 'Helsingør', 'Fredericia', 'Hobro', 'Skive', 'Holstebro', 'Svendborg']],
        'JP' => [['FC', 'Vissel', 'Sanfrecce', 'Gamba', 'Kashima', 'Urawa'], ['Osaka', 'Nagoya', 'Sapporo', 'Fukuoka', 'Kobe', 'Hiroshima', 'Sendai', 'Niigata', 'Shizuoka', 'Kawasaki', 'Yokohama', 'Kioto', 'Kumamoto', 'Okayama', 'Kanazawa', 'Nagasaki', 'Oita', 'Mito', 'Kofu', 'Tokushima']],
        'KR' => [['FC', 'Ulsan', 'Pohang', 'Jeonbuk', 'Suwon', 'Daegu'], ['Seúl', 'Busan', 'Incheon', 'Gwangju', 'Daejeon', 'Ulsan', 'Jeonju', 'Changwon', 'Cheonan', 'Gimpo', 'Seongnam', 'Gangwon', 'Jeju', 'Anyang', 'Bucheon', 'Asan', 'Gimcheon', 'Ansan', 'Chungbuk', 'Gyeongnam']],
        'MA' => [['Raja', 'Wydad', 'FUS', 'Olympique', 'Hassania', 'Renaissance'], ['Casablanca', 'Rabat', 'Fez', 'Marrakech', 'Tánger', 'Agadir', 'Mequinez', 'Oujda', 'Kenitra', 'Tetuán', 'Safí', 'El Jadida', 'Nador', 'Berkane', 'Khouribga', 'Béni Mellal', 'Settat', 'Taza', 'Larache', 'Errachidia']],
        'NG' => [['Enyimba', 'Rangers', 'Kano Pillars', 'Shooting Stars', 'Rivers United', 'Remo Stars'], ['Lagos', 'Abuja', 'Kano', 'Ibadan', 'Port Harcourt', 'Benin City', 'Enugu', 'Kaduna', 'Aba', 'Jos', 'Ilorin', 'Owerri', 'Calabar', 'Uyo', 'Akure', 'Abeokuta', 'Warri', 'Makurdi', 'Sokoto', 'Maiduguri']],
        'CR' => [['Club Sport', 'Deportivo', 'Asociación Deportiva', 'Municipal', 'Club', 'Unión'], ['Alajuela', 'Heredia', 'Cartago', 'Puntarenas', 'Limón', 'Liberia', 'San Carlos', 'Pérez Zeledón', 'Grecia', 'Guápiles', 'Santa Ana', 'Turrialba', 'Nicoya', 'Escazú', 'Desamparados', 'Palmares', 'Santos', 'Jicaral', 'Sarapiquí', 'Golfito']],
    ];
    private const EN_SUFFIX = ['United', 'City', 'Town', 'Rovers', 'Athletic', 'Wanderers'];

    private static array $usedNames = [];

    public static function resetCache(): void
    {
        self::$usedNames = [];
    }

    public static function randomName(int $countryId = 1): string
    {
        if (!self::$usedNames) {
            self::$usedNames = array_flip(array_column(DB::all('SELECT name FROM teams'), 'name'));
        }
        $code = (string)DB::value('SELECT code FROM countries WHERE id = ?', [$countryId]);
        for ($i = 0; $i < 300; $i++) {
            if (in_array($code, ['GB', 'US', 'CA'], true)) {
                $towns = self::LOCAL[$code][1];
                $name = $towns[array_rand($towns)] . ' ' . self::EN_SUFFIX[array_rand(self::EN_SUFFIX)];
            } elseif (isset(self::LOCAL[$code])) {
                [$pre, $towns] = self::LOCAL[$code];
                $name = $pre[array_rand($pre)] . ' ' . $towns[array_rand($towns)];
            } else {
                $towns = array_merge(self::TOWN, self::PANAMA_TOWNS);
                $name = self::PREFIX[array_rand(self::PREFIX)] . ' ' . $towns[array_rand($towns)];
            }
            if (!isset(self::$usedNames[$name])) {
                self::$usedNames[$name] = true;
                return $name;
            }
        }
        $name = 'FC ' . self::TOWN[array_rand(self::TOWN)] . ' ' . mt_rand(100, 999);
        self::$usedNames[$name] = true;
        return $name;
    }

    public static function shortName(string $name): string
    {
        $words = preg_split('/\s+/', trim($name));
        $last = end($words);
        return mb_substr($last, 0, 12);
    }

    /** Nivel medio de habilidad de los equipos de una división */
    public static function levelStrength(int $level): float
    {
        $divs = Config::int('game', 'divisions', 3);
        return 5.3 + ($divs - $level) * 1.3;
    }

    public static function createBotTeam(int $unitId, int $level, int $countryId = 1): int
    {
        $name = self::randomName($countryId);
        $divs = Config::int('game', 'divisions', 3);
        $teamId = DB::insert('teams', [
            'user_id'        => null,
            'league_unit_id' => $unitId,
            'country_id'     => $countryId,
            'name'           => $name,
            'short_name'     => self::shortName($name),
            'is_bot'         => 1,
            'cash'           => Config::int('game', 'starting_cash', 300000) + ($divs - $level) * 150000,
            'fans'           => (int)round(900 * (1 + ($divs - $level) * 0.6) * rnd(0.9, 1.1)),
            'trainer_level'  => mt_rand(2, 4),
            'training_type'  => array_keys(TRAINING_TYPES)[mt_rand(0, 5)],
            'stadium_name'   => 'Estadio de ' . self::shortName($name),
            'seats_terraces' => 8000 + ($divs - $level) * 3000,
            'seats_basic'    => 3000 + ($divs - $level) * 1500,
            'seats_roof'     => 800 + ($divs - $level) * 600,
            'seats_vip'      => 200 + ($divs - $level) * 80,
            'founded_at'     => now(),
        ]);
        PlayerFactory::squad($teamId, self::levelStrength($level), $countryId);
        StaffService::reconcile($teamId);
        return $teamId;
    }

    /** Equipo controlado por la máquina en la división más baja posible (del país indicado) */
    public static function findFreeTeam(?int $countryId = null): ?int
    {
        $id = DB::value(
            'SELECT t.id FROM teams t JOIN league_units lu ON lu.id = t.league_unit_id
             WHERE t.user_id IS NULL AND t.is_national = 0 AND (? IS NULL OR t.country_id = ?)
             ORDER BY lu.level DESC, RAND() LIMIT 1',
            [$countryId, $countryId]
        );
        return $id ? (int)$id : null;
    }

    public static function assignToUser(int $teamId, int $userId, string $teamName): void
    {
        $level = (int)DB::value('SELECT lu.level FROM teams t JOIN league_units lu ON lu.id = t.league_unit_id WHERE t.id = ?', [$teamId]);
        // Los jugadores del bot se retiran del club y se genera una plantilla nueva equilibrada
        DB::exec("UPDATE transfer_listings SET status = 'cancelled', closed_at = ? WHERE seller_team_id = ? AND status = 'active'", [now(), $teamId]);
        DB::exec('UPDATE players SET team_id = NULL, retired = 1 WHERE team_id = ?', [$teamId]);
        DB::exec('DELETE FROM team_lineups WHERE team_id = ?', [$teamId]);
        // Es un club nuevo: no hereda los títulos ni las noticias del equipo de la máquina
        DB::exec('DELETE FROM season_history WHERE team_id = ?', [$teamId]);
        DB::exec('DELETE FROM team_events WHERE team_id = ?', [$teamId]);
        DB::update('teams', [
            'user_id'        => $userId,
            'is_bot'         => 0,
            'name'           => $teamName,
            'short_name'     => self::shortName($teamName),
            'stadium_name'   => 'Estadio ' . $teamName,
            'cash'           => Config::int('game', 'starting_cash', 300000),
            'fans'           => 1000,
            'fan_mood'       => 5,
            'team_spirit'    => 5,
            'confidence'     => 5,
            'trainer_level'  => 3,
            'assistants'     => 1,
            'physios'        => 1,
            'doctors'        => 1,
            'spokespersons'  => 0,
            'psychologists'  => 0,
            'training_type'  => 'playmaking',
            'training_intensity' => 100,
            'stamina_share'  => 15,
            'youth_level'    => 1,
            'youth_points'   => 0,
            'negative_weeks' => 0,
            'founded_at'     => now(),
        ], 'id = ?', [$teamId]);
        PlayerFactory::squad($teamId, self::levelStrength($level) - 0.3);
        Lineup::save($teamId, Lineup::auto($teamId));
        StaffService::resetTeam($teamId);
        SponsorService::resetForNewOwner($teamId);
        DB::insert('team_events', ['team_id' => $teamId, 'created_at' => now(), 'type' => 'welcome',
            'message' => "🎉 ¡Bienvenido a $teamName! Revisa tu plantilla, prepara la alineación y elige el entrenamiento."]);
        DB::insert('messages', [
            'from_user_id' => null, 'to_user_id' => $userId, 'subject' => '¡Bienvenido a ' . Config::get('app', 'site_name', 'GoalTrick') . '!',
            'body' => "Hola,\n\nAcabas de hacerte cargo de $teamName. Algunos consejos para empezar:\n\n"
                . "1. Revisa tu plantilla en «Jugadores».\n2. Prepara la alineación antes de cada partido de liga.\n"
                . "3. Elige un tipo de entrenamiento: sólo entrenan los jugadores que juegan en las posiciones adecuadas.\n"
                . "4. Controla las finanzas: los salarios se pagan cada semana.\n5. Usa el mercado de fichajes para mejorar el equipo.\n\n¡Mucha suerte!",
            'created_at' => now(),
        ]);
    }

    /** Libera el equipo de un usuario (pasa a ser controlado por la máquina) */
    public static function release(int $teamId): void
    {
        DB::exec('UPDATE teams SET user_id = NULL, is_bot = 1 WHERE id = ?', [$teamId]);
    }

    /** Sube un juvenil de la cantera (una vez por semana) */
    public static function pullYouth(array $team): int
    {
        $pts = (float)$team['youth_points'];
        $level = 3.5 + $pts * 0.11;
        $roles = ['GK', 'CD', 'WB', 'IM', 'W', 'FW'];
        $pid = PlayerFactory::create([
            'role' => $roles[array_rand($roles)], 'level' => $level, 'team_id' => (int)$team['id'],
            'age_min' => 17, 'age_max' => 17, 'country_id' => (int)$team['country_id'], 'exact' => true,
        ]);
        DB::exec('UPDATE teams SET youth_points = 0, youth_pulled_week = ? WHERE id = ?', [self::weekIndex(), $team['id']]);
        DB::exec('UPDATE players SET loyalty = 20 WHERE id = ?', [$pid]);
        return $pid;
    }

    public static function weekIndex(): int
    {
        return GameState::season() * 100 + GameState::week();
    }

    public static function squadCount(int $teamId): int
    {
        return (int)DB::value('SELECT COUNT(*) FROM players WHERE team_id = ? AND retired = 0', [$teamId]);
    }
}
