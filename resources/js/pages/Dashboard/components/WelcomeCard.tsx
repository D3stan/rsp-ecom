import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { User } from '@/types';

export const WelcomeCard = ({ user }: { user: User }) => {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Welcome, {user.name}!</CardTitle>
            </CardHeader>
            <CardContent>
                <div className="flex items-center">
                    <Avatar>
                        <AvatarImage src={user.avatar_url} alt={user.name} />
                        <AvatarFallback>{user.name.charAt(0)}</AvatarFallback>
                    </Avatar>
                    <p className="ml-4">Here's a quick look at your account.</p>
                </div>
            </CardContent>
        </Card>
    );
};
