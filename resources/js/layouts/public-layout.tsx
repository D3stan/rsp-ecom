import FooterLegalLine from '@/components/footer-legal-line';
import FooterSocialLinks from '@/components/footer-social-links';
import Header from '@/components/header';
import NewsletterSignup from '@/components/newsletter/NewsletterSignup';
import { useSetting } from '@/hooks/use-setting';
import useTranslation from '@/hooks/useTranslation';
import { Link } from '@inertiajs/react';
import { type ReactNode } from 'react';

interface PublicLayoutProps {
    children: ReactNode;
    transparent?: boolean;
    currentPage?: 'home' | 'products' | 'about' | 'contact';
}

export default function PublicLayout({ children, transparent = false, currentPage }: PublicLayoutProps) {
    const { t } = useTranslation();
    const storeName = useSetting('store.name', 'Store');

    return (
        <div className="min-h-screen bg-white">
            {/* Header */}
            <Header currentPage={currentPage} transparent={transparent} />

            {/* Main Content */}
            <main className={transparent ? '' : 'pt-0'}>{children}</main>

            {/* Footer */}
            <footer className="bg-black py-12 text-white">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="grid grid-cols-1 gap-8 md:grid-cols-4">
                        <div>
                            <h3 className="mb-4 text-xl font-bold">{storeName}</h3>
                            <p className="mb-4 text-gray-400">{t('store_description')}</p>
                        </div>

                        <div>
                            <h4 className="mb-4 font-semibold">{t('quick_links')}</h4>
                            <ul className="space-y-2 text-gray-400">
                                <li>
                                    <Link href="/about" className="footer-link md:footer-link-none hover:text-white">
                                        {t('about_us')}
                                    </Link>
                                </li>
                                <li>
                                    <Link href="/contact" className="footer-link md:footer-link-none hover:text-white">
                                        {t('contact_page')}
                                    </Link>
                                </li>
                                <li>
                                    <Link href="/faq" className="footer-link md:footer-link-none hover:text-white">
                                        {t('faq')}
                                    </Link>
                                </li>
                            </ul>
                        </div>

                        <div>
                            <h4 className="mb-4 font-semibold">{t('legal')}</h4>
                            <ul className="space-y-2 text-gray-400">
                                <li>
                                    <Link href="/privacy" className="footer-link md:footer-link-none hover:text-white">
                                        {t('privacy_policy')}
                                    </Link>
                                </li>
                                <li>
                                    <Link href="/terms" className="footer-link md:footer-link-none hover:text-white">
                                        {t('terms_of_service')}
                                    </Link>
                                </li>
                                <li>
                                    <Link href="/shipping-returns" className="footer-link md:footer-link-none hover:text-white">
                                        {t('shipping_returns_link')}
                                    </Link>
                                </li>
                            </ul>
                        </div>

                        <FooterSocialLinks />
                    </div>

                    <NewsletterSignup className="mt-8" />

                    <div className="mt-8 border-t border-gray-800 pt-8 text-center text-gray-400">
                        <p>
                            &copy; {new Date().getFullYear()} {storeName}. {t('rights_reserved')}
                        </p>
                        <FooterLegalLine />
                    </div>
                </div>
            </footer>
        </div>
    );
}
