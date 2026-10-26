import Header from '@/components/header';
import { useTranslation } from '@/hooks/useTranslation';
import { Head, usePage } from '@inertiajs/react';

interface CookieRow {
    name: string;
    description: string;
    duration: string;
}

interface CookieCategory {
    key: string;
    title: string;
    description: string;
    cookies: CookieRow[];
}

export default function Cookies({ categories }: { categories: CookieCategory[] }) {
    const { t } = useTranslation();
    const { csrf_token: csrfToken } = usePage<{ csrf_token: string }>().props;

    return (
        <>
            <Head title={`${t('cookies_page.title')} - Your Store`} />

            <div className="min-h-screen bg-white">
                <Header />

                <div className="mx-auto max-w-4xl px-4 py-16 sm:px-6 lg:px-8">
                    <div className="mb-12 text-center">
                        <h1 className="mb-6 text-4xl font-bold text-black">{t('cookies_page.title')}</h1>
                        <p className="text-gray-600">{t('cookies_page.intro')}</p>
                    </div>

                    <div className="space-y-10">
                        {categories.map((category) => (
                            <section key={category.key}>
                                <h2 className="mb-2 text-2xl font-bold text-black">{category.title}</h2>
                                <p className="mb-4 text-gray-600">{category.description}</p>

                                <div className="overflow-x-auto">
                                    <table className="w-full min-w-[32rem] border-collapse text-left text-sm text-gray-600">
                                        <thead>
                                            <tr className="border-b border-gray-200 text-black">
                                                <th className="py-2 pr-4 font-semibold">{t('cookies_page.name')}</th>
                                                <th className="py-2 pr-4 font-semibold">{t('cookies_page.purpose')}</th>
                                                <th className="py-2 font-semibold">{t('cookies_page.duration')}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {category.cookies.map((cookie) => (
                                                <tr key={cookie.name} className="border-b border-gray-100 align-top">
                                                    <td className="py-2 pr-4 font-mono break-all">{cookie.name}</td>
                                                    <td className="py-2 pr-4">{cookie.description}</td>
                                                    <td className="py-2">{cookie.duration}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </section>
                        ))}

                        <section>
                            <h2 className="mb-2 text-2xl font-bold text-black">{t('cookies_page.third_parties')}</h2>
                            <ul className="list-inside list-disc space-y-2 text-gray-600">
                                <li>{t('cookies_page.third_party_sentry')}</li>
                                <li>{t('cookies_page.third_party_fonts')}</li>
                                <li>{t('cookies_page.third_party_stripe')}</li>
                            </ul>
                        </section>

                        <section>
                            <h2 className="mb-2 text-2xl font-bold text-black">{t('cookies_page.your_choices')}</h2>
                            <p className="mb-4 text-gray-600">{t('cookies_page.your_choices_text')}</p>
                            {/* A plain form on purpose: the banner is rendered by the full page load, not by an Inertia visit */}
                            <form method="post" action={route('cookieconsent.reset')}>
                                <input type="hidden" name="_token" value={csrfToken} />
                                <button type="submit" className="rounded-md bg-black px-4 py-2 text-sm font-medium text-white hover:bg-gray-800">
                                    {t('cookies_page.change_preferences')}
                                </button>
                            </form>
                        </section>
                    </div>
                </div>
            </div>
        </>
    );
}
