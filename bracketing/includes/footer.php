<?php if (Auth::check()): ?>
            </main>
        </div>
    </div>
<?php else: ?>
    </div>
<?php endif; ?>

<div id="toastContainer" class="toast-container"></div>
<div id="loadingOverlay" class="loading-overlay" style="display:none">
    <div class="loader"></div>
</div>

<script src="<?= APP_URL ?>/assets/vendor/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<?php if (isset($extraJS)): foreach ((array)$extraJS as $js): ?>
<script src="<?= APP_URL ?>/assets/js/<?= $js ?>"></script>
<?php endforeach; endif; ?>
</body>
</html>
