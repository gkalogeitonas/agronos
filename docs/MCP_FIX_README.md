# Laravel Boost MCP Protocol Fix

This document outlines the steps taken to resolve the `MPC -32602: Unsupported protocol version` error when trying to run the Laravel Boost MCP server with VS Code.

## The Issue
The VS Code MCP Client was requesting a protocol version that the `laravel/mcp` package did not strictly support, causing the server to reject the connection during the initialization handshake.

## The Solution

The fix involved patching the vendor package to bypass the strict protocol version check.

### 1. Configuration (`.vscode/mcp.json`)
We configured the server to run inside the DDEV container to ensure a clean environment and correct database access. output is directed to `stderr` to avoid polluting the JSON-RPC stream.

```json
"laravel-boost": {
    "command": "ddev",
    "args": [
        "exec",
        "php",
        "-d",
        "display_errors=stderr",
        "artisan",
        "boost:mcp"
    ]
}
```

### 2. The Patch (Critical)
We modified the `vendor/laravel/mcp/src/Server/Methods/Initialize.php` file to disable the exception thrown when versions do not match.

**File:** `vendor/laravel/mcp/src/Server/Methods/Initialize.php`

**Change:**
```php
public function handle(JsonRpcRequest $request, ServerContext $context): JsonRpcResponse
{
    $requestedVersion = $request->params['protocolVersion'] ?? null;
    
    // LOGGING ADDED
    file_put_contents(base_path('storage/logs/mcp_version.log'), "Requested: " . json_encode($requestedVersion) . "\nSupported: " . json_encode($context->supportedProtocolVersions) . "\n", FILE_APPEND);

    // BYPASS CHECK: Added `false &&` to short-circuit the exception
    if (false && ! is_null($requestedVersion) && ! in_array($requestedVersion, $context->supportedProtocolVersions, true)) {
        throw new JsonRpcException(
            message: 'Unsupported protocol version',
            // ...
        );
    }
    // ...
}
```

## ⚠️ Important Warning
**This fix is temporary.**

Because the modification was made inside the `vendor/` directory, **running `composer update` or `composer install` will overwrite this patch**, and the error will likely return.

### Long-term Solution
1.  **Diagnosed Version Mismatch:**
    *   **Requested by VS Code:** `2025-11-25`
    *   **Supported by Laravel:** `2025-06-18`, `2025-03-26`, `2024-11-05`
2.  Wait for `laravel/mcp` to update and support `2025-11-25`.
3.  Or, override the `Initialize` class in your own application code if possible (though patching `vendor` is often the quickest fix for local dev tools).
