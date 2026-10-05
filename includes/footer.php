    </main>
</div>

<!-- Notification Panel -->
<div class="notification-panel" id="notificationPanel">
    <div class="notification-panel-header">
        <h3>Notifications</h3>
        <button class="modal-close" onclick="toggleNotifications()">&times;</button>
    </div>
    <div class="notification-list" id="notificationList">
        <div class="loading-overlay">
            <div class="spinner"></div>
            <span>Loading notifications...</span>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div class="toast-container" id="toastContainer"></div>

<!-- Photo Lightbox -->
<div class="photo-lightbox" id="photoLightbox" onclick="closePhotoLightbox()">
    <img id="photoLightboxImg" alt="Profile Photo">
</div>

<script src="/MediQueue2/assets/js/script.js?v=5"></script>
<script>
function openPhotoLightbox(src) {
    document.getElementById('photoLightboxImg').src = src;
    document.getElementById('photoLightbox').classList.add('show');
}
function closePhotoLightbox() {
    document.getElementById('photoLightbox').classList.remove('show');
}
document.addEventListener('click', function (e) {
    var t = e.target.closest('.photo-zoom');
    if (t) {
        e.preventDefault();
        openPhotoLightbox(t.getAttribute('data-src'));
    }
});
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { closePhotoLightbox(); }
});
</script>
</body>
</html>
