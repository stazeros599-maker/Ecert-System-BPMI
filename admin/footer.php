<?php
// admin/footer.php - Shared footer for all admin pages
?>

    <!-- JavaScript -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
    
    <script>
        // Make sure navigation collapse works on mobile
        $(document).ready(function() {
            // Close mobile menu when clicking a link
            $('.navbar-collapse a').click(function() {
                if ($(window).width() <= 768) {
                    $('.navbar-collapse').collapse('hide');
                }
            });
        });
    </script>
</body>
</html>