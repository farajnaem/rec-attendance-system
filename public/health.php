<?php

declare(strict_types=1);

/**
 * فحص صحة بسيط — لا يحتاج قاعدة بيانات.
 * استخدمه في Coolify: Health Check Path = /health.php
 */
header('Content-Type: application/json; charset=utf-8');
http_response_code(200);
echo json_encode(['status' => 'ok', 'service' => 'rec-attendance'], JSON_UNESCAPED_UNICODE);
