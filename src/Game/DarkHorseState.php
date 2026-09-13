<?php
declare(strict_types=1);
namespace Resurrection\Game;

/** Small persisted wager envelope. All writers run under the account transaction. */
final class DarkHorseState
{
    /** @return array<string,mixed> */
    public static function read(string $json, int $owner): array
    {
        if ($json === '' || $json === '[]') return [];
        if (strlen($json) > 2048) throw new \DomainException('Invalid wager state.');
        try { $state = json_decode($json, true, 6, JSON_THROW_ON_ERROR); }
        catch (\JsonException) { throw new \DomainException('Invalid wager state.'); }
        if (!is_array($state)) throw new \DomainException('Invalid wager state.');
        self::validate($state, $owner);
        return $state;
    }

    /** @param array<string,mixed> $state */
    public static function write(array $state, int $owner): string
    {
        self::validate($state, $owner);
        return json_encode($state, JSON_THROW_ON_ERROR);
    }

    /** @param array<string,mixed> $state */
    public static function validate(array $state, int $owner): void
    {
        $keys = ['version','owner','game','wager','stage','active','result','settled','data'];
        if (count($state) !== count($keys) || array_diff(array_keys($state), $keys) !== [] ||
            $state['version'] !== 1 || $owner < 1 || $state['owner'] !== $owner ||
            !in_array($state['game'], ['game_stones','game_dice','game_fivesix'], true) ||
            !is_int($state['wager']) || $state['wager'] < 0 || $state['wager'] > 1073741823 ||
            !in_array($state['stage'], ['choose','play','complete','abandoned'], true) ||
            !is_bool($state['active']) || !is_bool($state['settled']) || !is_string($state['data']) ||
            strlen($state['data']) > 512 || !in_array($state['result'], ['pending','win','loss','tie','abandoned'], true)) {
            throw new \DomainException('Invalid wager state.');
        }
        $live = in_array($state['stage'], ['choose','play'], true);
        if ($state['active'] !== $live || $state['settled'] === $live ||
            ($live !== ($state['result'] === 'pending')) ||
            (($state['stage'] === 'abandoned') !== ($state['result'] === 'abandoned')) ||
            ($state['stage'] === 'choose' && ($state['game'] !== 'game_stones' || $state['wager'] !== 0)) ||
            ($state['stage'] === 'play' && $state['wager'] < 1)) {
            throw new \DomainException('Inconsistent wager state.');
        }
        if ($state['game'] === 'game_stones') {
            $stones = StonesState::decode($state['data']);
            if ($live && ($stones === [] || ($stones['bet'] ?? 0) !== $state['wager'])) {
                throw new \DomainException('Inconsistent Stones wager.');
            }
        }
        if ($state['game'] === 'game_dice') DiceGame::decode($state['data']);
        if ($state['game'] === 'game_fivesix') FiveSixGame::decode($state['data']);
    }

    /** @param array<string,mixed> $prior
     * @return array<string,mixed>
     */
    public static function start(array $prior, int $owner, string $game, int $wager, string $data): array
    {
        if ($prior !== []) {
            self::validate($prior, $owner);
            if ($prior['active']) throw new \DomainException('Finish or abandon the active game.');
        }
        $state = ['version'=>1,'owner'=>$owner,'game'=>$game,'wager'=>$wager,
            'stage'=>$wager === 0 ? 'choose' : 'play','active'=>true,'result'=>'pending','settled'=>false,'data'=>$data];
        self::validate($state, $owner);
        return $state;
    }

    /** @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    public static function abandon(array $state, int $owner, string $game): array
    {
        self::validate($state, $owner);
        if (!$state['active'] || $state['game'] !== $game) throw new \DomainException('No matching active wager.');
        $state['stage']='abandoned'; $state['active']=false; $state['settled']=true; $state['result']='abandoned';
        return $state;
    }
}
