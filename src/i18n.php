<?php
// src/i18n.php

require_once __DIR__ . '/db.php';

function app_language(): string {
    static $lang = null;
    if ($lang !== null) return $lang;

    $pdo = db();
    $stmt = $pdo->prepare("SELECT setting_value FROM app_settings WHERE setting_key = 'app_language'");
    $stmt->execute();
    $row = $stmt->fetch();
    $lang = $row ? trim($row['setting_value']) : 'en';

    // Fallback
    if (!in_array($lang, ['en', 'ar'], true)) {
        $lang = 'en';
    }
    return $lang;
}