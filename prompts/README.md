# Prompt Management System

This directory contains prompt templates for the WordPress AI Chatbot. The prompts support dynamic placeholder replacement for WordPress-specific data.

## Features

- **File-based prompt templates**: Store prompts as markdown files
- **Dynamic placeholders**: Automatically replace placeholders with live WordPress data
- **Extensible**: Filter hooks for custom placeholders and prompt modifications
- **Caching**: Prompts are cached for performance

## Available Placeholders

### Site Information
- `{{site.name}}` - Site title
- `{{site.url}}` - Site URL
- `{{site.description}}` - Site tagline
- `{{site.admin_url}}` - WordPress admin URL
- `{{site.ai_settings_url}}` - AI settings page URL
- `{{site.timezone}}` - Site timezone

### WordPress Information
- `{{wp.version}}` - WordPress version
- `{{wp.language}}` - Site language/locale

### User Information
- `{{user.display_name}}` - Current user's display name
- `{{user.email}}` - Current user's email
- `{{user.role}}` - Current user's primary role
- `{{user.id}}` - Current user's ID

### Date and Time
- `{{date.today}}` - Today's date (formatted)
- `{{date.time}}` - Current time
- `{{date.datetime}}` - Full datetime
- `{{date.year}}` - Current year
- `{{date.month}}` - Current month
- `{{date.day}}` - Current day

### Custom Placeholders
- `{{custom.YOUR_KEY}}` - Custom placeholders passed via context

## Usage in Code

```php
// Initialize the prompt manager
$prompt_manager = new Prompt_Manager( '/path/to/prompts' );

// Get a prompt with placeholders replaced
$prompt = $prompt_manager->get_prompt( 'chatbot-system-prompt' );

// Pass custom context for additional placeholders
$context = array(
    'custom' => array(
        'context' => 'Additional custom information here'
    )
);
$prompt = $prompt_manager->get_prompt( 'chatbot-system-prompt', $context );
```

## Filter Hooks

### `wp_ai_chatbot_prompt_context`
Modify the context before placeholder replacement:
```php
add_filter( 'wp_ai_chatbot_prompt_context', function( $context ) {
    $context['custom']['my_value'] = 'Custom value';
    return $context;
} );
```

### `wp_ai_chatbot_prompt_placeholders`
Add or modify available placeholders:
```php
add_filter( 'wp_ai_chatbot_prompt_placeholders', function( $placeholders, $context ) {
    $placeholders['{{custom.new}}'] = 'New placeholder value';
    return $placeholders;
}, 10, 2 );
```

### `wp_ai_chatbot_system_prompt`
Modify the final prompt after all replacements:
```php
add_filter( 'wp_ai_chatbot_system_prompt', function( $prompt, $context ) {
    // Modify the prompt as needed
    return $prompt;
}, 10, 2 );
```

## Editing Prompts

To modify the chatbot's system prompt:

1. Edit the `chatbot-system-prompt.md` file in this directory
2. Use any of the available placeholders listed above
3. The changes will take effect immediately (cached prompts are refreshed on each request)

## Creating Additional Prompts

1. Create a new `.md` file in this directory
2. Use any of the available placeholders
3. Access it via code: `$prompt_manager->get_prompt( 'your-prompt-name' )`

## Best Practices

1. Keep prompts focused and clear
2. Use placeholders for dynamic data instead of hardcoding
3. Test prompts with different user roles and contexts
4. Document any custom placeholders you add
5. Version control your prompt files for easy rollback