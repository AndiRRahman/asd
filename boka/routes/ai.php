<?php

use App\Mcp\Servers\TokoServer;
use Laravel\Mcp\Facades\Mcp;

// Server MCP Admin (bagian 14): token Sanctum, 30 panggilan per menit per token.
Mcp::web('/mcp/toko', TokoServer::class)->middleware(['auth:sanctum', 'throttle:30,1']);
