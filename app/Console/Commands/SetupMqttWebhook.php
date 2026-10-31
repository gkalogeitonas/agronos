<?php

namespace App\Console\Commands;

use App\Services\EmqxService;
use App\Services\MqttTopics;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;

#[Signature('mqtt:setup-webhook
    {--base-url= : Base URL of the app as reachable from EMQX (defaults to services.emqx.webhook_base_url)}
    {--force : Run in production without confirmation}')]
#[Description('Create or update the EMQX rules that forward device and LoRa gateway messages to the Laravel webhooks')]
class SetupMqttWebhook extends Command
{
    use ConfirmableTrait;

    /**
     * Each flow is an HTTP connector and action (sharing the webhook name) plus a rule that feeds the action.
     *
     * @return list<array{webhook: string, rule: string, topic: string, path: string}>
     */
    private function flows(): array
    {
        return [
            [
                'webhook' => 'agronos_device_webhook',
                'rule' => 'agronos_device_sensors',
                'topic' => MqttTopics::deviceSensorsFilter(),
                'path' => 'api/v1/device/mqtt-webhook',
            ],
            [
                'webhook' => 'agronos_lora_webhook',
                'rule' => 'agronos_lora_gateway',
                'topic' => MqttTopics::loraDataFilter(),
                'path' => 'api/v1/lora/webhook',
            ],
        ];
    }

    /**
     * Execute the console command.
     */
    public function handle(EmqxService $emqx): int
    {
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        $secret = config('services.emqx.webhook_secret');

        if (! filled($secret)) {
            $this->error('Set EMQX_WEBHOOK_SECRET first: the webhooks reject every request without it.');

            return self::FAILURE;
        }

        $baseUrl = rtrim($this->option('base-url') ?: config('services.emqx.webhook_base_url'), '/');

        try {
            foreach ($this->flows() as $flow) {
                $url = "{$baseUrl}/{$flow['path']}";
                $sql = "SELECT username, payload, topic FROM \"{$flow['topic']}\"";

                $this->line("Connector {$flow['webhook']}: ".$emqx->upsertWebhookConnector($flow['webhook'], $url, ['x-emqx-secret' => $secret]));
                $this->line("Action {$flow['webhook']}: ".$emqx->upsertWebhookAction($flow['webhook'], $flow['webhook']));
                $this->line("Rule {$flow['rule']}: ".$emqx->upsertRule($flow['rule'], $sql, ["http:{$flow['webhook']}"]));
                $this->info("EMQX now forwards {$flow['topic']} to {$url}.");
            }
        } catch (RequestException $exception) {
            $this->error("EMQX rejected the request with HTTP {$exception->response->status()}: {$exception->response->body()}");

            return self::FAILURE;
        } catch (ConnectionException $exception) {
            $this->error("Could not reach the EMQX management API: {$exception->getMessage()}. Check EMQX_API_URL.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
