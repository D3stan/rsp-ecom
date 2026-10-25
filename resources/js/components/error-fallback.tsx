import { Button } from '@/components/ui/button';
import useTranslation from '@/hooks/useTranslation';
import { AlertTriangle, RefreshCw } from 'lucide-react';

// Shown by the root ErrorBoundary when a component crashes while rendering: the page is gone, so all we can offer is a reload.
export default function ErrorFallback() {
    const { t } = useTranslation();

    return (
        <div className="flex min-h-screen flex-col items-center justify-center gap-6 px-4 text-center">
            <AlertTriangle className="h-12 w-12 text-red-600" />
            <div className="space-y-2">
                <h1 className="text-2xl font-bold text-gray-900">{t('error_pages.crash.title')}</h1>
                <p className="text-gray-600">{t('error_pages.crash.message')}</p>
            </div>
            <Button onClick={() => window.location.reload()} size="lg">
                <RefreshCw className="mr-2 h-4 w-4" />
                {t('error_pages.crash.reload')}
            </Button>
        </div>
    );
}
