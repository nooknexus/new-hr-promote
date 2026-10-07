<div>
    <div class="d-flex justify-content-center align-items-center" style="min-height: 70vh;">
        <div class="text-center">
            <div class="spinner-border text-primary mb-3" role="status">
                <span class="sr-only">Loading...</span>
            </div>
            <h3>กำลังเชื่อมต่อกับระบบ Provider ID</h3>
            <p class="text-muted">กรุณารอสักครู่...</p>
        </div>
    </div>

    <script>
        // Immediately redirect to the Provider ID auth - no delay
        window.location.href = "{{ $redirectUrl }}";
    </script>
</div>
