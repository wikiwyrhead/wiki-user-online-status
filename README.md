# Wiki User Online Status

A lightweight WordPress plugin that tracks and displays user online/offline status, similar to WP User Online plugin.

## Features

- Tracks user online/offline status in real-time
- Displays online status in the WordPress admin users list
- Shows last seen time for offline users
- Lightweight and optimized for performance
- Mobile-friendly and responsive
- Supports multiple user roles
- Clean and intuitive interface

## Installation

1. Upload the `wiki-user-online-status` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. That's it! The plugin will start tracking user activity automatically

## Usage

### Shortcodes

#### Display Online Users List

```php
[online_users]
```

#### Display Online Users Count

```php
[online_users_count]
```

### Admin Area

- The plugin adds an "Online Status" column to the Users page in WordPress admin
- A dedicated "Online Users" page is available under the Users menu

## Customization

### CSS Styling

You can customize the appearance by adding CSS to your theme's stylesheet:

```css
/* Change online indicator color */
.user-online-indicator.online {
    color: #00a32a;
}

/* Change offline indicator color */
.user-online-indicator.offline {
    color: #d63638;
}
```

## Performance

The plugin is optimized for performance with:
- Transient caching to reduce database queries
- Batch processing for cleanup operations
- Efficient database queries with proper indexing
- Minimal impact on page load time

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher

## Changelog

### 1.0.1

- Fixed admin page warnings and improved user data handling
- Added proper error handling for missing user data
- Improved security with output escaping

### 1.0.0

- Initial release

## License

This plugin is licensed under the GPL v2 or later.

## Credits

Developed by [Arnel Go](https://arnelbg.com/)
