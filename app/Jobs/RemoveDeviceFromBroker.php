<?php

namespace App\Jobs;

use App\Services\EmqxService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class RemoveDeviceFromBroker implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 5;

    /**
     * Seconds to wait between attempts, so a short EMQX outage does not use them all up.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120, 600, 1800];
    }

    /**
     * Takes the username rather than the device, which no longer exists once this runs.
     */
    public function __construct(public string $username)
    {
        // Run only once the surrounding delete has committed, so a rolled-back delete never removes a live device's broker user
        $this->afterCommit();
    }

    /**
     * @throws RuntimeException When EMQX could not remove the user or its rules, so the job is retried
     */
    public function handle(EmqxService $emqx): void
    {
        $userRemoved = $emqx->deleteUser($this->username);
        $rulesRemoved = $emqx->deleteUserRules($this->username);

        if (! ($userRemoved && $rulesRemoved)) {
            throw new RuntimeException("EMQX cleanup incomplete for {$this->username}");
        }
    }

    /**
     * Leave a trace of the orphaned broker user once every attempt has failed.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('EMQX cleanup gave up, broker user and rules left behind', [
            'username' => $this->username,
            'error' => $exception->getMessage(),
        ]);
    }
}
