jQuery(document).ready(function($) {
    // Send heartbeat every 2 minutes for frontend
    function sendHeartbeat() {
        $.ajax({
            url: userOnlineAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'user_heartbeat',
                nonce: userOnlineAjax.nonce
            },
            success: function(response) {
                console.log('Frontend heartbeat sent successfully');
            },
            error: function() {
                console.log('Frontend heartbeat failed');
            }
        });
    }
    
    // Send initial heartbeat
    sendHeartbeat();
    
    // Set interval for heartbeat (every 2 minutes)
    setInterval(sendHeartbeat, 120000);
    
    // Track user activity (mouse movement, clicks, scrolling)
    var activityTimer;
    var isActive = true;
    
    function resetActivityTimer() {
        clearTimeout(activityTimer);
        
        if (!isActive) {
            isActive = true;
            sendHeartbeat(); // Send heartbeat when user becomes active again
        }
        
        // Set user as inactive after 5 minutes of no activity
        activityTimer = setTimeout(function() {
            isActive = false;
        }, 300000);
    }
    
    // Listen for user activity
    $(document).on('mousemove click scroll keypress', function() {
        resetActivityTimer();
    });
    
    // Initialize activity timer
    resetActivityTimer();
    
    // Page visibility API to handle tab switching
    var hidden, visibilityChange;
    if (typeof document.hidden !== "undefined") {
        hidden = "hidden";
        visibilityChange = "visibilitychange";
    } else if (typeof document.webkitHidden !== "undefined") {
        hidden = "webkitHidden";
        visibilityChange = "webkitvisibilitychange";
    }
    
    function handleVisibilityChange() {
        if (document[hidden]) {
            // Tab is hidden - reduce heartbeat frequency
            console.log('Tab hidden - reducing heartbeat frequency');
        } else {
            // Tab is visible - send immediate heartbeat
            sendHeartbeat();
            console.log('Tab visible - sending heartbeat');
        }
    }
    
    if (typeof document.addEventListener !== "undefined" && typeof document[hidden] !== "undefined") {
        document.addEventListener(visibilityChange, handleVisibilityChange, false);
    }
    
    // Send heartbeat before page unload
    $(window).on('beforeunload', function() {
        // Use navigator.sendBeacon if available for more reliable sending
        if (navigator.sendBeacon) {
            var formData = new FormData();
            formData.append('action', 'user_heartbeat');
            formData.append('nonce', userOnlineAjax.nonce);
            navigator.sendBeacon(userOnlineAjax.ajax_url, formData);
        } else {
            // Fallback to synchronous AJAX
            $.ajax({
                url: userOnlineAjax.ajax_url,
                type: 'POST',
                async: false,
                data: {
                    action: 'user_heartbeat',
                    nonce: userOnlineAjax.nonce
                }
            });
        }
    });
});