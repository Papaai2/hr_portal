<?php
// in file: app/templates/footer.php
// This file assumes it's included within the <body> and after the <main> content.
?>
            </main>
            <footer class="footer mt-auto py-3">
                <div class="container text-center">
                    <small>&copy; <?= date('Y') ?> <?php echo htmlspecialchars(SITE_NAME); ?>. All rights reserved.</small>
                </div>
            </footer>
        </div><!-- /#page-content-wrapper -->
    </div><!-- /#wrapper -->

<!-- Bootstrap JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
<!-- Your Custom JS -->
<script src="/js/main.js"></script>
<!-- Removed dashboard-enhancements.js as its contents are merged into main.js -->

</body>
</html>