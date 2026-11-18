<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Unit\Concurrency;

use Nowo\DashboardMenuBundle\Concurrency\DashboardMenuConflictAnswer;
use Nowo\TransactionContract\TransientStorageFailure;
use Nowo\TransactionDoctrine\ConflictCode;
use Nowo\TransactionDoctrine\Symfony\Conflict\ConflictResponsePolicy;
use Nowo\TransactionDoctrine\WriteUnit\CommitLedger;
use Nowo\TransactionDoctrine\WriteUnit\CommitMark;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * REQ-RUNTIME-003 rule 6 (2.23): the dashboard's lock-conflict answer.
 */
final class DashboardMenuConflictAnswerTest extends TestCase
{
    /**
     * @return iterable<string, array{Throwable}>
     */
    public static function lockConflicts(): iterable
    {
        yield 'deadlock' => [StorageErrors::deadlock()];
        yield 'lock wait timeout' => [StorageErrors::lockWaitTimeout()];
        yield 'postgresql lock_timeout' => [StorageErrors::postgresLockNotAvailable()];
        yield 'serialization failure' => [StorageErrors::serializationFailure()];
        yield 'runner failure' => [TransientStorageFailure::fromPrevious(StorageErrors::deadlock(), 1)];
        yield 'wrapped' => [new RuntimeException('wrapped', 0, StorageErrors::deadlock())];
    }

    #[DataProvider('lockConflicts')]
    public function testLockConflictWithNothingCommittedAnswersConflict001WithRetryAfter(Throwable $failure): void
    {
        $answer = (new DashboardMenuConflictAnswer())->httpExceptionFor(new Request(), $failure);

        self::assertInstanceOf(HttpException::class, $answer);
        self::assertSame(409, $answer->getStatusCode());
        self::assertSame(['X-Conflict-Code' => ConflictCode::RETRY_SAFE, 'Retry-After' => '1'], $answer->getHeaders());
        self::assertSame(DashboardMenuConflictAnswer::RETRY_SAFE_MESSAGE, $answer->getMessage());
        self::assertSame($failure, $answer->getPrevious());
    }

    public function testACommitEarlierInTheRequestAnswersConflict002WithoutRetryAfter(): void
    {
        $ledger  = new CommitLedger();
        $request = new Request();
        $request->attributes->set(CommitMark::REQUEST_ATTRIBUTE, new CommitMark($ledger));
        $ledger->record();

        $answer = (new DashboardMenuConflictAnswer())->httpExceptionFor($request, StorageErrors::deadlock());

        self::assertInstanceOf(HttpException::class, $answer);
        self::assertSame(409, $answer->getStatusCode());
        self::assertSame(['X-Conflict-Code' => ConflictCode::COMMITTED], $answer->getHeaders());
        self::assertSame(DashboardMenuConflictAnswer::COMMITTED_MESSAGE, $answer->getMessage());
    }

    public function testAMarkWithNothingCommittedSinceStaysConflict001(): void
    {
        $request = new Request();
        $request->attributes->set(CommitMark::REQUEST_ATTRIBUTE, new CommitMark(new CommitLedger()));

        self::assertSame(ConflictCode::RetrySafe, (new DashboardMenuConflictAnswer())->codeFor($request, StorageErrors::deadlock()));
    }

    public function testALostSavepointHasAnUnknownOutcomeAndAnswersConflict002(): void
    {
        self::assertSame(ConflictCode::Committed, (new DashboardMenuConflictAnswer())->codeFor(new Request(), StorageErrors::lostSavepoint()));
    }

    /**
     * @return iterable<string, array{Throwable}>
     */
    public static function notConflicts(): iterable
    {
        yield 'unique violation' => [StorageErrors::uniqueViolation()];
        yield 'lost connection' => [StorageErrors::connectionLost()];
        yield 'domain error' => [new RuntimeException('domain')];
    }

    #[DataProvider('notConflicts')]
    public function testUniqueViolationsLostConnectionsAndOtherErrorsAreNotMapped(Throwable $failure): void
    {
        $answer = new DashboardMenuConflictAnswer();

        self::assertNull($answer->codeFor(new Request(), $failure));
        self::assertNull($answer->httpExceptionFor(new Request(), $failure));

        $caught = null;
        try {
            $answer->guard(new Request(), static function () use ($failure): never {
                throw $failure;
            });
        } catch (Throwable $thrown) {
            $caught = $thrown;
        }
        self::assertSame($failure, $caught, 'guard() rethrows it unchanged');
    }

    public function testGuardReturnsTheResultAndTurnsAConflictIntoTheAnswer(): void
    {
        $answer = new DashboardMenuConflictAnswer();
        $result = new stdClass();
        self::assertSame($result, $answer->guard(new Request(), static fn (): stdClass => $result));

        $this->expectException(HttpException::class);
        $answer->guard(new Request(), static function (): never {
            throw StorageErrors::lockWaitTimeout();
        });
    }

    public function testTheHostCanAnswer503AndItsOwnRetryAfter(): void
    {
        $answer = (new DashboardMenuConflictAnswer(new ConflictResponsePolicy(503, 9)))->httpExceptionFor(new Request(), StorageErrors::deadlock());

        self::assertInstanceOf(HttpException::class, $answer);
        self::assertSame(503, $answer->getStatusCode());
        self::assertSame('9', $answer->getHeaders()['Retry-After']);
    }

    public function testThePositiveHintOfTheRunnerFailureWinsAndZeroFallsBackToThePolicy(): void
    {
        $answer = new DashboardMenuConflictAnswer(new ConflictResponsePolicy(409, 3));

        $hinted = $answer->httpExceptionFor(new Request(), TransientStorageFailure::fromPrevious(StorageErrors::deadlock(), 5));
        self::assertInstanceOf(HttpException::class, $hinted);
        self::assertSame('5', $hinted->getHeaders()['Retry-After']);

        $zero = $answer->httpExceptionFor(new Request(), TransientStorageFailure::fromPrevious(StorageErrors::deadlock(), 0));
        self::assertInstanceOf(HttpException::class, $zero);
        self::assertSame('3', $zero->getHeaders()['Retry-After']);

        $none = $answer->httpExceptionFor(new Request(), new TransientStorageFailure());
        self::assertInstanceOf(HttpException::class, $none);
        self::assertSame('3', $none->getHeaders()['Retry-After']);
    }
}
