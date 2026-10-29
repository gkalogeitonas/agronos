# Agronos

A platform where growers deploy IoT devices on their farms and monitor the environmental conditions those devices report.

## Language

### People

**Tenant**:
The owner whose data is isolated from everyone else's. Today a tenant is a single user.
_Avoid_: Account, organisation (until one exists)

### Places

**Farm**:
A piece of land a tenant manages, optionally bounded by a drawn polygon.
_Avoid_: Field, plot, garden

**Farm Health**:
The status of a farm derived from how many of its devices are online: OK, Warning, Offline, or Unknown when it has no devices.
_Avoid_: Farm status

### Hardware

**Device**:
A physical unit deployed on a farm that carries one or more sensors and talks to the platform.
_Avoid_: Node, board, unit

**Device Secret**:
The credential shipped with a device (on its QR label) that proves possession of it at registration and login.
_Avoid_: Device password, key

**Device Type**:
How a device reaches the platform: Wi-Fi (directly over HTTP or MQTT) or LoRa (radio, through a gateway).
_Avoid_: Connectivity, transport

**Gateway**:
A relay that receives LoRa radio packets and forwards them to the platform. It is untrusted and never sees readable data.
_Avoid_: Hub, bridge

**Sensor**:
One measuring element on a device, of a single type (moisture, temperature, humidity, light, battery).
_Avoid_: Probe, channel

**Diagnostic Sensor**:
A sensor that reports on the device itself rather than the farm, such as battery. Shown on device screens, hidden from farm screens.
_Avoid_: Technical sensor, internal sensor

### Data

**Reading**:
A single value a sensor reported at a point in time.
_Avoid_: Measurement, data point, sample
