<?php
// footer.php - closes main content and includes scripts
?>
            <!-- page content ends -->
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="assets/js/main.js"></script>
<?php if (isset($_SESSION['patient_id'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
        try {
                fetch('fetch_unseen_announcement.php').then(function (res) { return res.json(); }).then(function (data) {
                        if (!data || !data.found) return;

                        // Build modal element
                        var modalId = 'announcementModal';
                        if (document.getElementById(modalId)) return;
                        var div = document.createElement('div');
                        div.innerHTML = `
<div class="modal fade" id="${modalId}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3 announcement-body"></div>
                <div class="announcement-attachment"></div>
            </div>
            <div class="modal-footer">
                <a class="btn btn-outline-primary read-more" href="#" target="_blank">Read More</a>
                <button type="button" class="btn btn-secondary dont-show">Don't Show Again</button>
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>`;
                        document.body.appendChild(div);

                        var modalEl = document.getElementById(modalId);
                        modalEl.querySelector('.modal-title').innerText = data.title || '';
                        modalEl.querySelector('.announcement-body').innerText = data.message || '';

                        if (data.attachment && data.attachment.url) {
                                var aEl = document.createElement('div');
                                var ext = (data.attachment.ext || '').toLowerCase();
                                if (ext === 'jpg' || ext === 'jpeg' || ext === 'png') {
                                        aEl.innerHTML = '<img src="'+data.attachment.url+'" style="max-width:100%;height:auto;border-radius:8px;" alt="Announcement attachment">';
                                } else {
                                        aEl.innerHTML = '<a href="'+data.attachment.url+'" target="_blank" class="btn btn-sm btn-outline-secondary">Download attachment</a>';
                                }
                                modalEl.querySelector('.announcement-attachment').appendChild(aEl);
                        }

                        var readMore = modalEl.querySelector('.read-more');
                        readMore.href = 'view_announcement.php?id=' + encodeURIComponent(data.id || '');

                        // Show modal via Bootstrap
                        var bsModal = new bootstrap.Modal(modalEl, { backdrop: 'static' });
                        bsModal.show();

                        // Mark announcements seen immediately so it won't reappear
                        fetch('mark_announcements_seen.php', { method: 'POST' }).catch(function () {});

                        // Don't show again button also marks seen and hides modal
                        modalEl.querySelector('.dont-show').addEventListener('click', function () {
                                fetch('mark_announcements_seen.php', { method: 'POST' }).catch(function () {});
                                bsModal.hide();
                        });
                }).catch(function () {});
        } catch (e) {}
});
</script>
<?php endif; ?>
</body>
</html>
