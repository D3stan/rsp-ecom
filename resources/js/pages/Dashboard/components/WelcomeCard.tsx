import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { type User } from '@/types';
import { CheckCircle, AlertCircle } from 'lucide-react';
import { useTranslation } from '@/hooks/useTranslation';

export const WelcomeCard = ({ user }: { user: User }) => {
    const { t } = useTranslation();
    
    const formatTime = () => {
        const hour = new Date().getHours();
        if (hour < 12) return t('dashboard.good_morning');
        if (hour < 17) return t('dashboard.good_afternoon');
        return t('dashboard.good_evening');
    };

    const getInitials = (name: string) => {
        return name
            .split(' ')
            .map(word => word.charAt(0))
            .join('')
            .toUpperCase()
            .slice(0, 2);
    };

    return (
        <Card className="border-l-4 border-l-primary w-full overflow-hidden">
            <CardHeader>
                <CardTitle className="text-xl break-words">
                    {formatTime()}, {user.name}!
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div className="flex flex-col sm:flex-row sm:items-center gap-4">
                    <Avatar className="h-16 w-16 flex-shrink-0">
                        <AvatarImage src={user.avatar} alt={user.name} />
                        <AvatarFallback className="text-lg font-semibold">
                            {getInitials(user.name)}
                        </AvatarFallback>
                    </Avatar>
                    
                    <div className="flex-1 space-y-2 min-w-0">
                        <p className="text-muted-foreground break-words">
                            {t('dashboard.welcome_description')}
                        </p>
                        
                        <div className="flex flex-wrap gap-2">
                            <Badge 
                                variant={user.email_verified_at ? "outline" : "destructive"}
                                className="flex items-center gap-1 flex-shrink-0"
                            >
                                {user.email_verified_at ? (
                                    <>
                                        <CheckCircle className="h-3 w-3" />
                                        {t('dashboard.email_verified')}
                                    </>
                                ) : (
                                    <>
                                        <AlertCircle className="h-3 w-3" />
                                        {t('dashboard.email_unverified')}
                                    </>
                                )}
                            </Badge>
                            
                            {user.google_id && (
                                <Badge variant="outline" className="flex items-center gap-1 flex-shrink-0">
                                    <svg className="h-3 w-3" viewBox="0 0 24 24">
                                        <path
                                            fill="currentColor"
                                            d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"
                                        />
                                        <path
                                            fill="currentColor"
                                            d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"
                                        />
                                        <path
                                            fill="currentColor"
                                            d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"
                                        />
                                        <path
                                            fill="currentColor"
                                            d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"
                                        />
                                    </svg>
                                    {t('dashboard.google_connected')}
                                </Badge>
                            )}
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
};
