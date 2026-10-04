<?php
declare(strict_types=1);
namespace Resurrection\Security;

/** Explicit display identity, never an account ID or trusted system sender. */
final readonly class GameMasterMailSender
{
    private function __construct(public int $actor, public string $label) {}

    /** Caller supplies the current persisted (and, at settlement, locked) account.
     * @param array<string,mixed> $account
     */
    public static function resolve(array $account, string $label): self
    {
        $actor = MailContent::id($account['acctid'] ?? null);
        $label = trim(MailContent::text($label));
        $plain = trim(preg_replace('/`./u', '', $label));
        if (((int)($account['superuser'] ?? 0) & SU_IS_GAMEMASTER) === 0 || (int)($account['locked'] ?? 1) !== 0 ||
            $plain === '' || preg_match('/\A[+\-.0-9]/', $plain) || strcasecmp($plain, 'System') === 0 ||
            mb_strlen($label, 'UTF-8') > 255 || preg_match('/[\x00-\x1f\x7f<>]/', $label) || str_contains($label, '`n')) {
            throw new \DomainException('Invalid Game Master display identity.');
        }
        return new self($actor, $label);
    }
}
