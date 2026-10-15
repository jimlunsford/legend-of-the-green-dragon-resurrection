# Bank authority, 2026-10-15

## Scope and predecessor

Repository: `jimlunsford/legend-of-the-green-dragon-resurrection`.
Starting branch and draft PR #1 head: `cbc0cf49875a59fd963b80cd7187bccc732141a0`.
Main remains `999cec6f9c655a840320d982d673bb863c68c2b2`. Predecessor Modern core
`38779187332` and Baseline integrity `38779187265`: SUCCESS at that head (clan text
and moderation accepted).

Scope: `bank.php`, which covers deposit, withdraw, borrow, gold transfer between
players and the transfer notice. Route-local helpers live in `lib/bank_authority.php`.
Before this change `bank.php` was byte-identical to 1.1.2. New Day bank interest is
already accepted and was not reopened.

## Findings verified on the previous code

These were probed over HTTP on PHP 8.5.11 / MySQL 8.4.11 against `cbc0cf4`:

| Finding | Observed |
|---|---|
| `GET bank.php?op=depositfinish` (no amount) | Deposited all gold on hand: 500 gold / 200 bank became 0 / 700. Any reload, or a restored page after login, did the same |
| Deposit amount `-50` | Deposited 50 (`abs()`) |
| Deposit amount `abc` | Deposited everything (`(int)"abc"` is 0, which means "all") |
| Withdraw amount `12abc` | Withdrew 12 |
| `transfer3` POST with no CSRF token | Gold moved and the recipient was credited |
| Transfer to a locked account | Credited (the search hides locked accounts; completion did not check) |
| Transfer by a player in debt (bank -10) | Completed (only the transfer page refuses debtors) |
| Transfer with `allowgoldtransfer=0` | Completed (only the navigation was hidden) |
| Recipient search containing an apostrophe | Fatal SQL error (`to` pasted into `ORDER BY`) |
| Recipient search with several matches | Names printed raw in `<option>`: `<img src=x>` reached the page |

Read from source and not exploitable as a probe:

- The recipient credit is an autocommit UPDATE issued before the sender's debit is saved
  at page end, so a failure between them left the credit without the debit.
- The search split names byte by byte, so a UTF-8 character's bytes were separated by `%`.
  A typed `%` or `_` acted as a wildcard.
- The minimum transfer level was enforced only by the navigation.

**`$leftoborrow` (line 224):** the branch that prints this undefined variable cannot be
reached. It requires `lefttoborrow - goldinbank > maxborrow` after the remaining balance
has been withdrawn. That is the outer `lefttoborrow <= goldinbank + maxborrow` check
negated, with both sides shifted by the same withdrawn balance. So it could never be
fatal. `BankAuthorityTest` compares a transcription of the 1.1.2 arithmetic with the new
helper over 20,776 combinations: it never reaches that branch and the two always agree.
The helper keeps the branch and its message with the variable fixed.

## Amount coercion decision

1.1.2 parsed every amount as `abs((int)httppost(...))`. No form or message describes
that coercion. The only documented input rule is "Enter 0 or nothing to deposit (or
withdraw) it all". The coercion acted as the sole guard against negative amounts, and it
turned typing mistakes into real moves:

- `abc` or `1,000` deposited everything, or one gold.
- `-50` was treated as 50.

**Decision: reject.** An amount is empty or a canonical non-negative integer (digits,
surrounding spaces allowed, no sign, no leading zeros, within the integer range).
Anything else is 400 and nothing changes. The documented rule is kept exactly: empty
or `0` deposits all gold on hand or withdraws the whole balance. No request can move
gold in the wrong direction. A negative value is refused rather than flipped.

## Behavior against 1.1.2

| Behavior | 1.1.2 | Now | Reason |
|---|---|---|---|
| Finishing steps | `depositfinish`, `withdrawfinish`, `transfer3` acted on any request; GET meant "all" | Only a protected POST acts. GET shows the deposit, withdraw or transfer form | GET mutation, reloads, restored pages |
| Protection | No CSRF, no replay check | POST + CSRF + a one-use intent bound to the operation (deposit, withdraw, borrow, transfer), the transfer amount, and the actor's level, dragon kills, gold, bank balance and both daily counters | CSRF, replay, stale forms |
| Atomicity | Session changes saved at page end; the transfer credit was a separate earlier statement | One locked player transaction. A transfer locks sender and recipient in ascending account order and commits debit, credit, both counters, the debug log and the notice together | Partial transfers |
| Notice | `systemmail()` immediately | Written in the transaction through the explicit `mail_notifications` queue; external email after commit | Accepted systemmail caller contract |
| Amounts | `abs((int)…)` | Empty or canonical non-negative integer, else 400 (decision above) | Typos and signs |
| Deposit | 0 = all on hand; more than on hand refused with the 1.1.2 text; deposits reduce debt | Same, under lock | Historical rules |
| Withdraw and borrow | 0 = whole balance; withdraw form refuses more than the balance; borrowing withdraws any balance first, then lends until total debt reaches level × `borrowperlevel` | Same arithmetic and messages (`resurrection_bank_withdrawal()`), under lock. The withdraw form's intent cannot borrow (409), and only the borrow form's intent can. On the borrow form while in debt, `0` asks for the debt's size, as in 1.1.2 | Exact formula kept |
| Debt floor | Kept by the arithmetic | Same; gold never below 0 | Unchanged |
| Transfer limits | All in `transfer3`, in this order: funds (hand + bank); unknown login; sender daily `amountouttoday + amt ≤ level × maxtransferout`; per transfer `amt ≤ recipient level × transferperlevel`; recipient `transferredtoday < transferreceive`; `amt ≥ sender level`; not to self | Same checks, values, order and messages, on the locked rows | Server-side, race-free |
| Locked recipient | Hidden by the search, accepted by completion | Refused at completion with the "accounting error" text used for an unknown login | The search was the only guard |
| Debtor | Refused only on the transfer page | Also refused at search and completion, with the same text | Same |
| `allowgoldtransfer`, minimum level (`mintransferlev` or a dragon kill) | Navigation only | 403 on every transfer step | Same |
| Transfer funding | Hand gold first, then the bank | Same | Historical rule |
| Recipient search | `LIKE` from bytes with `addslashes`; `to` pasted into `ORDER BY`; unlimited rows | Bound; per UTF-8 character; `%`, `_` and `\|` match literally (`ESCAPE '\|'`); same ordering; at most 101 rows read (enough to show the "narrow it down" text) | Injection, UTF-8, wildcards |
| Escaping | Option names raw; the "narrow it down" form echoed `to` raw | Escaped | HTML injection |
| Messages | — | Unchanged, including Elessa's refusals and the notice | Historical text |

## Tests

PHPUnit `BankAuthorityTest` (fast, no database):
- The withdrawal arithmetic equals the 1.1.2 transcription and never reaches `$leftoborrow`.
- Amounts accept empty and canonical integers and reject `-50`, `-0`, `1.5`, `abc`, `12abc`, `0x10`, `1e3`, `007`, `+5`, overflow and arrays.
- Search patterns split UTF-8 characters and escape `%`, `_` and `|`.

HTTP tests:

- `test_bank_deposit_withdraw_and_borrow_authority_http`:
  - Access and GET: anonymous access; GET of each finishing step changes nothing.
  - Input refusals: CSRF 403; negative, decimal, text, trailing-text, overflow, array and extra-field amounts are 400; an amount larger than gold shows the historical text.
  - Deposit, then replay and a stale form, each 409.
  - Deposit all (empty) and withdraw 50; withdrawing past the balance is refused; withdraw all (`0`).
  - Borrowing: borrow 130 with 30 in the bank (withdraw 30, borrow 100, debt 100 = level 5 × 20). Borrowing more past the limit is refused, and borrowing within it while in debt works.
  - A deposit pays the debt down.
  - A withdraw form cannot borrow.
- `test_bank_transfer_authority_http`:
  - Gates: anonymous; disabled transfers and below the minimum level are 403 on all three steps.
  - Search with apostrophe, backslash, UTF-8, `%` and `_`: only the true match, and the locked account is never offered. Option names are escaped.
  - Input refusals: bad amounts 400 at search and completion; CSRF 403; a changed amount 409.
  - Each refusal, nothing moved: unknown login, locked recipient, self, insufficient funds, sender daily cap, recipient level limit, recipient transfer count, below the sender's level.
  - The debtor refusal at the transfer page and at search.
  - A successful transfer (hand gold, then bank) with the recipient's credit, counter and one notice; replay 409.
  - Rollback: a CHECK-constraint failure on the sender's own final write rolls back the credit, counters and notice (500). The spent intent is 409; a fresh intent succeeds; the notice names the apostrophe/UTF-8 sender.

All three fail against `cbc0cf4`:
- The deposit test fails because GET deposited everything.
- The transfer test fails because the transfer page was served with transfers disabled.
- The unit test fails because the helper did not exist.

All three pass with the change. Local runs are supporting evidence only. Acceptance
comes from CI on both supported legs.

## Remaining bank gaps

- A credit that would push the recipient's `goldinbank` past the signed 32-bit column
  maximum fails at the database (500, nothing changes) rather than with a message.
  1.1.2 had no check either.
- The recipient limit message says "per day", but the historical rule (kept) is per
  transfer: `amt ≤ recipient level × transferperlevel`. Only the count of transfers
  received is per day.
- On the borrow form while in debt, entering `0` asks to borrow the size of the
  existing debt (1.1.2 arithmetic, kept and bounded by the borrow limit).
