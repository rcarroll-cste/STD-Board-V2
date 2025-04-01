# STD Board V2 Development Guide

## Technology Stack
- WordPress CMS with custom post types and taxonomies
- Advanced Custom Fields (ACF) plugin for field management
- WPGraphQL for GraphQL API interactions
- Kendo UI for frontend components and data grids

## Key Commands
- No formal build/lint/test commands available
- WordPress admin available at `/wp-admin`

## Code Style Guidelines
- JavaScript: Use camelCase for variables and functions
- PHP: Use snake_case for functions and variables
- ACF fields: Use snake_case (e.g., user_jurisdiction, notes_sti_hiv)
- GraphQL: Follow camelCase convention for queries and fields

## Kendo UI Guidelines
- Use Kendo UI best practices and built-in functions
- 'date of last exposure' should use textbox editor, not datepicker
- Array types in Kendo should be represented as "object" type
- Use proper error handling with notifications and console logging

## Development Rules
- Do not modify ACF_Export.php directly as it's a source of truth
- Don't add new functionality unless confirmed by the user
- Only make updates directly related to what was requested
- Avoid assumptions, confirm with the user when unclear