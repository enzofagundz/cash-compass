<?php

use App\Mcp\Servers\CashCompassServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::local('cash-compass', CashCompassServer::class);
