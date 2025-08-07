<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Page>
 */
class PageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $pageTypes = [
            'About Us' => [
                'slug' => 'about-us',
                'content' => 'Learn more about our company, our mission, and our commitment to providing excellent products and services to our customers worldwide.',
            ],
            'Privacy Policy' => [
                'slug' => 'privacy-policy',
                'content' => 'This privacy policy explains how we collect, use, and protect your personal information when you use our website and services.',
            ],
            'Terms of Service' => [
                'slug' => 'terms-of-service',
                'content' => 'These terms of service govern your use of our website and services. Please read them carefully before using our platform.',
            ],
            'FAQ' => [
                'slug' => 'faq',
                'content' => 'Frequently asked questions about our products, shipping, returns, and customer service policies.',
            ],
            'Contact Us' => [
                'slug' => 'contact-us',
                'content' => 'Get in touch with our customer service team. We are here to help with any questions or concerns you may have.',
            ],
            'Shipping Information' => [
                'slug' => 'shipping-info',
                'content' => 'Detailed information about our shipping methods, delivery times, and shipping costs for different regions.',
            ],
            'Return Policy' => [
                'slug' => 'return-policy',
                'content' => 'Our comprehensive return and refund policy to ensure customer satisfaction with every purchase.',
            ],
            'Size Guide' => [
                'slug' => 'size-guide',
                'content' => 'Complete sizing charts and measurement guides to help you find the perfect fit for clothing and footwear.',
            ],
        ];

        $pageTitle = $this->faker->randomElement(array_keys($pageTypes));
        $pageData = $pageTypes[$pageTitle];

        return [
            'title' => $pageTitle,
            'slug' => $pageData['slug'],
            'content' => $this->expandContent($pageData['content']),
            'is_active' => $this->faker->boolean(90), // 90% active
        ];
    }

    /**
     * Expand basic content into more realistic page content
     */
    private function expandContent(string $baseContent): string
    {
        $paragraphs = [
            $baseContent,
            $this->faker->paragraph(6),
            $this->faker->paragraph(4),
        ];

        // Add some variation with lists or additional sections
        if ($this->faker->boolean(40)) {
            $paragraphs[] = "## Key Points\n\n" . 
                "- " . $this->faker->sentence() . "\n" .
                "- " . $this->faker->sentence() . "\n" .
                "- " . $this->faker->sentence() . "\n";
        }

        if ($this->faker->boolean(30)) {
            $paragraphs[] = $this->faker->paragraph(3);
        }

        return implode("\n\n", $paragraphs);
    }

    /**
     * Create an active page
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Create an inactive page
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Create specific pages
     */
    public function aboutUs(): static
    {
        return $this->state(fn (array $attributes) => [
            'title' => 'About Us',
            'slug' => 'about-us',
            'content' => $this->getAboutUsContent(),
        ]);
    }

    public function privacyPolicy(): static
    {
        return $this->state(fn (array $attributes) => [
            'title' => 'Privacy Policy',
            'slug' => 'privacy-policy',
            'content' => $this->getPrivacyPolicyContent(),
        ]);
    }

    public function termsOfService(): static
    {
        return $this->state(fn (array $attributes) => [
            'title' => 'Terms of Service',
            'slug' => 'terms-of-service',
            'content' => $this->getTermsOfServiceContent(),
        ]);
    }

    public function contactUs(): static
    {
        return $this->state(fn (array $attributes) => [
            'title' => 'Contact Us',
            'slug' => 'contact-us',
            'content' => $this->getContactUsContent(),
        ]);
    }

    /**
     * Generate detailed content for specific pages
     */
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
               "- **Email**: support@rsp-industries.com\n" .
               "- **Phone**: +1 (555) 123-4567\n" .
               "- **Hours**: Monday - Friday, 9:00 AM - 5:00 PM EST\n\n" .
               "## Business Address\n\n" .
               "123 Business Street\n" .
               "City, State 12345\n" .
               "United States\n\n" .
               "## Response Time\n\n" .
               "We typically respond to emails within 24 hours during business days.";
    }
}
