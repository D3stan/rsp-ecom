import useTranslation from '@/hooks/useTranslation';
import { ImgHTMLAttributes } from 'react';

interface AppLogoIconProps extends Omit<ImgHTMLAttributes<HTMLImageElement>, 'src' | 'alt'> {
    className?: string;
}

export default function AppLogoIcon({ className = 'w-8 h-8', ...props }: AppLogoIconProps) {
    const { t } = useTranslation();
    return (
        <img
            src="/favicon.ico"
            alt={t('nav.logo_alt')}
            className={className}
            onError={(e) => {
                // Fallback to favicon.ico if SVG fails
                const target = e.target as HTMLImageElement;
                target.src = '/favicon.ico';
            }}
            {...props}
        />
    );
}
