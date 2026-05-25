<?php
/**
 * Banner de anuncio global del superadmin.
 *
 * Incluir en el layout del panel principal justo después de <body>.
 * Usa localStorage para no repetir el mismo anuncio hasta que cambie el ID.
 *
 * @package  Es21Plus\Includes
 * @author   Carlos Vico
 * @version  1.0.0
 */

require_once __DIR__ . '/../superadmin/controllers/AnnouncementController.php';

$_announcementBannerData = AnnouncementController::activo();
?>
<?php if ($_announcementBannerData): ?>
<div id="announcementBanner"
     data-announcement-id="<?= (int)$_announcementBannerData['id'] ?>"
     style="display:none;background:linear-gradient(90deg,#4f46e5,#818cf8);color:#fff;
            padding:.6rem 1.5rem;font-size:.84rem;display:flex;align-items:center;
            gap:1rem;position:sticky;top:0;z-index:200;">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;opacity:.85">
        <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
    </svg>
    <span style="flex:1;">
        <strong><?= htmlspecialchars($_announcementBannerData['title'], ENT_QUOTES, 'UTF-8') ?>:</strong>
        <?= htmlspecialchars($_announcementBannerData['body'], ENT_QUOTES, 'UTF-8') ?>
    </span>
    <button onclick="cerrarAnuncio()" style="background:none;border:none;color:#fff;cursor:pointer;opacity:.8;padding:0;line-height:1;" title="Cerrar">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>
</div>
<script>
(function() {
    var el  = document.getElementById('announcementBanner');
    var aid = el ? el.dataset.announcementId : null;
    if (!aid) return;
    var dismissed = localStorage.getItem('dismissed_announcement');
    if (dismissed !== aid) {
        el.style.display = 'flex';
    }
})();
function cerrarAnuncio() {
    var el = document.getElementById('announcementBanner');
    if (el) {
        localStorage.setItem('dismissed_announcement', el.dataset.announcementId);
        el.style.display = 'none';
    }
}
</script>
<?php endif; ?>
