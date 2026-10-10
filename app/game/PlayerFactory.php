<?php
declare(strict_types=1);

/**
 * Generación de jugadores y cálculo de TSI, salario y valor de mercado.
 */
final class PlayerFactory
{
    /** Nombres por idioma. Cada país usa el grupo de su idioma (ver LANG_BY_CODE). */
    private const NAMES = [
        'es' => [
            'first' => ['Alejandro', 'Andrés', 'Ángel', 'Antonio', 'Bruno', 'Carlos', 'César', 'Cristian', 'Daniel', 'David',
                'Diego', 'Eduardo', 'Emiliano', 'Enrique', 'Esteban', 'Fabián', 'Facundo', 'Federico', 'Felipe', 'Fernando',
                'Francisco', 'Gabriel', 'Gonzalo', 'Guillermo', 'Gustavo', 'Héctor', 'Hugo', 'Ignacio', 'Iván', 'Jaime',
                'Javier', 'Jesús', 'Joaquín', 'Jorge', 'José', 'Juan', 'Julián', 'Leandro', 'Leonardo', 'Lucas',
                'Luis', 'Manuel', 'Marcelo', 'Marcos', 'Mario', 'Martín', 'Mateo', 'Matías', 'Mauricio', 'Miguel',
                'Nicolás', 'Óscar', 'Pablo', 'Pedro', 'Rafael', 'Ramiro', 'Raúl', 'Ricardo', 'Roberto', 'Rodrigo',
                'Rubén', 'Samuel', 'Santiago', 'Sebastián', 'Sergio', 'Simón', 'Tomás', 'Valentín', 'Víctor', 'Thiago',
                'Jordi', 'Iker', 'Álvaro', 'Gael', 'Aníbal', 'Rolando', 'Erick', 'Yoel', 'Abdiel', 'Ismael'],
            'last' => ['García', 'Rodríguez', 'González', 'Fernández', 'López', 'Martínez', 'Sánchez', 'Pérez', 'Gómez', 'Martín',
                'Jiménez', 'Ruiz', 'Hernández', 'Díaz', 'Moreno', 'Álvarez', 'Muñoz', 'Romero', 'Alonso', 'Gutiérrez',
                'Navarro', 'Torres', 'Domínguez', 'Vázquez', 'Ramos', 'Gil', 'Ramírez', 'Serrano', 'Blanco', 'Suárez',
                'Molina', 'Morales', 'Ortega', 'Delgado', 'Castro', 'Ortiz', 'Rubio', 'Marín', 'Sanz', 'Núñez',
                'Iglesias', 'Medina', 'Garrido', 'Cortés', 'Castillo', 'Santos', 'Lozano', 'Guerrero', 'Cano', 'Prieto',
                'Méndez', 'Cruz', 'Calvo', 'Gallego', 'Vidal', 'León', 'Herrera', 'Márquez', 'Peña', 'Flores',
                'Cabrera', 'Campos', 'Vega', 'Fuentes', 'Carrasco', 'Diez', 'Caballero', 'Reyes', 'Aguilar', 'Pascual',
                'Silva', 'Rojas', 'Mendoza', 'Acosta', 'Benítez', 'Aguirre', 'Quintero', 'Valencia', 'Cardona', 'Zapata',
                'Arango', 'Ospina', 'Barría', 'Chiari', 'Quintana', 'Murillo', 'Guardia', 'Batista', 'Araúz', 'Solís'],
        ],
        'pt' => [
            'first' => ['João', 'Pedro', 'Lucas', 'Gabriel', 'Rafael', 'Thiago', 'Bruno', 'Diogo', 'Tiago', 'Rodrigo',
                'Gonçalo', 'André', 'Ricardo', 'Vinícius', 'Matheus', 'Felipe', 'Caio', 'Leandro', 'Renato', 'Henrique',
                'Rúben', 'Nuno', 'Fábio', 'Marcelo', 'Paulo', 'Fernando', 'Eduardo', 'Luís', 'Miguel', 'Danilo'],
            'last' => ['Silva', 'Santos', 'Oliveira', 'Souza', 'Pereira', 'Costa', 'Rodrigues', 'Almeida', 'Nascimento', 'Lima',
                'Araújo', 'Ferreira', 'Carvalho', 'Gomes', 'Martins', 'Rocha', 'Ribeiro', 'Alves', 'Monteiro', 'Mendes',
                'Barbosa', 'Cardoso', 'Teixeira', 'Moreira', 'Correia', 'Fonseca', 'Pinto', 'Sousa', 'Neves', 'Coelho'],
        ],
        'it' => [
            'first' => ['Marco', 'Luca', 'Andrea', 'Matteo', 'Alessandro', 'Lorenzo', 'Francesco', 'Davide', 'Simone', 'Federico',
                'Giorgio', 'Riccardo', 'Stefano', 'Paolo', 'Fabio', 'Nicolò', 'Gianluca', 'Daniele', 'Antonio', 'Giovanni'],
            'last' => ['Rossi', 'Russo', 'Ferrari', 'Esposito', 'Bianchi', 'Romano', 'Colombo', 'Ricci', 'Marino', 'Greco',
                'Bruno', 'Gallo', 'Conti', 'De Luca', 'Mancini', 'Costa', 'Giordano', 'Rizzo', 'Lombardi', 'Moretti'],
        ],
        'fr' => [
            'first' => ['Pierre', 'Antoine', 'Hugo', 'Lucas', 'Théo', 'Maxime', 'Julien', 'Nicolas', 'Olivier', 'Kylian',
                'Mathis', 'Louis', 'Baptiste', 'Clément', 'Adrien', 'Florian', 'Romain', 'Thomas', 'Yanis', 'Ousmane'],
            'last' => ['Martin', 'Bernard', 'Dubois', 'Thomas', 'Robert', 'Richard', 'Petit', 'Durand', 'Leroy', 'Moreau',
                'Simon', 'Laurent', 'Lefebvre', 'Michel', 'Garnier', 'Faure', 'Rousseau', 'Blanc', 'Mercier', 'Girard'],
        ],
        'de' => [
            'first' => ['Lukas', 'Jonas', 'Leon', 'Maximilian', 'Felix', 'Paul', 'Niklas', 'Tim', 'Jan', 'Thomas',
                'Florian', 'Julian', 'Moritz', 'Kai', 'Manuel', 'Sebastian', 'Tobias', 'Matthias', 'Stefan', 'Joshua'],
            'last' => ['Müller', 'Schmidt', 'Schneider', 'Fischer', 'Weber', 'Meyer', 'Wagner', 'Becker', 'Schulz', 'Hoffmann',
                'Koch', 'Richter', 'Klein', 'Wolf', 'Schröder', 'Neumann', 'Schwarz', 'Zimmermann', 'Braun', 'Krüger'],
        ],
        'en' => [
            'first' => ['Harry', 'Jack', 'James', 'Oliver', 'George', 'Charlie', 'Thomas', 'Mason', 'Declan', 'Jordan',
                'Kyle', 'Marcus', 'Bukayo', 'Phil', 'Jude', 'Raheem', 'Callum', 'Luke', 'Daniel', 'Ben'],
            'last' => ['Smith', 'Jones', 'Taylor', 'Brown', 'Williams', 'Wilson', 'Johnson', 'Davies', 'Walker', 'Wright',
                'Robinson', 'Thompson', 'White', 'Hughes', 'Edwards', 'Green', 'Hall', 'Wood', 'Clarke', 'Harris'],
        ],
        'hr' => [
            'first' => ['Luka', 'Ivan', 'Marko', 'Josip', 'Mateo', 'Ante', 'Domagoj', 'Mario', 'Dario', 'Nikola',
                'Andrej', 'Bruno', 'Filip', 'Tomislav', 'Lovro', 'Petar', 'Duje', 'Borna', 'Kristijan', 'Mislav'],
            'last' => ['Horvat', 'Kovačević', 'Babić', 'Marić', 'Jurić', 'Novak', 'Kovačić', 'Knežević', 'Vuković', 'Perić',
                'Matić', 'Petrović', 'Pavlović', 'Brozović', 'Perišić', 'Vlašić', 'Gvardiol', 'Sučić', 'Pašalić', 'Livaja'],
        ],
        'da' => [
            'first' => ['Mads', 'Rasmus', 'Kasper', 'Christian', 'Mikkel', 'Andreas', 'Jonas', 'Simon', 'Frederik', 'Thomas',
                'Emil', 'Mathias', 'Joachim', 'Pierre', 'Jesper', 'Nicolai', 'Anders', 'Lasse', 'Oliver', 'Magnus'],
            'last' => ['Nielsen', 'Jensen', 'Hansen', 'Pedersen', 'Andersen', 'Christensen', 'Larsen', 'Sørensen', 'Rasmussen', 'Jørgensen',
                'Madsen', 'Kristensen', 'Olsen', 'Thomsen', 'Poulsen', 'Johansen', 'Knudsen', 'Møller', 'Mortensen', 'Dahl'],
        ],
        'ja' => [
            'first' => ['Takumi', 'Kaoru', 'Daichi', 'Ritsu', 'Takefusa', 'Wataru', 'Ko', 'Shuichi', 'Hidemasa', 'Junya',
                'Yuto', 'Kyogo', 'Ayase', 'Daizen', 'Hiroki', 'Shogo', 'Kento', 'Yuki', 'Ren', 'Sora'],
            'last' => ['Sato', 'Suzuki', 'Takahashi', 'Tanaka', 'Watanabe', 'Ito', 'Yamamoto', 'Nakamura', 'Kobayashi', 'Kato',
                'Yoshida', 'Yamada', 'Sasaki', 'Yamaguchi', 'Matsumoto', 'Inoue', 'Kimura', 'Hayashi', 'Shimizu', 'Endo'],
        ],
        'ko' => [
            'first' => ['Min-jae', 'Heung-min', 'Kang-in', 'Hee-chan', 'Jae-sung', 'Woo-young', 'Gue-sung', 'Seung-ho', 'Young-gwon', 'Ji-sung',
                'Hyun-woo', 'Jun-ho', 'Dong-hyun', 'Sang-ho', 'Tae-hwan', 'Chang-hoon', 'Jin-su', 'In-beom', 'Seol', 'Hwan'],
            'last' => ['Kim', 'Lee', 'Park', 'Choi', 'Jung', 'Kang', 'Cho', 'Yoon', 'Jang', 'Lim',
                'Han', 'Oh', 'Seo', 'Shin', 'Kwon', 'Hwang', 'Ahn', 'Song', 'Hong', 'Son'],
        ],
        'ma' => [
            'first' => ['Achraf', 'Hakim', 'Youssef', 'Sofyan', 'Azzedine', 'Nayef', 'Bilal', 'Abdelhamid', 'Romain', 'Yassine',
                'Hamza', 'Amine', 'Zakaria', 'Ilias', 'Brahim', 'Selim', 'Anass', 'Oussama', 'Walid', 'Karim'],
            'last' => ['Hakimi', 'Ziyech', 'En-Nesyri', 'Amrabat', 'Ounahi', 'Aguerd', 'El Khannouss', 'Sabiri', 'Bounou', 'Saiss',
                'Benali', 'El Amrani', 'Alaoui', 'Bennani', 'Tazi', 'Idrissi', 'Chakir', 'Bouzid', 'Mansouri', 'Rahimi'],
        ],
        'ng' => [
            'first' => ['Victor', 'Ademola', 'Samuel', 'Wilfred', 'Alex', 'Kelechi', 'Moses', 'Chidozie', 'Calvin', 'Frank',
                'Joe', 'Semi', 'Taiwo', 'Emmanuel', 'Chukwuemeka', 'Olamide', 'Tunde', 'Ifeanyi', 'Obinna', 'Uche'],
            'last' => ['Osimhen', 'Lookman', 'Chukwueze', 'Ndidi', 'Iwobi', 'Iheanacho', 'Simon', 'Awaziem', 'Bassey', 'Onyeka',
                'Aribo', 'Ajayi', 'Okafor', 'Adeyemi', 'Eze', 'Nwosu', 'Okonkwo', 'Balogun', 'Obi', 'Okoye'],
        ],
        'nl' => [
            'first' => ['Daan', 'Sem', 'Lars', 'Bram', 'Ruud', 'Frenkie', 'Virgil', 'Matthijs', 'Stefan', 'Wout',
                'Joost', 'Thijs', 'Jasper', 'Niels', 'Koen', 'Sven', 'Tim', 'Ryan', 'Denzel', 'Cody'],
            'last' => ['de Jong', 'Jansen', 'de Vries', 'van den Berg', 'van Dijk', 'Bakker', 'Visser', 'Smit', 'Meijer', 'de Boer',
                'Mulder', 'de Groot', 'Bos', 'Vos', 'Peters', 'Hendriks', 'van Leeuwen', 'Dekker', 'Brouwer', 'de Wit'],
        ],
    ];

    private const LANG_BY_CODE = ['BR' => 'pt', 'PT' => 'pt', 'IT' => 'it', 'FR' => 'fr', 'DE' => 'de', 'GB' => 'en', 'NL' => 'nl',
        'US' => 'en', 'CA' => 'en', 'BE' => 'fr', 'CH' => 'de', 'HR' => 'hr', 'DK' => 'da', 'JP' => 'ja', 'KR' => 'ko', 'MA' => 'ma', 'NG' => 'ng'];

    private static array $countryCodes = [];

    /** Nombre y apellido típicos del país */
    public static function randomName(int $countryId): array
    {
        if (!self::$countryCodes) {
            self::$countryCodes = array_column(DB::all('SELECT id, code FROM countries'), 'code', 'id');
        }
        $lang = self::LANG_BY_CODE[self::$countryCodes[$countryId] ?? ''] ?? 'es';
        $pool = self::NAMES[$lang];
        return [$pool['first'][array_rand($pool['first'])], $pool['last'][array_rand($pool['last'])]];
    }

    /** Nacionalidad de un jugador nuevo: 80% del país del club, 20% de cualquier otro */
    public static function randomNationality(int $homeCountry): int
    {
        if (!self::$countryCodes) {
            self::$countryCodes = array_column(DB::all('SELECT id, code FROM countries'), 'code', 'id');
        }
        if (mt_rand(1, 100) <= 80 || count(self::$countryCodes) < 2) {
            return $homeCountry;
        }
        return (int)array_rand(self::$countryCodes);
    }

    public const OUTFIELD_SKILLS = ['defending', 'playmaking', 'winger', 'passing', 'scoring'];

    /**
     * Crea un jugador y devuelve su id.
     * $opts: role (GK,CD,WB,IM,W,FW), level (habilidad principal aprox), age_min, age_max, team_id, country_id
     */
    public static function create(array $opts): int
    {
        $p = self::build($opts);
        return DB::insert('players', $p);
    }

    public static function build(array $opts): array
    {
        $role = $opts['role'] ?? ['GK', 'CD', 'WB', 'IM', 'W', 'FW'][mt_rand(0, 5)];
        $level = (float)($opts['level'] ?? 5.0);
        $age = mt_rand($opts['age_min'] ?? 17, $opts['age_max'] ?? 31);

        // Los jugadores jóvenes de la generación inicial son algo peores; los veteranos algo mejores
        if (empty($opts['exact'])) {
            $level += ($age - 24) * 0.08;
        }
        $main = clamp(gauss($level, 0.8), 1.0, 19.0);

        $lo = fn(float $f, float $sd = 0.7) => clamp(gauss($main * $f, $sd), 1.0, 19.0);
        $base = fn() => clamp(gauss(2.0, 0.8), 1.0, 5.0);

        $s = [
            'keeper' => 0.0, 'defending' => $base(), 'playmaking' => $base(), 'winger' => $base(),
            'passing' => $base(), 'scoring' => $base(), 'set_pieces' => clamp(gauss($main * 0.45, 1.2), 1.0, 16.0),
        ];
        switch ($role) {
            case 'GK':
                $s['keeper'] = $main;
                $s['defending'] = clamp(gauss(1.5, 0.6), 0.0, 4.0);
                $s['playmaking'] = $s['winger'] = $s['scoring'] = clamp(gauss(1.0, 0.4), 0.0, 3.0);
                $s['passing'] = clamp(gauss(1.5, 0.6), 0.0, 3.0);
                break;
            case 'CD':
                $s['defending'] = $main;
                $s['playmaking'] = max($s['playmaking'], $lo(0.45));
                $s['passing'] = max($s['passing'], $lo(0.35));
                break;
            case 'WB':
                $s['defending'] = $main;
                $s['winger'] = max($s['winger'], $lo(0.7));
                $s['playmaking'] = max($s['playmaking'], $lo(0.4));
                $s['passing'] = max($s['passing'], $lo(0.35));
                break;
            case 'IM':
                $s['playmaking'] = $main;
                $s['passing'] = max($s['passing'], $lo(0.6));
                $s['defending'] = max($s['defending'], $lo(0.55));
                $s['scoring'] = max($s['scoring'], $lo(0.3));
                break;
            case 'W':
                $s['winger'] = $main;
                $s['playmaking'] = max($s['playmaking'], $lo(0.65));
                $s['passing'] = max($s['passing'], $lo(0.5));
                $s['defending'] = max($s['defending'], $lo(0.35));
                break;
            case 'FW':
                $s['scoring'] = $main;
                $s['passing'] = max($s['passing'], $lo(0.6));
                $s['winger'] = max($s['winger'], $lo(0.35));
                $s['playmaking'] = max($s['playmaking'], $lo(0.35));
                break;
        }
        if ($role !== 'GK') {
            $s['keeper'] = clamp(gauss(1.0, 0.4), 0.0, 2.5);
        }
        // La habilidad principal siempre es la mejor del jugador
        $mainKey = ['GK' => 'keeper', 'CD' => 'defending', 'WB' => 'defending', 'IM' => 'playmaking', 'W' => 'winger', 'FW' => 'scoring'][$role];
        $others = array_diff_key($s, [$mainKey => 1, 'set_pieces' => 1]);
        $s[$mainKey] = max($s[$mainKey], max($others) + rnd(0.3, 1.0));
        foreach ($s as $k => $v) {
            $s[$k] = round($v, 2);
        }

        $specialties = array_keys(SPECIALTIES);
        $specialty = mt_rand(1, 100) <= 45 ? $specialties[array_rand($specialties)] : null;
        if ($role === 'GK' && in_array($specialty, ['head', 'quick'], true)) {
            $specialty = null;
        }

        $countryId = (int)($opts['country_id'] ?? self::randomNationality((int)($opts['home_country'] ?? 1)));
        [$firstName, $lastName] = self::randomName($countryId);

        $p = array_merge($s, [
            'team_id'        => $opts['team_id'] ?? null,
            'mother_team_id' => $opts['mother_team_id'] ?? ($opts['team_id'] ?? null),
            'country_id'     => $countryId,
            'first_name'     => $firstName,
            'last_name'      => $lastName,
            'avatar'         => mt_rand(1, AVATAR_COUNT),
            'age_years'      => $age,
            'age_days'       => mt_rand(0, 111),
            'stamina'        => round(clamp(gauss(5.5 + ($age - 22) * 0.05, 1.2), 2.0, 9.0), 2),
            'form'           => round(clamp(gauss(5.5, 1.0), 2.0, 8.0), 2),
            'experience'     => round(clamp(($age - 17) * 0.35 + gauss($level * 0.2, 0.8), 0.0, 15.0), 2),
            'leadership'     => mt_rand(1, 7),
            'loyalty'        => round(rnd(1, 8), 2),
            'specialty'      => $specialty,
            'role'           => $role,
            'joined_at'      => now(),
            'created_at'     => now(),
        ]);
        $p['tsi'] = self::tsi($p);
        $p['wage'] = self::wage($p);
        return $p;
    }

    /** Habilidad principal (la más alta excluyendo resistencia y balón parado) */
    public static function mainSkill(array $p): float
    {
        return max((float)$p['keeper'], (float)$p['defending'], (float)$p['playmaking'],
            (float)$p['winger'], (float)$p['passing'], (float)$p['scoring']);
    }

    /** Índice de habilidad total (TSI) */
    public static function tsi(array $p): int
    {
        $sum = 0.0;
        if ((float)$p['keeper'] >= self::mainSkill($p) - 0.01 && (float)$p['keeper'] > 3) {
            $sum = pow((float)$p['keeper'], 3) * 1.5 + pow((float)$p['set_pieces'], 2) * 2;
        } else {
            $vals = [];
            foreach (self::OUTFIELD_SKILLS as $k) {
                $vals[] = (float)$p[$k];
            }
            rsort($vals);
            $sum = pow($vals[0], 3) * 1.0 + pow($vals[1], 3) * 0.55 + pow($vals[2], 3) * 0.35
                 + pow($vals[3], 3) * 0.1 + pow((float)$p['set_pieces'], 2) * 2;
        }
        $form = 0.6 + (float)$p['form'] * 0.065;
        $stamina = 0.85 + (float)$p['stamina'] * 0.025;
        $ageF = $p['age_years'] >= 28 ? max(0.35, 1 - 0.07 * ($p['age_years'] - 27)) : 1.0;
        $injury = (float)($p['injury_weeks'] ?? 0) > 0 ? 0.8 : 1.0;
        return (int)max(10, round($sum * 6.2 * $form * $stamina * $ageF * $injury));
    }

    /** Salario semanal */
    public static function wage(array $p): int
    {
        $vals = [(float)$p['keeper'] * 1.15];
        foreach (self::OUTFIELD_SKILLS as $k) {
            $vals[] = (float)$p[$k];
        }
        rsort($vals);
        $w = 250 + 1.6 * pow($vals[0], 3.2) + 0.45 * pow($vals[1], 3.0) + 0.15 * pow($vals[2], 3.0);
        return (int)round($w / 10) * 10;
    }

    /** Valor estimado de mercado (usado por los bots y como referencia) */
    public static function value(array $p): int
    {
        $age = (int)$p['age_years'];
        $ageFactor = match (true) {
            $age <= 18 => 75,
            $age <= 20 => 65,
            $age <= 22 => 55,
            $age <= 24 => 45,
            $age <= 26 => 36,
            $age <= 28 => 28,
            $age <= 30 => 20,
            $age <= 32 => 12,
            default => 6,
        };
        $tsi = self::tsi(array_merge($p, ['form' => 5.5, 'injury_weeks' => 0]));
        $spec = $p['specialty'] ? 1.08 : 1.0;
        return (int)max(1000, round($tsi * $ageFactor * $spec / 100) * 100);
    }

    /** Recalcula TSI y salario de uno o varios jugadores */
    public static function refresh(array $ids = []): void
    {
        $sql = 'SELECT * FROM players WHERE retired = 0';
        $params = [];
        if ($ids) {
            $sql .= ' AND id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
            $params = $ids;
        }
        DB::transaction(function () use ($sql, $params) {
            $st = DB::pdo()->prepare('UPDATE players SET tsi = ?, wage = ? WHERE id = ?');
            foreach (DB::all($sql, $params) as $p) {
                $tsi = self::tsi($p);
                $wage = self::wage($p);
                if ($tsi !== (int)$p['tsi'] || $wage !== (int)$p['wage']) {
                    $st->execute([$tsi, $wage, $p['id']]);
                }
            }
        });
    }

    /** Plantilla inicial equilibrada de 18 jugadores */
    public static function squad(int $teamId, float $level, ?int $countryId = null): void
    {
        $countryId = $countryId ?? (int)DB::value('SELECT country_id FROM teams WHERE id = ?', [$teamId]);
        $roles = ['GK', 'GK', 'CD', 'CD', 'CD', 'WB', 'WB', 'WB', 'IM', 'IM', 'IM', 'IM', 'W', 'W', 'W', 'FW', 'FW', 'FW'];
        foreach ($roles as $role) {
            self::create(['role' => $role, 'level' => $level, 'team_id' => $teamId, 'age_min' => 17, 'age_max' => 32, 'home_country' => $countryId]);
        }
    }
}
