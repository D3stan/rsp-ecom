import { Badge } from '@/components/ui/badge';
import useTranslation from '@/hooks/useTranslation';

export type CampaignStatus = 'draft' | 'scheduled' | 'sending' | 'sent' | 'cancelled';

const VARIANT: Record<CampaignStatus, 'default' | 'secondary' | 'outline' | 'destructive'> = {
    draft: 'outline',
    scheduled: 'secondary',
    sending: 'secondary',
    sent: 'default',
    cancelled: 'destructive',
};

export default function CampaignStatusBadge({ status }: { status: CampaignStatus }) {
    const { t } = useTranslation();

    return <Badge variant={VARIANT[status]}>{t(`newsletter.admin.campaign.status.${status}`)}</Badge>;
}
