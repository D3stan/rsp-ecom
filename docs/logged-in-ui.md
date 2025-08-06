**Tech stack**: Laravel 12 API + React 19 (Inertia) frontend using **shadcn/ui** components  

---

## Admin Dashboard – mandatory areas (ordered by criticality)

| # | Section | Purpose & key DB entities |
|---|---------|---------------------------|
| **1** | **Products** | Central catalogue control.<br/>*Sub-sections*<br/>• **Add Product** – multi-image upload (`images` JSON), SKU, price, compare_price, stock_quantity, category_id, size_id.<br/>• **All Products** – paginated table with search, filters (category, status, stock), sortable columns (price, stock, created_at). Row actions: edit, delete, **Quick Stock** (+/− qty) and **Flash Discount** (temporary compare_price override). |
| **2** | **Orders** | Full order lifecycle (`orders`, `order_items`). Advanced filtering, bulk status update, printable invoices, refunds. |
| **3** | **Customers** | Manage `users`. View profile, latest orders, balances; impersonate; soft-deactivate. |
| **4** | **Categories & Sizes** | Maintain product taxonomy (`categories`) and logistics dimensions (`sizes`). |
| **5** | **Reviews moderation** | Approve / reject product feedback (`reviews`). |
| **6** | **Content (CMS)** | Edit `pages`, manage homepage banners/media. |
| **7** | **Reports & Analytics** | Sales KPIs, inventory alerts, customer growth. |
| **8** | **Settings** | Global config (`settings`): payment, shipping, tax, email templates. |

---

## Customer Dashboard – mandatory areas (ordered by criticality)

| # | Section | Purpose & key DB entities |
|---|---------|---------------------------|
| **1** | **Orders** | History & real-time tracking (`orders`, `order_items`). Returns, invoices. |
| **2** | **Profile** | Edit personal data (`users`), manage **Addresses** (`addresses`). |
| **3** | **Payment Methods** | Add / remove cards via Stripe Billing Portal. |
| **4** | **Wishlist / Favorites** | Save products (`wishlists`) with “Move to Cart”. |
| **5** | **Account Settings** | Notification toggles, privacy, self-delete. |
| **6** | **Reviews** | Write & manage product reviews (`reviews`). |
| **7** | **Support** | Open tickets / chat; reference order #. |

---

## UI / Layout specification

> Component names reference **shadcn/ui**; charts use **Recharts**; icons from **Lucide-React**.

### 1&nbsp;· Global Layout

* **Desktop ≥ 1280 px** – Fixed **Sidebar** (w-64) left, main content right; **TopBar** (h-16) for search, notifications, profile dropdown.  
* **Tablet / Mobile** – Collapsible sidebar (*Drawer*), TopBar gains hamburger.  
* Consistent **PageHeader**: H1, breadcrumb, right-aligned action buttons.  
* Use **Card** wrappers (rounded-2xl, shadow-sm, p-6) for all content blocks.

### 2&nbsp;· Admin Pages

| Page | Primary Components | Layout notes |
|------|--------------------|--------------|
| **Products → Add** | `Form`, `Tabs` (“Basic”, “Media”, “Inventory”, “SEO”) | Two-column grid on desktop: 2/3 form fields, 1/3 sticky summary card (status toggle, publish button). “Media” tab → **ImageUploader** (multi-drag-drop, reorder). |
| **Products → All** | `DataTable`, `DropdownMenu`, `Input` (search), `Select` (filters), `DateRangePicker` | Filters row above table; columns: Thumb, Name, Category, Price, Stock (badge), Status, Actions (icon menu). Pagination below. |
| **Orders** | `DataTable`, `Badge` (status), `Calendar` (range picker), `Drawer` (order detail) | KPI cards (Today, MTD, YTD) in 4-item grid above filters. Row click → drawer with tabs “Summary”, “Timeline”, “Payment”. |
| **Customers** | `DataTable`, `Avatar`, `Drawer` | Search + active/inactive filter. Row → drawer: profile, last 5 orders, impersonate btn. |
| **Categories & Sizes** | `Card`, `Accordion`, `Form` | Left: inline editable list; right: add/edit form. |
| **Reviews moderation** | `DataTable`, `Toggle` (approve), `Popover` (product preview) | Inline approve toggle; bulk approve via toolbar. |
| **Reports & Analytics** | `Card`, `LineChart`, `BarChart`, `Table` | 12-col grid: KPI cards, revenue line (12-mo), bar (orders by category), low-stock table. Responsive. |
| **Settings** | `Tabs` vertical on md+, stacked on mobile | Each tab (Payments, Shipping, Tax, Email) shows forms inside cards; save btn fixed bottom. |

### 3&nbsp;· Customer Pages

| Page | Primary Components | Layout notes |
|------|--------------------|--------------|
| **Orders** | `Timeline`, `Card`, `Button` | Order card: header (status badge, date), product thumbs, total. Expand → timeline; CTA (Re-order, Return). |
| **Profile** | `Form` + `AvatarUploader` | Two-panel: left profile form, right default address card + “Manage Addresses”. |
| **Payment Methods** | `Card`, `Badge` (default), `Button` | Cards listed; “Add Card” opens Stripe modal. |
| **Wishlist** | `Grid` (auto-fit min-w-56), `ProductCard` | Heart-toggle, “Add to Cart”. |
| **Account Settings** | `Toggle`, `Button` (danger) | Sections by heading; destructive actions in red. |
| **Reviews** | `Card`, `Textarea`, `RatingStars` | Past reviews list (edit/del); new review form at top when routed from product page. |
| **Support** | `Textarea`, `Select` (topic), `Button`, `MessageList` | Ticket composer on top; previous msgs scrollable below; real-time via WS. |

### 4&nbsp;· Accessibility & Responsiveness

* All interactive elements have `aria-label` and focus ring.  
* Color contrast ≥ WCAG AA; light/dark via CSS vars.  
* Below 768 px grids collapse to single column; tables convert to stacked cards.

### 5&nbsp;· Design tokens

* **Primary accent**: brand-600 (hover brand-700).  
* **Border radius**: `rounded-2xl` everywhere.  
* **Spacing scale**: 4-pt increments; section padding `py-8`.  
* **Shadow**: `shadow-sm` for cards, `shadow` on hover for actionable cards.

---

This spec aligns UI and DB schema for a cohesive, maintainable ecommerce back-office and customer portal.
