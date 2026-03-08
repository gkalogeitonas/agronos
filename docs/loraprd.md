Zero-Trust LoRa Decryption & Dedicated Webhook Architecture

System Segment: Backend Platform (Agronos Laravel)
Context: PHP / Laravel 12 / EMQX Broker / InfluxDB
1. Objective

To securely ingest, authenticate, and decrypt binary LoRa payloads forwarded by a gateway. By implementing a dedicated webhook and a decoupled cryptographic service, the architecture adheres to the Single Responsibility Principle (SRP) while providing military-grade End-to-End Encryption (E2EE) and Replay Attack mitigation tailored for agricultural IoT.
2. Architectural Requirements

2.1. Dedicated Webhook Routing (EMQX to Laravel)

    Logic: Isolate the untrusted, encrypted LoRa traffic from the trusted, authenticated Wi-Fi JSON traffic.

    Implementation: * Create a new HTTP POST endpoint (e.g., /api/v1/lora/webhook) managed by a new LoRaDataController.

        Configure a new EMQX Rule Engine policy to forward topics originating from the LoRa Gateway specifically to this new endpoint, keeping it strictly separate from the existing DeviceDataController::mqttBrokerWebhook().

2.2. Database Schema Expansion

    Logic: The backend must persistently track the cryptographic state of each LoRa-enabled device to prevent replay attacks.

    Implementation: Add a new column to the devices table: lora_frame_counter (unsigned big integer, default 0). The existing secret column will be utilized as the 16-byte AES-128 Shared Secret Key.

2.3. Anti-Replay & MAX_FCNT_GAP Validation

    Logic: Prevent malicious actors from intercepting and re-transmitting old commands or environmental data.

    Implementation: Before any cryptographic operations occur, the controller must compare the incoming Frame Counter against the device's lora_frame_counter in the database.

        Replay Block: If Incoming Counter <= Database Counter, reject the packet immediately.

        Gap Allowance: Check if the difference exceeds a predefined MAX_FCNT_GAP (e.g., 10,000). If it does, reject it to prevent nonce-synchronization attacks. This specifically accommodates the firmware's "lazy NVS saving" strategy which causes intentional counter jumps after a power loss.

        State Update: If valid, the database counter must be updated to the new incoming value.

2.4. Cryptographic Adapter (LoRaCryptoService)

    Logic: Decrypt the payload and deserialize the binary data back into a high-level application format without bloating the controllers.

    Implementation: * Deterministic Nonce: Reconstruct the 16-byte nonce in memory using the agreed-upon rule: [4-byte Device ID] + [4-byte Incoming Frame Counter] + [8-byte Zero Padding].

        Decryption: Utilize PHP's OpenSSL extension (aes-128-ctr) with OPENSSL_NO_PADDING to decrypt the Base64-encoded ciphertext.

        Deserialization: Unpack the raw bytes into a PHP associative array, reversing the mathematical scaling applied at the edge (e.g., dividing scaled integers back into floats).

2.5. Data Pipeline Normalization

    Logic: Ensure the decrypted LoRa data flows seamlessly into the existing platform architecture.
