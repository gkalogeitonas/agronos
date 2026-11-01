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
A physical unit that carries one or more sensors and talks to the platform. A tenant places it on one farm at a time; until then it is unassigned.
_Avoid_: Node, board, unit

**Unassigned**:
The state of a device that is registered but not placed on any farm. It appears on no farm and counts toward no farm's health.
_Avoid_: Unplaced, orphaned

**Offline**:
The state of a device that has reported before but has sent nothing for longer than the offline threshold. It goes back online with its next reading.
_Avoid_: Disconnected, down

**Device Secret**:
The credential shipped with a device (on its QR label) that proves possession of it at registration and login.
_Avoid_: Device password, key

**Device Type**:
How a device reaches the platform: Wi-Fi (directly over HTTP or MQTT) or LoRa (radio, through a gateway).
_Avoid_: Connectivity, transport

**Gateway**:
A relay that receives LoRa radio packets and forwards them to the platform. It is untrusted and never sees readable data. A tenant registers each gateway by name, and the platform issues the credentials the gateway uses to connect. A gateway belongs to a tenant, not to a farm or a device.
_Avoid_: Hub, bridge

**Sensor**:
One measuring element on a device, of a single type (moisture, temperature, humidity, light, battery). It is on its device's farm, at its own position within it.
_Avoid_: Probe, channel

**Diagnostic Sensor**:
A sensor that reports on the device itself rather than the farm, such as battery. Shown on device screens, hidden from farm screens.
_Avoid_: Technical sensor, internal sensor

### Data

**Reading**:
A single value a sensor reported at a point in time. It belongs to the farm its device was on when it was taken, even after the device moves.
_Avoid_: Measurement, data point, sample

### Monitoring

**Alert**:
A record that a condition needing attention started on a subject (a farm, device or sensor). It is open from when the condition starts until it clears, then resolved. The platform resolves it; a user cannot dismiss it. Each occurrence is its own alert, and resolved alerts stay as history.
_Avoid_: Notification, warning, incident

**Alert Rule**:
A condition the platform checks to open and resolve alerts, such as a device being offline or its battery being low. Each rule gives its alerts a fixed severity.
_Avoid_: Trigger, check

**Severity**:
How serious an alert is: danger, warning or info.
_Avoid_: Priority, level, tone
