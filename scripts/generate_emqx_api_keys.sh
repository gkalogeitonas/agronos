#!/usr/bin/env bash
set -euo pipefail

# Generates docker/emqx/default_api_key.conf from .env
# Usage: ./scripts/generate_emqx_api_keys.sh

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="$HERE/.env"
OUT_DIR="$HERE/docker/emqx"
OUT_FILE="$OUT_DIR/default_api_key.conf"

if [ ! -f "$ENV_FILE" ]; then
  echo ".env file not found at $ENV_FILE"
  exit 1
fi

mkdir -p "$OUT_DIR"

# Read variables (only the two we need)
MQTT_API_KEY=$(grep -E '^MQTT_API_KEY=' "$ENV_FILE" | cut -d'=' -f2- | tr -d '"') || true
MQTT_API_SECRET=$(grep -E '^MQTT_API_SECRET=' "$ENV_FILE" | cut -d'=' -f2- | tr -d '"') || true
echo "Api SECRET: $MQTT_API_SECRET"
echo "Api KEY: $MQTT_API_KEY"
if [ -z "$MQTT_API_KEY" ] || [ -z "$MQTT_API_SECRET" ]; then
  echo "MQTT_API_KEY and/or MQTT_API_SECRET not found in $ENV_FILE"
  exit 1
fi

printf '%s:%s\n' "$MQTT_API_KEY" "$MQTT_API_SECRET" > "$OUT_FILE"
chmod 640 "$OUT_FILE" || true
echo "Wrote API key bootstrap file to $OUT_FILE"
