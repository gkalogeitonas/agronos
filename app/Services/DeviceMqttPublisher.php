<?php

namespace App\Services;

use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Exceptions\MqttClientException;
use PhpMqtt\Client\MqttClient;

/**
 * One MQTT connection to the broker as a device, used by the device simulator.
 */
class DeviceMqttPublisher
{
    private ?MqttClient $client = null;

    /**
     * Connect with the device's broker credentials. The keep-alive must exceed the publish interval,
     * because this publisher does not run the client loop that would send pings between publishes.
     *
     * @throws MqttClientException
     */
    public function connect(string $clientId, string $username, string $password, int $keepAliveSeconds = 60): void
    {
        $this->client = new MqttClient(
            config('services.mqtt.host'),
            (int) config('services.mqtt.port'),
            $clientId,
            MqttClient::MQTT_3_1_1,
        );

        $this->client->connect(
            (new ConnectionSettings)
                ->setUsername($username)
                ->setPassword($password)
                ->setConnectTimeout(5)
                ->setKeepAliveInterval($keepAliveSeconds),
            true,
        );
    }

    /**
     * Publish with QoS 0, like the device firmware (PubSubClient only publishes at QoS 0).
     *
     * @throws MqttClientException
     */
    public function publish(string $topic, string $payload): void
    {
        $this->client->publish($topic, $payload, MqttClient::QOS_AT_MOST_ONCE);
    }

    public function disconnect(): void
    {
        try {
            if ($this->client?->isConnected()) {
                $this->client->disconnect();
            }
        } catch (MqttClientException) {
            // The broker already dropped the connection; nothing left to close.
        } finally {
            $this->client = null;
        }
    }
}
