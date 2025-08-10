# Sitemap Implementation Guide

This Laravel e-commerce project includes a comprehensive sitemap generation system using Spatie Laravel Sitemap package.

## Overview

The sitemap system includes:
- **Automatic daily regeneration** via scheduled tasks
- **Intelligent sitemap indexing** for large product catalogs
- **Auto-regeneration** when products/categories are modified
- **SEO-optimized** structure with proper priorities and frequencies

## Commands Available

### 1. Basic Sitemap Generation
```bash
php artisan generate:sitemap
```
- Generates a single sitemap.xml with all pages
- Best for smaller sites (<5000 products)
- Includes homepage, static pages, categories, and products

### 2. Advanced Sitemap Index Generation
```bash
php artisan generate:sitemap-index
```
- Generates multiple sitemap files with an index
- Automatically splits large product catalogs into separate files
- Best for sites with >5000 products
- Creates:
  - `sitemap.xml` (main index)
  - `sitemap-main.xml` (homepage + static pages)
  - `sitemap-categories.xml` (all categories)
  - `sitemap-products-1.xml`, `sitemap-products-2.xml`, etc. (products)

### 3. Clear All Sitemaps
```bash
php artisan sitemap:clear
php artisan sitemap:clear --confirm  # Skip confirmation
```
- Removes all generated sitemap files
- Useful for testing or cleanup

## Scheduled Tasks

The system automatically generates sitemaps:

- **Daily at 2:00 AM**: Runs `generate:sitemap-index` (primary)
- **Weekly on Sunday at 3:00 AM**: Runs `generate:sitemap` (backup)

Logs are stored in:
- `storage/logs/sitemap.log` (daily index generation)
- `storage/logs/sitemap-simple.log` (weekly simple generation)

## Automatic Regeneration

The system includes smart auto-regeneration:

### Triggers
Sitemaps are automatically regenerated when:
- Products are created, updated (important fields), or deleted
- Categories are created, updated (important fields), or deleted

### Important Fields
**Products**: `slug`, `status`, `name`, `stock_quantity`
**Categories**: `slug`, `is_active`, `name`

### Debouncing
- Auto-regeneration is debounced to prevent excessive runs
- Maximum once every 5 minutes via cache mechanism
- Runs in background using `afterResponse()` queue

## SEO Configuration

### URL Priorities
- **Homepage**: 1.0 (highest)
- **Products listing**: 0.9
- **Categories**: 0.8
- **Products**: 0.7
- **About page**: 0.7
- **Contact page**: 0.6
- **FAQ/Shipping**: 0.5
- **Legal pages**: 0.3 (lowest)

### Change Frequencies
- **Homepage**: Daily
- **Products listing**: Hourly
- **Categories**: Weekly
- **Products**: Weekly
- **Static pages**: Monthly to Yearly

### Content Rules
- Only **active** products and categories are included
- Only products **in stock** are included
- Categories must have at least one active product

## robots.txt Integration

The system includes an enhanced robots.txt that:
- Blocks admin, dashboard, cart, checkout, and API routes
- Includes dynamic sitemap reference
- Available at `/robots.txt` route

## File Structure

```
public/
├── sitemap.xml              # Main sitemap (index or single)
├── sitemap-main.xml         # Static pages (index mode)
├── sitemap-categories.xml   # Categories (index mode)
├── sitemap-products-1.xml   # Products batch 1 (index mode)
├── sitemap-products-2.xml   # Products batch 2 (index mode)
└── ...
```

## Manual Operations

### Force Regeneration
```bash
# Basic sitemap
php artisan generate:sitemap

# Advanced sitemap index (recommended)
php artisan generate:sitemap-index

# Clear and regenerate
php artisan sitemap:clear --confirm && php artisan generate:sitemap-index
```

### Check Generated Files
```bash
ls -la public/sitemap*.xml
```

### View Logs
```bash
tail -f storage/logs/sitemap.log
```

## Testing

### Validate Sitemap
- Submit to Google Search Console
- Test with online sitemap validators
- Check file accessibility: `curl https://yoursite.com/sitemap.xml`

### Local Testing
```bash
# Generate and check
php artisan generate:sitemap-index
cat public/sitemap.xml
```

## Performance Notes

- **Large Catalogs**: Use `generate:sitemap-index` for >5000 products
- **Memory**: Chunked processing prevents memory issues
- **Speed**: Background auto-regeneration doesn't slow user requests
- **Cache**: Smart debouncing prevents redundant generations

## Troubleshooting

### Common Issues

1. **Permission Errors**
   ```bash
   chmod 755 public/
   chmod 644 public/sitemap*.xml
   ```

2. **Missing Files**
   - Check if commands are registered in `AppServiceProvider`
   - Verify scheduled tasks with `php artisan schedule:list`

3. **Large Site Performance**
   - Use sitemap index version
   - Increase memory limit if needed: `php -d memory_limit=512M artisan generate:sitemap-index`

### Debug Commands
```bash
# List all commands
php artisan list | grep sitemap

# Check scheduled tasks
php artisan schedule:list

# Test schedule
php artisan schedule:run
```

This comprehensive sitemap system ensures optimal SEO performance with minimal maintenance overhead.
