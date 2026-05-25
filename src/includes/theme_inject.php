<?php
/**
 * Inyecta la variable CSS --primary con el color de marca del negocio en sesión.
 *
 * Incluir dentro del <head> de cada página del panel de empresa.
 *
 * @package  Es21Plus\Includes
 * @author   Carlos Vico
 * @version  1.0.0
 */

$_themeColor = '#4F46E5';
if (!empty($_SESSION['business_theme_color'])) {
    $raw = trim($_SESSION['business_theme_color']);
    if (preg_match('/^#[0-9A-Fa-f]{6}$/', $raw)) {
        $_themeColor = $raw;
    }
}
// Calcular versión más oscura: reducir luminosidad ~18%
if (!function_exists('_themeInjectDarken')) {
    function _themeInjectDarken(string $hex): string {
        $r = max(0, (int)(hexdec(substr($hex, 1, 2)) * 0.82));
        $g = max(0, (int)(hexdec(substr($hex, 3, 2)) * 0.82));
        $b = max(0, (int)(hexdec(substr($hex, 5, 2)) * 0.82));
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }
}
$_themeColorDark = _themeInjectDarken($_themeColor);
?>
<style>
:root {
    --primary:      <?= htmlspecialchars($_themeColor,     ENT_QUOTES, 'UTF-8') ?>;
    --primary-dark: <?= htmlspecialchars($_themeColorDark, ENT_QUOTES, 'UTF-8') ?>;
    --accent:       <?= htmlspecialchars($_themeColor,     ENT_QUOTES, 'UTF-8') ?>;
    --accent-dark:  <?= htmlspecialchars($_themeColorDark, ENT_QUOTES, 'UTF-8') ?>;
    --sidebar-active-border: <?= htmlspecialchars($_themeColor, ENT_QUOTES, 'UTF-8') ?>;
    --border-focus: <?= htmlspecialchars($_themeColor, ENT_QUOTES, 'UTF-8') ?>;
}
</style>
