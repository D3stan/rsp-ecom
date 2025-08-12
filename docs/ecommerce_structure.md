# Ecommerce Website Structure & User Experience Guide

## Tech Stack
The project is based on Laravel and React served with Inertiajs.
In particular it has:
- Laravel 12
- React 19
- Intertiajs 2.0
- MySQL 8.0
- Shadcn react library
- Laravel Cashier (Stripe Integration)
- Stripe Payment Processing

### Fronted Structure
```
resources/js/
├── components/    # Reusable React components
├── hooks/         # React hooks
├── layouts/       # Application layouts
├── lib/           # Utility functions and configuration
├── pages/         # Page components
└── types/         # TypeScript definitions
```

## Public Pages (Not Logged In)

### Home Page Layout
- **Header**
  - Logo/Brand name (top left)
  - Search bar (center)
  - Navigation menu: Categories, About, Contact
  - User actions: Login/Register, Cart icon with item counter
  
- **Hero Section**
  - Large banner with main promotional content
  - Call-to-action button (Shop Now, View Deals)
  
- **Featured Products**
  - Grid of 8-12 featured/bestselling products
  - Product cards showing: image, name, price, rating
  - Quick "Add to Cart" button on hover
  
- **Categories Section**
  - 4-6 main product categories with images
  - Clear category navigation
  
- **Footer**
  - Links: Privacy Policy, Terms of Service, FAQ
  - Contact information
  - Social media links

### Website Structure (Public)
```
├── Home (/)
├── Products (/products)
│   ├── Category pages (/products/category/{slug})
│   ├── Product detail (/products/{slug})
│   └── Search results (/products/search)
├── Cart (/cart)
├── Checkout (/checkout) - requires login
│   ├── Checkout Success (/checkout/success)
│   ├── Checkout Cancel (/checkout/cancel)
│   └── Guest Checkout (/checkout/guest)
├── Payment Methods (/payment-methods) - logged in users
├── About (/about)
├── Contact (/contact)
├── Auth
│   ├── Login (/login)
│   ├── Register (/register)
│   ├── Email Verification (/verification/pending)
│   ├── Verify Email Link (/verification/verify/{token}/{email})
│   └── Forgot Password (/forgot-password)
└── Legal
    ├── Privacy Policy (/privacy)
    └── Terms of Service (/terms)
```

### Key UX Principles
- **Mobile-first design** - responsive on all devices
- **Minimalistic style** - simple components and few colors
- **Clear navigation** - breadcrumbs, intuitive menu structure
- **Trust signals** - customer reviews, secure payment badges
- **Search functionality** - prominent search with filters
- **Guest checkout option** - don't force registration
- **Mandatory email verification** - all registered users must verify email before accessing protected features

## Admin Dashboard Sections

### 1. Dashboard Overview
- Sales statistics (today, week, month)
- Recent orders summary
- Low stock alerts
- Quick action buttons

### 2. Products Management
- **Product List**: View all products with bulk actions
- **Add/Edit Product**: Name, description, price, images, categories, stock
- **Categories**: Manage product categories and subcategories
- **Inventory**: Stock levels, low stock alerts

### 3. Orders Management
- **Order List**: All orders with status filters
- **Order Details**: Customer info, items, payment status, shipping
- **Order Status Updates**: Processing, shipped, delivered, cancelled

### 4. Customers Management
- **Customer List**: All registered customers
- **Customer Details**: Order history, contact information
- **Customer Support**: Messages, support tickets

### 5. Content Management
- **Homepage Content**: Hero banners, featured products
- **Pages**: About, Contact, FAQ content
- **Media Library**: Upload and manage images

### 6. Reports & Analytics
- **Sales Reports**: Revenue, orders, popular products
- **Customer Analytics**: Registration trends, purchase behavior
- **Inventory Reports**: Stock levels, reorder alerts

### 7. Settings
- **General Settings**: Site name, logo, contact info
- **Payment Settings**: Configure Stripe payment gateway, webhook settings
- **Shipping Settings**: Zones, rates, methods
- **Email Templates**: Order confirmations, notifications
- **Tax Settings**: Tax calculation configuration

## Logged-in User Dashboard

### 1. Account Overview
- Welcome message with recent activity
- Quick stats: Total orders, saved items, reward points

### 2. Orders
- **Order History**: List of all past orders
- **Order Tracking**: Current order status and tracking
- **Returns**: Request returns, view return status

### 3. Profile Management
- **Personal Information**: Name, email, phone
- **Addresses**: Shipping and billing addresses
- **Payment Methods**: Saved credit cards and payment methods (via Stripe)
- **Password**: Change password form

### 4. Wishlist/Favorites
- **Saved Items**: Products added to wishlist
- **Move to Cart**: Easy conversion from wishlist

### 5. Account Settings
- **Notifications**: Email preferences, order updates
- **Privacy Settings**: Data preferences
- **Account Deletion**: Self-service account closure

## User Flow Considerations

### Guest User Journey
1. Browse products → Add to cart → Checkout (with guest option)
2. Register during checkout for faster future purchases (requires email verification)

### Registered User Journey
1. Register → **Verify email via link** → Login → Browse → Add to cart → Quick checkout with saved info
2. Access order history and tracking (requires verified email)

### Mobile Experience
- Hamburger menu for navigation
- Touch-friendly buttons and forms
- Optimized product image galleries
- One-thumb checkout process

## Performance & Security
- Implement caching for product catalogs
- Secure payment processing with Stripe (PCI compliance handled by Stripe)
- SSL certificates for all pages
- Rate limiting for API endpoints
- Image optimization and CDN usage
- Webhook signature verification for Stripe events
- Strong Customer Authentication (SCA) support for European customers
- **Mandatory email verification** with secure token-based system
- **Protected checkout routes** requiring verified email addresses
- **Pending user verification system** preventing database pollution from unverified accounts