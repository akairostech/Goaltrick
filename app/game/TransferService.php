<?php
declare(strict_types=1);

/**
 * Mercado de fichajes con subastas.
 */
final class TransferService
{
    public static function activeListing(int $playerId): ?array
    {
        return DB::row("SELECT * FROM transfer_listings WHERE player_id = ? AND status = 'active'", [$playerId]);
    }

    /** Dinero comprometido por un equipo en pujas donde va ganando */
    public static function committed(int $teamId, ?int $exceptListing = null): int
    {
        return (int)DB::value(
            "SELECT COALESCE(SUM(highest_bid),0) FROM transfer_listings WHERE status = 'active' AND highest_bidder_id = ? AND id <> ?",
            [$teamId, $exceptListing ?? 0]
        );
    }

    public static function list(array $team, int $playerId, int $price): ?string
    {
        $p = DB::row('SELECT * FROM players WHERE id = ? AND team_id = ? AND retired = 0', [$playerId, $team['id']]);
        if (!$p) {
            return t('Ese jugador no pertenece a tu equipo.');
        }
        if (self::activeListing($playerId)) {
            return t('El jugador ya está en venta.');
        }
        if ($price < 1000 || $price > 500000000) {
            return t('El precio mínimo debe estar entre {min} y {max}.', ['min' => money(1000), 'max' => money(500000000)]);
        }
        $min = Config::int('game', 'min_players', 15);
        $listed = (int)DB::value("SELECT COUNT(*) FROM transfer_listings WHERE seller_team_id = ? AND status = 'active'", [$team['id']]);
        if (TeamService::squadCount((int)$team['id']) - $listed <= $min) {
            return t('Debes conservar al menos {n} jugadores en la plantilla.', ['n' => $min]);
        }
        $deadline = date('Y-m-d H:i:s', time() + Config::int('game', 'transfer_hours', 72) * 3600);
        DB::insert('transfer_listings', [
            'player_id' => $playerId, 'seller_team_id' => $team['id'], 'asking_price' => $price,
            'deadline' => $deadline, 'created_at' => now(),
        ]);
        DB::insert('player_events', ['player_id' => $playerId, 'created_at' => now(), 'message' => tx('Puesto a la venta por {price}.', ['price' => money($price)])]);
        return null;
    }

    public static function cancel(array $team, int $listingId): ?string
    {
        $l = DB::row("SELECT * FROM transfer_listings WHERE id = ? AND seller_team_id = ? AND status = 'active'", [$listingId, $team['id']]);
        if (!$l) {
            return t('No se encontró la venta.');
        }
        if ($l['highest_bid']) {
            return t('No puedes retirar un jugador que ya tiene pujas.');
        }
        DB::exec("UPDATE transfer_listings SET status = 'cancelled', closed_at = ? WHERE id = ?", [now(), $listingId]);
        return null;
    }

    public static function minNextBid(array $l): int
    {
        if (!$l['highest_bid']) {
            return (int)$l['asking_price'];
        }
        $inc = Config::float('game', 'min_bid_increment', 5) / 100;
        return (int)max((int)$l['highest_bid'] + 1000, ceil((int)$l['highest_bid'] * (1 + $inc) / 1000) * 1000);
    }

    public static function bid(array $team, int $listingId, int $amount): ?string
    {
        return DB::transaction(function () use ($team, $listingId, $amount) {
            $l = DB::row('SELECT * FROM transfer_listings WHERE id = ? FOR UPDATE', [$listingId]);
            if (!$l || $l['status'] !== 'active' || strtotime($l['deadline']) <= time()) {
                return t('La subasta ya no está activa.');
            }
            if ((int)$l['seller_team_id'] === (int)$team['id']) {
                return t('No puedes pujar por tus propios jugadores.');
            }
            if ((int)$l['highest_bidder_id'] === (int)$team['id']) {
                return t('Ya eres el mejor postor.');
            }
            $min = self::minNextBid($l);
            if ($amount < $min) {
                return t('La puja mínima es {amount}.', ['amount' => money($min)]);
            }
            $cash = (int)DB::value('SELECT cash FROM teams WHERE id = ?', [$team['id']]);
            if ($cash - self::committed((int)$team['id'], $listingId) < $amount) {
                return t('No tienes suficiente dinero disponible (teniendo en cuenta tus otras pujas ganadoras).');
            }
            $max = Config::int('game', 'max_players', 40);
            if (TeamService::squadCount((int)$team['id']) >= $max) {
                return t('Tu plantilla ya tiene el máximo de {n} jugadores.', ['n' => $max]);
            }
            $prevBidder = $l['highest_bidder_id'] ? (int)$l['highest_bidder_id'] : null;
            $deadline = $l['deadline'];
            $ext = Config::int('game', 'bid_extension_minutes', 3) * 60;
            if (strtotime($deadline) - time() < $ext) {
                $deadline = date('Y-m-d H:i:s', time() + $ext);
            }
            DB::insert('transfer_bids', ['listing_id' => $listingId, 'team_id' => $team['id'], 'amount' => $amount, 'created_at' => now()]);
            DB::update('transfer_listings', ['highest_bid' => $amount, 'highest_bidder_id' => $team['id'], 'deadline' => $deadline], 'id = ?', [$listingId]);

            $p = DB::row('SELECT first_name, last_name FROM players WHERE id = ?', [$l['player_id']]);
            if ($prevBidder) {
                $pt = DB::row('SELECT is_bot FROM teams WHERE id = ?', [$prevBidder]);
                if ($pt && !(int)$pt['is_bot']) {
                    DB::insert('team_events', ['team_id' => $prevBidder, 'created_at' => now(), 'type' => 'transfer',
                        'message' => tx('💰 Han superado tu puja por {player} ({amount}).', ['player' => player_name($p), 'amount' => money($amount)])]);
                }
            }
            $seller = DB::row('SELECT is_bot FROM teams WHERE id = ?', [$l['seller_team_id']]);
            if ($seller && !(int)$seller['is_bot']) {
                DB::insert('team_events', ['team_id' => $l['seller_team_id'], 'created_at' => now(), 'type' => 'transfer',
                    'message' => tx('💰 Nueva puja de {amount} por {player} ({team}).', ['amount' => money($amount), 'player' => player_name($p), 'team' => $team['name']])]);
            }
            return null;
        });
    }

    /** Cierra las subastas vencidas */
    public static function processDue(): int
    {
        $n = 0;
        $due = DB::all("SELECT id FROM transfer_listings WHERE status = 'active' AND deadline <= ?", [now()]);
        foreach ($due as $row) {
            DB::transaction(function () use ($row, &$n) {
                $l = DB::row('SELECT * FROM transfer_listings WHERE id = ? FOR UPDATE', [$row['id']]);
                if (!$l || $l['status'] !== 'active') {
                    return;
                }
                $p = DB::row('SELECT * FROM players WHERE id = ?', [$l['player_id']]);
                $sold = false;
                if ($l['highest_bid'] && $p && (int)$p['team_id'] === (int)$l['seller_team_id']) {
                    $buyer = DB::row('SELECT * FROM teams WHERE id = ?', [$l['highest_bidder_id']]);
                    $price = (int)$l['highest_bid'];
                    if ($buyer && (int)$buyer['cash'] >= $price && TeamService::squadCount((int)$buyer['id']) < Config::int('game', 'max_players', 40)) {
                        self::executeTransfer($p, (int)$l['seller_team_id'], $buyer, $price);
                        DB::exec("UPDATE transfer_listings SET status = 'sold', closed_at = ? WHERE id = ?", [now(), $l['id']]);
                        $sold = true;
                        $n++;
                    }
                }
                if (!$sold) {
                    DB::exec("UPDATE transfer_listings SET status = 'unsold', closed_at = ? WHERE id = ?", [now(), $l['id']]);
                    $seller = DB::row('SELECT is_bot FROM teams WHERE id = ?', [$l['seller_team_id']]);
                    if ($p && $seller && !(int)$seller['is_bot']) {
                        DB::insert('team_events', ['team_id' => $l['seller_team_id'], 'created_at' => now(), 'type' => 'transfer',
                            'message' => tx('{player} no se ha vendido. Sigue en tu plantilla.', ['player' => player_name($p)])]);
                    }
                }
            });
        }
        return $n;
    }

    private static function executeTransfer(array $p, int $sellerId, array $buyer, int $price): void
    {
        $seller = DB::row('SELECT * FROM teams WHERE id = ?', [$sellerId]);
        $name = player_name($p);
        Economy::transaction((int)$buyer['id'], -$price, 'transfers_out', tx('Fichaje de {player}', ['player' => $name]));
        Economy::transaction($sellerId, $price, 'transfers_in', tx('Venta de {player}', ['player' => $name]));
        DB::update('players', ['team_id' => $buyer['id'], 'joined_at' => now(), 'loyalty' => 1], 'id = ?', [$p['id']]);
        DB::insert('player_events', ['player_id' => $p['id'], 'created_at' => now(),
            'message' => tx('Traspasado de {from} a {to} por {price}.', ['from' => $seller['name'], 'to' => $buyer['name'], 'price' => money($price)])]);
        if (!(int)$buyer['is_bot']) {
            DB::insert('team_events', ['team_id' => $buyer['id'], 'created_at' => now(), 'type' => 'transfer',
                'message' => tx('✅ ¡Has fichado a {player} por {price}!', ['player' => $name, 'price' => money($price)])]);
        }
        if (!(int)$seller['is_bot']) {
            DB::insert('team_events', ['team_id' => $sellerId, 'created_at' => now(), 'type' => 'transfer',
                'message' => tx('✅ Has vendido a {player} a {team} por {price}.', ['player' => $name, 'team' => $buyer['name'], 'price' => money($price)])]);
        }
    }

    /** Despedir un jugador (queda como agente libre retirado) */
    public static function fire(array $team, int $playerId): ?string
    {
        $p = DB::row('SELECT * FROM players WHERE id = ? AND team_id = ?', [$playerId, $team['id']]);
        if (!$p) {
            return t('Ese jugador no pertenece a tu equipo.');
        }
        if (self::activeListing($playerId)) {
            return t('No puedes despedir a un jugador que está en venta.');
        }
        if (TeamService::squadCount((int)$team['id']) <= Config::int('game', 'min_players', 15)) {
            return t('Tienes muy pocos jugadores para despedir a nadie.');
        }
        // Indemnización: 2 semanas de salario
        Economy::transaction((int)$team['id'], -(int)$p['wage'] * 2, 'wages', tx('Indemnización por despido de {player}', ['player' => player_name($p)]));
        DB::exec('UPDATE players SET team_id = NULL, retired = 1 WHERE id = ?', [$playerId]);
        DB::insert('team_events', ['team_id' => $team['id'], 'created_at' => now(), 'type' => 'transfer',
            'message' => tx('{player} ha sido despedido.', ['player' => player_name($p)])]);
        return null;
    }
}
