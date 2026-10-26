document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.alert[data-autodismiss="true"]').forEach((alert) => {
        window.setTimeout(() => {
            if (window.bootstrap) {
                window.bootstrap.Alert.getOrCreateInstance(alert).close();
            }
        }, 5000);
    });
});
