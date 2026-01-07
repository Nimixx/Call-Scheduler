# Call Scheduler - CSS Architecture

Modern, modular CSS architecture for the Call Scheduler WordPress plugin.

## Structure

```
css/
├── admin.css                 # Main entry point (imports all files)
├── base/                     # Design tokens & foundational styles
│   ├── variables.css         # CSS variables for colors, spacing, typography
│   ├── typography.css        # Font sizes, weights, heading styles
│   └── global.css            # Global page and element styles
├── components/               # Reusable UI components
│   ├── buttons.css           # Button styles (dashboard-btn, primary, etc)
│   ├── widgets.css           # Dashboard widget cards
│   ├── cards.css             # Settings, member, and general card styles
│   ├── toggle.css            # Toggle/checkbox switch component
│   ├── forms.css             # Form inputs, rows, labels, quick actions
│   └── table.css             # Table and list styles, status badges
└── pages/                    # Page-specific styles
    ├── dashboard.css         # Dashboard page
    ├── availability.css      # Availability management page
    ├── bookings.css          # Bookings list page
    └── settings.css          # Settings page
```

## Key Features

### 1. CSS Variables (Base Layer)
All design tokens are defined in `base/variables.css`:
- **Colors**: Primary, status colors, neutral grays, WordPress admin colors
- **Spacing**: Consistent 8px-based scale (xs, sm, md, lg, xl, 2xl, 3xl)
- **Typography**: Font sizes, family, weights
- **Components**: Border radius, shadows, transitions
- **Layout**: Max-widths for page containers

**Usage:**
```css
background-color: var(--cs-primary);
padding: var(--cs-space-lg);
border-radius: var(--cs-radius-lg);
```

### 2. Components
Reusable components that follow BEM-like naming:
- `.cs-dashboard-btn` - Primary button for dashboard
- `.cs-widget` - Dashboard overview widget card
- `.cs-card` - Generic card container
- `.cs-toggle` - Toggle/checkbox switch
- `.cs-form-row` - Form row layout
- `.cs-days-table` - Availability table

### 3. Page Styles
Minimal page-specific overrides that extend components.

## Benefits

✅ **Maintainability**: Changes to variables update across all pages automatically
✅ **Consistency**: Unified design tokens across the entire plugin
✅ **Scalability**: Easy to add new components without duplication
✅ **Performance**: Organized imports reduce CSS file sizes
✅ **Responsive**: Consistent breakpoints and media queries
✅ **WordPress Native**: Uses WordPress admin colors and patterns

## Adding New Styles

### New Component
1. Create `components/new-component.css`
2. Use CSS variables for all colors, spacing, etc
3. Add import to `admin.css`

### New Page
1. Create `pages/new-page.css`
2. Keep page-specific styles here
3. Reuse components whenever possible
4. Add import to `admin.css`

### Updating Colors
Edit `base/variables.css` - all dependent styles update automatically!

## Breakpoints

- **Desktop**: Default (> 1024px)
- **Tablet**: 1024px and below - 2-column layouts
- **Mobile**: 600px and below - single column, full-width

## WordPress Integration

All admin pages load `admin.css` via PHP enqueue:

```php
wp_enqueue_style('cs-admin-dashboard', CS_PLUGIN_URL . 'assets/css/admin.css');
```

The main `admin.css` file imports all components and page styles in correct order.
