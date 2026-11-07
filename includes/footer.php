<?php
/**
 * Shared Footer Component
 * HR Management System
 */
?>
        </main>
        <!-- Footer -->
        <footer class="px-4 py-3 bg-white border-top text-secondary d-flex flex-column flex-md-row justify-content-between align-items-center" style="font-size: 0.8rem;">
            <div>
                &copy; <?= date('Y') ?> <strong><?= htmlspecialchars($_SESSION['company_name'] ?? 'HRMS') ?></strong>. All rights reserved.
            </div>
            <div class="d-flex gap-3 mt-2 mt-md-0">
                <span class="text-muted"><i class="bi bi-shield-check text-success me-1"></i>Enterprise Secure v2.4</span>
                <span class="text-muted"><i class="bi bi-clock me-1"></i>Server Time: <?= date('h:i A') ?></span>
            </div>
        </footer>
    </div>
</div>

<!-- Bootstrap 5 Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Main Application JS -->
<script src="<?= BASE_URL ?>assets/js/main.js"></script>
</body>
</html>

