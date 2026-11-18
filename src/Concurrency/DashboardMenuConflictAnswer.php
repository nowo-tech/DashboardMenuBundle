<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Concurrency;

use Nowo\TransactionContract\TransientStorageFailure;
use Nowo\TransactionDoctrine\ConflictCode;
use Nowo\TransactionDoctrine\Symfony\Conflict\ConflictResponsePolicy;
use Nowo\TransactionDoctrine\TransientFailureClassifier;
use Nowo\TransactionDoctrine\WriteUnit\CommitMark;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * The answer of a lock conflict on the bundle's own dashboard routes and Live component (2.23, REQ-RUNTIME-003 rule 6).
 *
 * A deadlock, lock wait timeout or serialization failure (a {@see TransientStorageFailure} of
 * {@see DashboardMenuTransaction}, or the storage error itself anywhere down the chain), or a lost savepoint (commit
 * outcome unknown), becomes an {@see HttpException} with the status of the {@see ConflictResponsePolicy} (**409** by
 * default, 503 when the host configures it), rendered by the application's error page:
 *
 * - `CONFLICT_001` with `Retry-After` when nothing of the request was committed;
 * - `CONFLICT_002` without `Retry-After` when part of it may already be committed: anything committed since the
 *   request's {@see CommitMark} (transaction-doctrine's request watermark: the host, another bundle or an earlier unit
 *   of the same request), or a commit outcome the failure leaves unknown ({@see ConflictCode::forRequest()}).
 *
 * The code always travels in the `X-Conflict-Code` header (only `CONFLICT_001` / `CONFLICT_002`). The message is fixed
 * and client-safe; the storage error stays the previous exception, for the logs. Unique-constraint violations and
 * lost connections are NOT conflicts: they are rethrown unchanged (the dashboard turns the expected "same code and
 * context" race into its form error before this point). Host routes are never answered here: only the bundle's
 * controller and Live component call it.
 *
 * Stateless (FrankenPHP worker mode, kernel reset off).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class DashboardMenuConflictAnswer
{
    /** Message of a CONFLICT_001 answer (nothing committed, retry is safe). */
    public const RETRY_SAFE_MESSAGE = 'Another change to this menu is in progress. Retry in a moment.';

    /** Message of a CONFLICT_002 answer (part of the request may be committed). */
    public const COMMITTED_MESSAGE = 'Part of the change may already be saved. Reload the page before trying again.';

    private ConflictResponsePolicy $policy;

    private TransientFailureClassifier $classifier;

    /**
     * @param ConflictResponsePolicy|null $policy status and default Retry-After (null: 409, 1 second)
     */
    public function __construct(?ConflictResponsePolicy $policy = null)
    {
        $this->policy     = $policy ?? new ConflictResponsePolicy();
        $this->classifier = new TransientFailureClassifier();
    }

    /**
     * Runs $operation; a lock conflict leaves as the conflict answer, anything else unchanged.
     *
     * @template T
     *
     * @param callable(): T $operation
     *
     * @throws HttpException on a lock conflict
     *
     * @return T
     */
    public function guard(Request $request, callable $operation): mixed
    {
        try {
            return $operation();
        } catch (Throwable $failure) {
            throw $this->httpExceptionFor($request, $failure) ?? $failure;
        }
    }

    /**
     * The conflict answer of $failure, or null when it is not a lock conflict.
     */
    public function httpExceptionFor(Request $request, Throwable $failure): ?HttpException
    {
        $code = $this->codeFor($request, $failure);
        if (!$code instanceof ConflictCode) {
            return null;
        }

        return $this->policy->httpExceptionFor(
            $code,
            $code->retrySafe() ? self::RETRY_SAFE_MESSAGE : self::COMMITTED_MESSAGE,
            $failure,
            $this->hint($failure),
        );
    }

    /**
     * The conflict code of $failure, or null when it is not a lock conflict (unique violations, lost connections and
     * every other error).
     */
    public function codeFor(Request $request, Throwable $failure): ?ConflictCode
    {
        $conflict = $this->classifier->isTransient($failure) || $this->classifier->isCommitOutcomeUnknown($failure);
        for ($current = $failure; !$conflict && $current instanceof Throwable; $current = $current->getPrevious()) {
            $conflict = $current instanceof TransientStorageFailure;
        }

        return $conflict ? ConflictCode::forRequest($request, $failure) : null;
    }

    /** The first positive Retry-After hint of the chain; null for none (the policy's value then applies). */
    private function hint(Throwable $failure): ?int
    {
        for ($current = $failure; $current instanceof Throwable; $current = $current->getPrevious()) {
            if ($current instanceof TransientStorageFailure) {
                $hint = $current->retryAfterSeconds();

                return $hint !== null && $hint > 0 ? $hint : null;
            }
        }

        return null;
    }
}
