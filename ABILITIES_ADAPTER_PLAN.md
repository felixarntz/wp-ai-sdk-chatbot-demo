# Abilities ↔ Tools Adapter Plan for PHP SDK

## Overview

This document outlines the plan for creating an adapter that allows the PHP AI Client SDK to work with WordPress Abilities API instead of the current temporary tool system.

## Current State

- **Demo Tools**: Currently uses custom Tool classes that implement a Tool interface
- **Tool Structure**: Each tool has name, description, parameters, and execute methods
- **Integration**: Tools are passed directly to the Chatbot_Agent constructor
- **Format**: Tools provide function call format for LLM consumption

## Target State

- **Abilities**: Use WordPress Abilities API for all functionality
- **Adapter**: Create an adapter that converts abilities to tool-compatible format
- **Integration**: Seamless replacement without changing agent interface
- **Format**: Maintain same function call format for LLM compatibility

## Implementation Plan

### 1. Create Abilities-to-Tools Adapter

**File**: `includes/Adapters/Abilities_Tool_Adapter.php`

```php
namespace Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Adapters;

use Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Tools\Contracts\Tool;
use WP_Ability;

class Abilities_Tool_Adapter implements Tool {
    private WP_Ability $ability;
    
    public function __construct(WP_Ability $ability) {
        $this->ability = $ability;
    }
    
    public function get_name(): string {
        // Convert namespace/ability-name to snake_case for tool compatibility
        return str_replace(['/', '-'], '_', $this->ability->get_name());
    }
    
    public function get_description(): string {
        return $this->ability->get_description();
    }
    
    public function get_parameters(): array {
        // Convert JSON Schema from ability to tool parameter format
        return $this->ability->get_input_schema();
    }
    
    public function execute($args) {
        // Execute the underlying ability
        return $this->ability->execute((array) $args);
    }
}
```

### 2. Create Ability Collection Manager

**File**: `includes/Managers/Ability_Manager.php`

```php
namespace Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Managers;

use Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Adapters\Abilities_Tool_Adapter;

class Ability_Manager {
    
    public function get_chatbot_tools(): array {
        $abilities = $this->get_chatbot_abilities();
        $tools = [];
        
        foreach ($abilities as $ability) {
            $tools[] = new Abilities_Tool_Adapter($ability);
        }
        
        return $tools;
    }
    
    private function get_chatbot_abilities(): array {
        $all_abilities = wp_get_abilities();
        $chatbot_abilities = [];
        
        // Filter to only include chatbot demo abilities
        foreach ($all_abilities as $ability) {
            if (strpos($ability->get_name(), 'wp-ai-sdk-chatbot-demo/') === 0) {
                $chatbot_abilities[] = $ability;
            }
        }
        
        return $chatbot_abilities;
    }
}
```

### 3. Update Plugin Integration

**File**: `includes/Plugin_Main.php` (modifications)

```php
// Replace tool instantiation with ability manager
private function get_tools(): array {
    $ability_manager = new Managers\Ability_Manager();
    return $ability_manager->get_chatbot_tools();
}
```

### 4. Schema Compatibility Layer

**Challenge**: Abilities API uses REST API schema format, while tools may use different parameter formats.

**Solution**: Create schema converter in the adapter:

```php
private function convert_schema_format(array $ability_schema): array {
    // Convert REST API schema to tool parameter format
    // Handle differences in validation rules, types, etc.
    return $this->normalize_schema($ability_schema);
}
```

## Migration Benefits

1. **Standardization**: Use WordPress core API instead of custom tool system
2. **Extensibility**: Other plugins can register abilities for the chatbot
3. **Validation**: Built-in input/output validation from Abilities API
4. **Permissions**: Leverage ability permission callbacks
5. **Future-proofing**: Align with WordPress AI initiatives

## Compatibility Considerations

1. **Schema Format**: Ensure parameter schemas are compatible between abilities and tools
2. **Error Handling**: Map WP_Error from abilities to expected tool error format
3. **Permissions**: Maintain existing permission checks through ability callbacks
4. **Function Names**: Ensure function names in LLM prompts remain consistent

## Testing Strategy

1. **Unit Tests**: Test adapter converts abilities correctly
2. **Integration Tests**: Verify chatbot works with ability-based tools
3. **Regression Tests**: Ensure all existing functionality works
4. **Permission Tests**: Verify permission callbacks work correctly

## Rollout Plan

1. **Phase 1**: Implement adapter and ability manager
2. **Phase 2**: Create abilities registration file (✓ Complete)
3. **Phase 3**: Update plugin integration to use adapter
4. **Phase 4**: Remove old tool classes
5. **Phase 5**: Update documentation and examples

## File Structure After Migration

```
includes/
├── abilities.php                    # All ability registrations (✓ Created)
├── Adapters/
│   └── Abilities_Tool_Adapter.php   # Converts abilities to tools
├── Managers/
│   └── Ability_Manager.php          # Manages chatbot abilities
├── Agents/                          # Unchanged
├── Providers/                       # Unchanged
└── REST_Routes/                     # Unchanged

# Remove after migration:
├── Tools/ (entire directory)
```

## Next Steps

1. ✅ Create abilities registration file
2. 🔄 Implement Abilities_Tool_Adapter class
3. ⏳ Implement Ability_Manager class
4. ⏳ Update Plugin_Main.php integration
5. ⏳ Test and validate functionality
6. ⏳ Remove old tool classes

## Notes

- The adapter approach allows gradual migration without breaking changes
- Existing agent interface remains unchanged
- Function calling format for LLM remains compatible
- Permission system is enhanced through ability callbacks