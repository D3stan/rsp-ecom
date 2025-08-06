import { ImgHTMLAttributes } from 'react';

interface AppLogoIconProps extends Omit<ImgHTMLAttributes<HTMLImageElement>, 'src' | 'alt'> {
    className?: string;
}

export default function AppLogoIcon({ className = "w-8 h-8", ...props }: AppLogoIconProps) {
    return (
        <img
            src="/favicon.ico"
            alt="Logo"
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
