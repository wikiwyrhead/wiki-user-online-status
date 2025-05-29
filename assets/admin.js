jQuery(document).ready(function($) {
    // Send heartbeat every 2 minutes for admin pages
    function sendHeartbeat() {
        $.ajax({
            url: userOnlineAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'user_heartbeat',
                nonce: userOnlineAjax.nonce
            },
            success: function(response) {
                console.log('Heartbeat sent successfully');
            },
            error: function() {
                console.log('Heartbeat failed');
            }
        });
    }
    
    // Send initial heartbeat
    sendHeartbeat();
    
    // Set interval for heartbeat (every 2 minutes)
    setInterval(sendHeartbeat, 120000);
    
    // Auto-refresh user online status column every 30 seconds on users.php page
    if (window.location.href.indexOf('users.php') > -1) {
        setInterval(function() {
            $('.column-user_online_status').each(function() {
                var $row = $(this).closest('tr');
                var userId = $row.find('input[name="users[]"]').val();
                
                if (userId) {
                    // You could add AJAX call here to refresh individual user status
                    // For now, we'll just reload the page every 5 minutes
                }
            });
        }, 30000);
        
        // Full page refresh every 5 minutes to update all statuses
        setTimeout(function() {
            if (window.location.href.indexOf('users.php') > -1) {
                window.location.reload();
            }
        }, 300000);
    }
    
    // Add visual indicators
    function updateOnlineIndicators() {
        $('.user-online-indicator').each(function() {
            var $indicator = $(this);
            if ($indicator.hasClass('online')) {
                $indicator.css({
                    'animation': 'pulse 2s infinite',
                    'color': '#00a32a'
                });
            }
        });
    }
    
    // Add CSS animation for online indicators
    $('<style>')
        .prop('type', 'text/css')
        .html(`
            @keyframes pulse {
                0% { opacity: 1; }
                50% { opacity: 0.5; }
                100% { opacity: 1; }
            }
            .user-online-indicator.online {
                animation: pulse 2s infinite;
            }
        `)
        .appendTo('head');
    
    updateOnlineIndicators();
});