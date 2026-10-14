import useTranslation from '@/hooks/useTranslation';
import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';

/** Sub-navigation of Admin > Newsletter. */
export default function NewsletterTabs({ active }: { active: 'subscribers' | 'campaigns' }) {
    const { t } = useTranslation();

    const tabs = [
        { key: 'subscribers', href: route('admin.newsletter.subscribers'), label: t('newsletter.admin.tabs.subscribers') },
        { key: 'campaigns', href: route('admin.newsletter.campaigns.index'), label: t('newsletter.admin.tabs.campaigns') },
    ] as const;

    return (
        <nav className="flex gap-1 border-b" aria-label={t('newsletter.admin.title')}>
            {tabs.map((tab) => (
                <Link
                    key={tab.key}
                    href={tab.href}
                    aria-current={tab.key === active ? 'page' : undefined}
                    className={cn(
                        '-mb-px border-b-2 px-4 py-2 text-sm font-medium transition-colors',
                        tab.key === active ? 'border-foreground text-foreground' : 'border-transparent text-muted-foreground hover:text-foreground',
                    )}
                >
                    {tab.label}
                </Link>
            ))}
        </nav>
    );
}
