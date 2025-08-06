<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Creating essential pages...');
        
        // Create essential pages
        $pages = [
            [
                'title' => 'About Us',
                'slug' => 'about-us',
                'content' => $this->getAboutUsContent(),
                'is_active' => true,
            ],
            [
                'title' => 'Privacy Policy',
                'slug' => 'privacy-policy',
                'content' => $this->getPrivacyPolicyContent(),
                'is_active' => true,
            ],
            [
                'title' => 'Terms of Service',
                'slug' => 'terms-of-service',
                'content' => $this->getTermsOfServiceContent(),
                'is_active' => true,
            ],
            [
                'title' => 'Contact Us',
                'slug' => 'contact-us',
                'content' => $this->getContactUsContent(),
                'is_active' => true,
            ],
            [
                'title' => 'Shipping Information',
                'slug' => 'shipping-info',
                'content' => $this->getShippingInfoContent(),
                'is_active' => true,
            ],
            [
                'title' => 'Return Policy',
                'slug' => 'return-policy',
                'content' => $this->getReturnPolicyContent(),
                'is_active' => true,
            ],
            [
                'title' => 'Size Guide',
                'slug' => 'size-guide',
                'content' => $this->getSizeGuideContent(),
                'is_active' => true,
            ],
            [
                'title' => 'FAQ',
                'slug' => 'faq',
                'content' => $this->getFaqContent(),
                'is_active' => true,
            ],
        ];

        foreach ($pages as $pageData) {
            Page::updateOrCreate(
                ['slug' => $pageData['slug']], // Find by slug
                $pageData // Update or create with all data
            );
            $this->command->info("Created/updated page: {$pageData['title']}");
        }

        $this->command->info('Pages seeded successfully!');
    }

    private function getAboutUsContent(): string
    {
        return "# About Our Company\n\n" .
               "Welcome to our online store! We are passionate about providing high-quality products and exceptional customer service.\n\n" .
               "## Our Mission\n\n" .
               "Our mission is to make online shopping easy, enjoyable, and accessible to everyone. We carefully curate our product selection to ensure we offer only the best items at competitive prices.\n\n" .
               "## Our Values\n\n" .
               "- **Quality**: We never compromise on product quality\n" .
               "- **Customer Service**: Your satisfaction is our top priority\n" .
               "- **Innovation**: We continuously improve our shopping experience\n" .
               "- **Sustainability**: We care about our environmental impact\n\n" .
               "Thank you for choosing us for your shopping needs!";
    }

    private function getPrivacyPolicyContent(): string
    {
        return "# Privacy Policy\n\n" .
               "This privacy policy describes how we collect, use, and protect your personal information.\n\n" .
               "## Information We Collect\n\n" .
               "We collect information you provide directly to us, such as when you create an account, make a purchase, or contact us.\n\n" .
               "## How We Use Your Information\n\n" .
               "We use your information to process orders, provide customer support, and improve our services.\n\n" .
               "## Information Sharing\n\n" .
               "We do not sell, trade, or otherwise transfer your personal information to third parties without your consent.\n\n" .
               "Last updated: " . now()->format('F j, Y');
    }

    private function getTermsOfServiceContent(): string
    {
        return "# Terms of Service\n\n" .
               "Please read these terms carefully before using our website.\n\n" .
               "## Acceptance of Terms\n\n" .
               "By accessing and using this website, you accept and agree to be bound by the terms and provision of this agreement.\n\n" .
               "## Use License\n\n" .
               "Permission is granted to temporarily download one copy of the materials for personal, non-commercial transitory viewing only.\n\n" .
               "## Disclaimer\n\n" .
               "The materials on this website are provided on an 'as is' basis. We make no warranties, expressed or implied.\n\n" .
               "## Contact Information\n\n" .
               "If you have any questions about these Terms of Service, please contact us.";
    }

    private function getContactUsContent(): string
    {
        return "# Contact Us\n\n" .
               "We'd love to hear from you! Get in touch with our customer service team.\n\n" .
               "## Customer Service\n\n" .
               "- **Email**: support@example.com\n" .
               "- **Phone**: +1 (555) 123-4567\n" .
               "- **Hours**: Monday - Friday, 9:00 AM - 5:00 PM EST\n\n" .
               "## Business Address\n\n" .
               "123 Business Street\n" .
               "City, State 12345\n" .
               "United States\n\n" .
               "## Response Time\n\n" .
               "We typically respond to emails within 24 hours during business days.";
    }

    private function getShippingInfoContent(): string
    {
        return "# Shipping Information\n\n" .
               "We offer fast and reliable shipping options to get your orders to you quickly.\n\n" .
               "## Shipping Methods\n\n" .
               "- **Standard Shipping**: 5-7 business days - €5.99\n" .
               "- **Express Shipping**: 2-3 business days - €9.99\n" .
               "- **Next Day Delivery**: 1 business day - €19.99\n\n" .
               "## Free Shipping\n\n" .
               "Free standard shipping on orders over €50!\n\n" .
               "## International Shipping\n\n" .
               "We ship to all EU countries. International shipping rates and delivery times vary by destination.";
    }

    private function getReturnPolicyContent(): string
    {
        return "# Return Policy\n\n" .
               "We want you to be completely satisfied with your purchase.\n\n" .
               "## Return Window\n\n" .
               "You may return items within 30 days of delivery for a full refund.\n\n" .
               "## Return Conditions\n\n" .
               "- Items must be in original condition\n" .
               "- Original packaging and tags must be included\n" .
               "- Items must be unworn and unused\n\n" .
               "## How to Return\n\n" .
               "1. Contact our customer service team\n" .
               "2. Print the return label we provide\n" .
               "3. Package your items securely\n" .
               "4. Drop off at any authorized shipping location\n\n" .
               "## Refund Processing\n\n" .
               "Refunds are processed within 5-7 business days after we receive your return.";
    }

    private function getSizeGuideContent(): string
    {
        return "# Size Guide\n\n" .
               "Find your perfect fit with our comprehensive sizing charts.\n\n" .
               "## Clothing Sizes\n\n" .
               "### Women's Clothing\n" .
               "- **XS**: Bust 32-34\", Waist 24-26\", Hips 34-36\"\n" .
               "- **S**: Bust 34-36\", Waist 26-28\", Hips 36-38\"\n" .
               "- **M**: Bust 36-38\", Waist 28-30\", Hips 38-40\"\n" .
               "- **L**: Bust 38-40\", Waist 30-32\", Hips 40-42\"\n" .
               "- **XL**: Bust 40-42\", Waist 32-34\", Hips 42-44\"\n\n" .
               "### Men's Clothing\n" .
               "- **S**: Chest 34-36\", Waist 28-30\"\n" .
               "- **M**: Chest 36-38\", Waist 30-32\"\n" .
               "- **L**: Chest 38-40\", Waist 32-34\"\n" .
               "- **XL**: Chest 40-42\", Waist 34-36\"\n\n" .
               "## Shoe Sizes\n\n" .
               "### EU to US Conversion\n" .
               "- **EU 36** = US 6\n" .
               "- **EU 37** = US 7\n" .
               "- **EU 38** = US 8\n" .
               "- **EU 39** = US 9\n" .
               "- **EU 40** = US 10\n" .
               "- **EU 41** = US 11\n" .
               "- **EU 42** = US 12";
    }

    private function getFaqContent(): string
    {
        return "# Frequently Asked Questions\n\n" .
               "## Ordering\n\n" .
               "**Q: How do I place an order?**\n" .
               "A: Simply browse our products, add items to your cart, and proceed to checkout.\n\n" .
               "**Q: Can I modify my order after placing it?**\n" .
               "A: Orders can be modified within 1 hour of placement. Please contact us immediately.\n\n" .
               "## Shipping\n\n" .
               "**Q: How long does shipping take?**\n" .
               "A: Standard shipping takes 5-7 business days, express shipping takes 2-3 days.\n\n" .
               "**Q: Do you ship internationally?**\n" .
               "A: Yes, we ship to all EU countries.\n\n" .
               "## Returns\n\n" .
               "**Q: What is your return policy?**\n" .
               "A: We accept returns within 30 days of delivery in original condition.\n\n" .
               "**Q: Who pays for return shipping?**\n" .
               "A: We provide free return shipping labels for all returns.\n\n" .
               "## Account\n\n" .
               "**Q: Do I need an account to place an order?**\n" .
               "A: No, you can checkout as a guest, but having an account makes future orders easier.";
    }
}
