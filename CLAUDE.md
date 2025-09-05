# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Development Environment Setup

This is a WordPress plugin that uses `@wordpress/env` for local development with a custom configuration. The development environment runs on **port 8902** (not the default 8888):

```bash
# Setup commands
composer install
npm install  
npm run build
npm run wp-env start
```

Visit the local site at `http://localhost:8902/wp-admin/`

## Key Commands

### Build and Development
- `npm run build` - Build TypeScript and bundle assets (runs `wp-scripts build && tsc --build`)
- `npm run wp-env start` - Start WordPress development environment
- `npm run wp-env stop` - Stop development environment

### Code Quality
- `npm run lint-js` - Lint JavaScript/TypeScript files (includes `tsc --noEmit`)  
- `npm run lint-php` - Lint PHP files via wp-env
- `npm run format-php` - Format PHP files via wp-env
- `npm run phpstan` - Run PHPStan analysis via wp-env

### Composer Commands (run via wp-env)
- `npm run format-php` - Format PHP code with PHPCBF
- `npm run lint-php` - Lint PHP code with PHPCS
- `npm run phpstan` - Run PHPStan analysis

## Architecture Overview

This WordPress plugin implements an AI chatbot using multiple architectural layers:

### PHP Backend Architecture

**Core Plugin Structure:**
- Main entry point: `wp-ai-sdk-chatbot-demo.php` with autoloader and requirements checking
- Namespace: `Felix_Arntz\WP_AI_SDK_Chatbot_Demo\` 
- PSR-4 autoloading from `includes/` directory

**Key Components:**
- `Plugin_Main` - Central orchestrator handling WordPress hooks, provider management, and UI initialization
- `Provider_Manager` - Manages AI providers (Anthropic, Google, OpenAI) with credential handling and validation
- `Chatbot_Messages_REST_Route` - REST API endpoint for chatbot communication
- `Agents/Chatbot_Agent` - Core agent implementation for processing messages
- `Abilities` - WordPress abilities integration (search posts, create drafts, etc.)

**External Dependencies:**
- WordPress Abilities API for tool/function calling
- PHP AI Client SDK for provider abstraction  
- MCP Adapter for Model Context Protocol integration
- Jetpack Autoloader for dependency management

### Frontend Architecture

**React/TypeScript Components:**
- `src/index.tsx` - Entry point with DOM mounting logic
- `src/components/ChatbotApp/` - Main chatbot application container
- `src/components/Chatbot/` - Core chat interface
- `src/components/EmptyState/` - Initial state component

**Build System:**
- Uses `@wordpress/scripts` for building and bundling
- TypeScript compilation with `tsc --build`
- SCSS compilation for styling
- Integration with Agenttic UI components

### Integration Points

**WordPress Integration:**
- Capability system: `wpaisdk_access_chatbot` capability (defaults to `manage_options`)
- Admin UI injection via `admin_footer` hook
- REST API routes under `wpaisdk-chatbot/v1/` namespace

**MCP Integration:**
- Creates MCP server for exposing WordPress abilities
- Supports HTTP REST transport for MCP communication
- Ability registration on `abilities_api_init` hook

**Multi-Provider Support:**
- Unified interface across Anthropic, Google, OpenAI
- Provider credential validation and admin notices
- Model metadata exposure to frontend

## Debugging and Logs

**Debug Log Location:**
- Debug logs are stored at: `/var/www/html/wp-content/uploads/debug-log-manager/localhost_*_debug.log`
- Access via wp-env: `docker exec -i <container-name> tail -f /var/www/html/wp-content/uploads/debug-log-manager/localhost_*_debug.log`
- Clear logs: `docker exec -i <container-name> truncate -s 0 /var/www/html/wp-content/uploads/debug-log-manager/localhost_*_debug.log`

## Development Notes

- Plugin supports WordPress 6.8+ and PHP 8.1+
- Development environment uses PHP 8.2 with debugging enabled
- MCP Adapter integration avoids conflicts with existing MCP installations
- Provider validation happens on admin pages with error feedback
- Frontend configuration passed via `wp_add_inline_script`